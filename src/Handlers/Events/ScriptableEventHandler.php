<?php

namespace DreamFactory\Core\Script\Handlers\Events;

use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Events\ApiEvent;
use DreamFactory\Core\Events\PostProcessApiEvent;
use DreamFactory\Core\Events\PreProcessApiEvent;
use DreamFactory\Core\Events\ServiceEvent;
use DreamFactory\Core\Script\Components\ScriptHandler;
use DreamFactory\Core\Script\Events\BaseEventScriptEvent;
use DreamFactory\Core\Script\Events\EventScriptDeletedEvent;
use DreamFactory\Core\Script\Events\EventScriptModifiedEvent;
use DreamFactory\Core\Script\Jobs\ServiceEventScriptJob;
use DreamFactory\Core\Script\Models\EventScript;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Log;

class ScriptableEventHandler
{
    use ScriptHandler;
    use DispatchesJobs;

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     */
    public function subscribe($events)
    {
        $events->listen(
            [
                PreProcessApiEvent::class,
                PostProcessApiEvent::class,
            ],
            static::class . '@handleApiEvent'
        );
        $events->listen(
            [
                ApiEvent::class,
                ServiceEvent::class,
            ],
            static::class . '@handleServiceEvent'
        );
        $events->listen(
            [
                EventScriptModifiedEvent::class,
                EventScriptDeletedEvent::class,
            ],
            static::class . '@handleEventScriptEvent'
        );
    }

    /**
     * Handle events.
     *
     * @param ApiEvent $event
     *
     * @return boolean
     */
    public function handleApiEvent($event)
    {
        Log::debug('API event handled: ' . $event->name);

        if ($script = $this->getEventScript($event->name)) {
            Log::debug('API event script found: ' . $event->name);
            $data = $event->makeData();

            if (null !== $result = $this->handleEventScript($script, $data)) {
                if ($script->allow_event_modification) {
                    if ($event instanceof PreProcessApiEvent) {
                        // request only
                        $event->request->mergeFromArray((array)array_get($result, 'request'));

                        // new feature to allow pre-process to circumvent process by returning response
                        if (!empty($response = array_get($result, 'response'))) {
                            if (is_array($response) && array_key_exists('content', $response)) {
                                $content = array_get($response, 'content');
                                $contentType = array_get($response, 'content_type');
                                $status = array_get($response, 'status_code', HttpStatusCodeInterface::HTTP_OK);

                                $event->response = ResponseFactory::create($content, $contentType, $status);
                            } else {
                                // otherwise assume raw content
                                $event->response = ResponseFactory::create($response);
                            }
                        }
                    } elseif ($event instanceof PostProcessApiEvent) {
                        if (empty($response = array_get($result, 'response', []))) {
                            // check for "return" results
                            // could be formatted array or raw content
                            if (is_array($result) && (isset($result['content']) || isset($result['status_code']))) {
                                $response = $result;
                            } else {
                                // otherwise must be raw content, assumes 200
                                $response = [
                                    'content'     => $result,
                                    'status_code' => HttpStatusCodeInterface::HTTP_OK
                                ];
                            }
                        }

                        // response only
                        if ($event->response instanceof ServiceResponseInterface) {
                            $event->response->mergeFromArray($response);
                        } else {
                            $event->response = $response;
                        }
                    }
                }

                return $this->handleEventScriptResult($script, $result);
            }
        }

        return true;
    }

    /**
     * Handle queueable service events.
     *
     * @param ServiceEvent $event
     *
     * @return boolean
     */
    public function handleServiceEvent($event)
    {
        Log::debug('Service event handled: ' . $event->name);

        if ($script = $this->getEventScript($event->name)) {
            Log::debug('Service event script found: ' . $event->name);
            $data = $event->makeData();

            if (null !== $result = $this->handleEventScript($script, $data)) {
                return $this->handleEventScriptResult($script, $result);
            }
        } elseif ($script = $this->getEventScript($event->name . '.queued')) {
            Log::debug('Queued service event script found: ' . $event->name);
            $result = $this->dispatchNow(new ServiceEventScriptJob($event->name . '.queued', $event, $script->config));
            Log::debug('Service event queued: ' . $event->name . PHP_EOL . $result);
        }

        return true;
    }

