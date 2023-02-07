<?php

namespace DreamFactory\Core\Script\Services;

use Config;
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
use DreamFactory\Core\Utility\Session;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Log;
use Illuminate\Support\Arr;

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
     * @type bool
     */
    protected $cacheEnabled = false;
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
        $this->content = Arr::get($settings, 'config.content');

        parent::__construct($settings);

        $this->queued = array_get_bool($this->config, 'queued');

        if (empty($this->engineType = Arr::get($this->config, 'type'))) {
            throw new \InvalidArgumentException('Script engine configuration can not be empty.');
        }

        if (!is_array($this->scriptConfig = Arr::get($this->config, 'config', []))) {
            $this->scriptConfig = [];
        }

        $this->cacheEnabled = array_get_bool($this->config, 'cache_enabled');
        $this->cacheTTL = intval(Arr::get($this->config, 'cache_ttl', Config::get('df.default_cache_ttl')));

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
            $storageServiceId = Arr::get($this->config, 'storage_service_id');
            $storagePath = trim(Arr::get($this->config, 'storage_path'), '/');
            $scmRepo = Arr::get($this->config, 'scm_repository');
            $scmRef = Arr::get($this->config, 'scm_reference');

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
                        $content = base64_decode(Arr::get($result->getContent(), 'content'));
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

        $paths = array_keys((array)Arr::get($this->getApiDoc(), 'paths'));
        foreach ($paths as $path) {
            // drop service from path
            if (!empty($path = ltrim($path, '/'))) {
                $list[] = $path;
                $path = explode('/', $path);
                end($path);
                while ($level = prev($path)) {
                    $list[] = $level . '/*';
                }
            }
        }

        natsort($list);

        return array_values(array_unique($list));
    }

    protected function buildRequestCacheKey()
    {
        // build cache_key
        $cacheKey = $this->action;
        $resource = array_map('rawurlencode', $this->resourceArray);
        if ($resource) {
            $cacheKey .= ':' . implode('.', $resource);
        }

        $cacheQuery = '';
        // Using raw query string here to allow for multiple parameters with the same key name.
        // The laravel Request object or PHP global array $_GET doesn't allow that.
        $requestQuery = explode('&', Arr::get($_SERVER, 'QUERY_STRING'));

        // If request is coming from a scripted service then $_SERVER['QUERY_STRING'] will be blank.
        // Therefore need to check the Request object for parameters.
        foreach ($this->request->getParameters() as $pk => $pv) {
            if (is_array($pv)) {
                foreach ($pv as $ipk => $ipv) {
                    $param = $pk . '[' . $ipk . ']=' . $ipv;
                    if (!in_array($param, $requestQuery)) {
                        $requestQuery[] = $param;
                    }
                }
            } else {
                $param = $pk . '=' . $pv;
                if (!in_array($param, $requestQuery)) {
                    $requestQuery[] = $param;
                }
            }
        }

        foreach ($requestQuery as $q) {
            $pairs = explode('=', $q);
            $name = trim(Arr::get($pairs, 0));
            $value = trim(Arr::get($pairs, 1));
            static::parseParameter($cacheQuery, $name, $value);
        }

        if (!empty($cacheQuery)) {
            $cacheKey .= ':' . $cacheQuery;
        }

        return sha1($cacheKey); // the key may contain confidential info
    }

    protected static function parseParameter(&$key, $name, $value)
    {
        if ('_' !== $name) { // this value included with jQuery calls should not be considered
            if (is_array($value)) {
                foreach ($value as $sub => $subValue) {
                    static::parseParameter($key, $name . '[' . $sub . ']', $subValue);
                }
            } else {
                Session::replaceLookups($value, true);
                $part = $name;
                if (!empty($value)) {
                    $part .= '=' . $value;
                }
                if (!empty($key)) {
                    $key .= '&';
                }
                $key .= $part;
            }
        }
    }

    /**
     * @return bool|mixed
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     * @throws \DreamFactory\Core\Script\Exceptions\ScriptException
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

        $cacheKey = null;
        if ($this->cacheEnabled) {
            switch ($this->action) {
                case Verbs::GET:
                    // build cache_key
                    $cacheKey = $this->buildRequestCacheKey();
                    if (null !== $result = $this->getFromCache($cacheKey)) {
                        return $result;
                    }
                    break;
            }
        }

        $logOutput = $this->request->getParameterAsBool('log_output', true);
        $result = $this->handleScript('service.' . $this->name, $this->getScriptContent(), $this->engineType,
            $this->scriptConfig, $data, $logOutput);

        if (is_array($result) && array_key_exists('response', $result)) {
            $result = Arr::get($result, 'response', []);
        }

        if (is_array($result) && array_key_exists('content', $result)) {
            $content = Arr::get($result, 'content');
            $contentType = Arr::get($result, 'content_type');
            $status = Arr::get($result, 'status_code', HttpStatusCodeInterface::HTTP_OK);
            $headers = (array)Arr::get($result, 'headers');

            $result = ResponseFactory::create($content, $contentType, $status, $headers);
        } else {
            // otherwise assume raw content
            $result = ResponseFactory::create($result);
        }

        $status = $result->getStatusCode();
        if ($cacheKey && (($status >= 200) && ($status < 300))) { // do not cache error responses
            $this->addToCache($cacheKey, $result);
        }

        return $result;
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
        $paths = array_keys((array)Arr::get($this->getApiDoc(), 'paths'));
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
                    if (!str_starts_with($diff, '{')) {
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
