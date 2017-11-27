<?php

namespace DreamFactory\Core\Script\Services;

use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Script\Components\ScriptHandler;
use DreamFactory\Core\Script\Jobs\ScriptServiceJob;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Log;

/**
 * Script
 * Scripting as a Service
 */
class Script extends BaseRestService
{
    use ScriptHandler, DispatchesJobs;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string $content Content of the script
     */
    protected $content;
    /**
     * @var string $engineType Type of the script
     */
    protected $engineType;
    /**
     * @var array $scriptConfig Configuration for the engine for this particular script
     */
    protected $scriptConfig;
    /**
     * @var boolean $queued Configuration for the engine for this particular script
     */
    protected $queued = false;
    /**
     * @type bool
     */
    protected $implementsAccessList = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new Script Service
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = [])
    {
        // parent handles the lookup replacement, don't want that yet for script content
        $this->content = array_get($settings, 'config.content');

        parent::__construct($settings);

        $this->queued = array_get_bool($this->config, 'queued');

        if (empty($this->engineType = array_get($this->config, 'type'))) {
            throw new \InvalidArgumentException('Script engine configuration can not be empty.');
        }

        if (!is_array($this->scriptConfig = array_get($this->config, 'config', []))) {
            $this->scriptConfig = [];
        }

        $this->implementsAccessList = array_get_bool($this->config, 'implements_access_list');
    }

    /**
     * @return null|array
     */
    public function getScriptConfig()
    {
        return $this->scriptConfig;
    }

    /**
     * @return bool|mixed|string
     */
    public function getScriptContent()
    {
        $cacheKey = 'script_content';

        if (empty($content = $this->getFromCache($cacheKey, ''))) {
            $storageServiceId = array_get($this->config, 'storage_service_id');
            $storagePath = trim(array_get($this->config, 'storage_path'), '/');
            $scmRepo = array_get($this->config, 'scm_repository');
            $scmRef = array_get($this->config, 'scm_reference');

            $content = strval($this->content);
            if (!empty($storageServiceId) && !empty($storagePath)) {
                try {
                    $service = \ServiceManager::getServiceById($storageServiceId);
                    $serviceName = $service->getName();
                    $typeGroup = $service->getServiceTypeInfo()->getGroup();

                    if ($typeGroup === ServiceTypeGroups::SCM) {
                        $result = \ServiceManager::handleRequest(
                            $serviceName,
                            Verbs::GET,
                            '_repo/' . $scmRepo,
                            ['path' => $storagePath, 'branch' => $scmRef, 'content' => 1]
                        );
                        $content = $result->getContent();
                    } else {
                        $result = \ServiceManager::handleRequest(
                            $serviceName,
                            Verbs::GET,
                            $storagePath,
                            ['include_properties' => 1, 'content' => 1]
                        );
                        $content = base64_decode(array_get($result->getContent(), 'content'));
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to fetch remote script. ' . $e->getMessage());
                    $content = '';
                }
            }

            $this->addToCache($cacheKey, $content, true);
        }

        return $content;
    }

    /**
     * Returns all request data.
     *
     * @return array
     */
    protected function getRequestData()
    {
        return [
            'request'  => $this->request->toArray(),
            'response' => [
                'content'      => null,
                'content_type' => null,
                'status_code'  => ServiceResponseInterface::HTTP_OK
            ],
            'resource' => $this->resourcePath
        ];
    }

    public function getAccessList()
    {
        $list = parent::getAccessList();

        $paths = array_keys((array)array_get($this->getApiDoc(), 'paths'));
        foreach ($paths as $path) {
            // drop service from path
            if (!empty($path = ltrim(strstr(ltrim($path, '/'), '/'), '/'))) {
                $list[] = $path;
                $path = explode("/", $path);
                end($path);
                while ($level = prev($path)) {
                    $list[] = $level . '/*';
                }
            }
        }

        natsort($list);

        return array_values(array_unique($list));
    }

    /**
     * @return bool|mixed
     * @throws
     * @throws \DreamFactory\Core\Script\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    protected function processRequest()
    {
        if (!$this->implementsAccessList &&
            (Verbs::GET === $this->action) &&
            ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST))
        ) {
            $result = ResourcesWrapper::wrapResources($this->getAccessList());

            return ResponseFactory::create($result);
        }

        if ($this->queued) {
            $job = new ScriptServiceJob($this->getServiceId(), $this->request, $this->resourcePath,
                $this->scriptConfig);
            $result = $this->dispatch($job);
            Log::debug('API service script queued: ' . $this->name . PHP_EOL . $result);

            return ResponseFactory::create(['success' => true], null, HttpStatusCodeInterface::HTTP_ACCEPTED);
        }

        $data = $this->getRequestData();

        $logOutput = $this->request->getParameterAsBool('log_output', true);
        $result = $this->handleScript('service.' . $this->name, $this->getScriptContent(), $this->engineType,
            $this->scriptConfig, $data, $logOutput);

        if (is_array($result) && array_key_exists('response', $result)) {
            $result = array_get($result, 'response', []);
        }

        if (is_array($result) && array_key_exists('content', $result)) {
            $content = array_get($result, 'content');
            $contentType = array_get($result, 'content_type');
            $status = array_get($result, 'status_code', HttpStatusCodeInterface::HTTP_OK);
            $headers = (array)array_get($result, 'headers');

            return ResponseFactory::create($content, $contentType, $status, $headers);
        }

        // otherwise assume raw content
        return ResponseFactory::create($result);
    }

    protected function getEventName()
    {
        if (!empty($this->resourcePath) && (null !== $match = $this->matchEventPath($this->resourcePath))) {
            return parent::getEventName() . '.' . $match['path'];
        }

        return parent::getEventName();
    }

    protected function getEventResource()
    {
        if (!empty($this->resourcePath) && (null !== $match = $this->matchEventPath($this->resourcePath))) {
            return $match['resource'];
        }

        return parent::getEventResource();
    }

    protected function matchEventPath($search)
    {
        $paths = array_keys((array)array_get($this->getApiDoc(), 'paths'));
        $pieces = explode('/', $search);
        foreach ($paths as $path) {
            // drop service from path
            $path = trim($path, '/');
            $pathPieces = explode('/', $path);
            if (count($pieces) === count($pathPieces)) {
                if (empty($diffs = array_diff($pathPieces, $pieces))) {
                    return ['path' => str_replace('/', '.', trim($path, '/')), 'resource' => null];
                }

                $resources = [];
                foreach ($diffs as $ndx => $diff) {
                    if (0 !== strpos($diff, '{')) {
                        // not a replacement parameters, see if another path works
                        continue 2;
                    }

                    $resources[$diff] = $pieces[$ndx];
                }

                return ['path' => str_replace('/', '.', trim($path, '/')), 'resource' => $resources];
            }
        }

        return null;
    }

    public function getApiDocInfo()
    {
        return ['paths' => [], 'components' => []];
    }
}