    /**
     * @param string $name
     *
     * @return EventScript|null
     */
    public function getEventScript($name)
    {
        $cacheKey = 'event_script:' . $name;
        try {
            /** @var EventScript $model */
            $model = \Cache::rememberForever($cacheKey, function () use ($name){
                if ($model = EventScript::whereName($name)->whereIsActive(true)->first()) {
                    return $model;
                }

                return ''; // so that we don't hit the database even after we know it isn't there
            });

            if (!empty($model)) { // see '' returned above
                $model->content = Session::translateLookups($model->content, true);

                if (!empty($model->storage_service_id) && !empty($model->storage_path)) {
                    $remoteContent = \Cache::rememberForever(
                        $cacheKey . ':remote',
                        function () use ($model, $cacheKey){
                            try {
                                $serviceId = $model->storage_service_id;
                                $storagePath = trim($model->storage_path, '/');
                                $scmRef = $model->scm_reference;
                                if (empty($scmRef)) {
                                    $scmRef = null;
                                }

                                $service = \ServiceManager::getServiceById($serviceId);
                                $serviceName = $service->getName();
                                $typeGroup = $service->getServiceTypeInfo()->getGroup();

                                if ($typeGroup === ServiceTypeGroups::SCM) {
                                    $pathArray = explode('/', $storagePath);
                                    $repoName = $pathArray[0];
                                    array_shift($pathArray);
                                    $repoPath = implode('/', $pathArray);
                                    $result = \ServiceManager::handleRequest(
                                        $serviceName,
                                        Verbs::GET,
                                        '_repo/' . $repoName,
                                        ['path' => $repoPath, 'branch' => $scmRef, 'content' => 1]
                                    );
                                    $remoteContent = $result->getContent();
                                } else {
                                    /** @var \Symfony\Component\HttpFoundation\StreamedResponse $result */
                                    $result = \ServiceManager::handleRequest(
                                        $serviceName,
                                        Verbs::GET,
                                        $storagePath,
                                        ['include_properties' => 1, 'content' => 1]
                                    );

                                    $remoteContent = base64_decode(array_get($result->getContent(), 'content'));
                                }

                                $model->update(['content' => $remoteContent]);

                                return $remoteContent;
                            } catch (\Exception $e) {
                                \Log::error('Failed to fetch remote script. ' . $e->getMessage());
                                \Cache::forget($cacheKey);

                                return null;
                            }
                        }
                    );

                    $model->content = Session::translateLookups($remoteContent, true);
                }

                if (!is_array($model->config)) {
                    $model->config = [];
                }

                return $model;
            }
        } catch (\Exception $ex) {
            \Log::error('Error occurred while loading event script. ', $ex->getMessage());
        }

        return null;
    }

    /**
     * @param EventScript $script
     * @param array       $event
     *
     * @return array|null
     * @throws \DreamFactory\Core\Script\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    public function handleEventScript($script, &$event)
    {
        $result = $this->handleScript($script->name, $script->content, $script->type, $script->config, $event);

        return $result;
    }

    /**
     * Handle queueable service events.
     *
     * @param BaseEventScriptEvent $event
     *
     * @return boolean
     */
    public function handleEventScriptEvent($event)
    {
        \Cache::forget('event_script:' . $event->script->name);

        return true;
    }

    /**
     * @param EventScript $script
     * @param             $result
     *
     * @return bool
     */
    protected function handleEventScriptResult(
        /** @noinspection PhpUnusedParameterInspection */
        $script,
        $result
    ){
        if (array_get($result, 'stop_propagation', false)) {
            Log::info('  * Propagation stopped by script.');

            return false;
        }

        return true;
    }
}
