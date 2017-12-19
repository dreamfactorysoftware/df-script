<?php
namespace DreamFactory\Core\Script\Components;

use Cache;
use Config;
use DreamFactory\Core\Components\ExceptionResponse;
use DreamFactory\Core\Script\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Script\Exceptions\ScriptException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Utility\Curl;
use DreamFactory\Core\Enums\Verbs;
use Illuminate\Support\Arr;
use ServiceManager;

/**
 * Allows platform access to a scripting engine
 */
abstract class BaseEngineAdapter implements ScriptingEngineInterface
{
    use ExceptionResponse;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The default cache ttl, 5m = 300s
     */
    const DEFAULT_CACHE_TTL = 300;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array A list of paths where our scripts might live
     */
    protected static $libraryPaths;
    /**
     * @var array The list of registered/known libraries
     */
    protected static $libraries = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     *
     */
    public function __construct(array $settings = [])
    {
    }

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return void
     * @throws ServiceUnavailableException
     */
    public static function startup($options = null)
    {
        static::initializeLibraryPaths(array_get($options, 'library_paths', []));
    }

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return void
     */
    public static function shutdown()
    {
        Cache::add('scripting.library_paths', static::$libraryPaths, static::DEFAULT_CACHE_TTL);
        Cache::add('scripting.libraries', static::$libraries, static::DEFAULT_CACHE_TTL);
    }

    /**
     * Process a single script
     *
     * @param string $path            The path/to/the/script to read and execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    abstract public function executeScript($path, $identifier, array &$data = [], array $engineArguments = []);

    /**
     * Process a single script
     *
     * @param string $path            The path/to/the/script to read and execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    abstract public function executeString($path, $identifier, array &$data = [], array $engineArguments = []);

    /**
     * @param string $script     The script to run or a script file name
     * @param string $identifier The name of this script
     * @param array  $config     The config for this particular script
     * @param array  $data       The additional data as it will be exposed to script
     * @param string $output     Any output of the script
     *
     * @return array
     * @throws ScriptException
     */
    public function runScript(
        $script,
        $identifier,
        array $config = [],
        array &$data = [],
        &$output = null
    ){
        $result = $message = false;

        try {
            //  Don't show output
            ob_start();

            if (strpos($script, "\n") === false && is_file($script)) {
                $result = $this->executeScript($script, $identifier, $data, $config);
            } else {
                $result = $this->executeString($script, $identifier, $data, $config);
            }
        } catch (ScriptException $ex) {
            $message = $ex->getMessage();

            \Log::error($message = "Exception executing script: $message");
        }

        //  Clean up
        $output = ob_get_clean();

        if (boolval(\Config::get('df.log_script_memory_usage', false))) {
            \Log::debug('Engine memory usage: ' . static::resizeBytes(memory_get_usage(true)));
        }

        if (false !== $message) {
            throw new ScriptException($message, $output);
        }

        return $result;
    }

    /**
     * Look through the known paths for a particular script. Returns full path to script file.
     *
     * @param string $name           The name/id of the script
     * @param string $path           The name of the script
     * @param bool   $returnContents If true, the contents of the file, if found, are returned. Otherwise, the only the
     *                               path is returned
     *
     * @return string
     */
    public static function loadScript($name, $path = null, $returnContents = true)
    {
        //  Already read, return script
        if (null !== ($script = array_get(static::$libraries, $name))) {
            return $returnContents ? file_get_contents($script) : $script;
        }

        $script = ltrim($script, ' /');

        //  Spin through paths and look for the script
        foreach (static::$libraryPaths as $libPath) {
            $check = $libPath . '/' . $script;

            if (is_file($check) && is_readable($check)) {
                array_set(static::$libraries, $name, $check);

                return $returnContents ? file_get_contents($check) : $check;
            }
        }

        if ($path) {
            if (is_file($path) && is_readable($path)) {
                array_set(static::$libraries, $name, $path);

                return $returnContents ? file_get_contents($path) : $path;
            }
        }

        return false;
    }

    /**
     * @param array $libraryPaths
     *
     */
    protected static function initializeLibraryPaths($libraryPaths = null)
    {
        static::$libraryPaths = Cache::get('scripting.library_paths', []);
        static::$libraries = Cache::get('scripting.libraries', []);

        //  Add ones from constructor
        $libraryPaths = (is_array($libraryPaths) ? $libraryPaths : []);

        //  Application storage script path
        $libraryPaths[] = storage_path('scripting');

        //  Merge in config libraries...
        $configPaths = \Config::get('df.scripting.paths', []);
        $configPaths = (is_array($configPaths) ? $configPaths : []);
        $libraryPaths = array_merge($libraryPaths, $configPaths);

        //  Add them to collection if valid
        if (is_array($libraryPaths)) {
            foreach ($libraryPaths as $path) {
                if (!in_array($path, static::$libraryPaths)) {
                    if (!empty($path) || is_dir($path) || is_readable($path)) {
                        static::$libraryPaths[] = $path;
                    } else {
                        \Log::debug("Invalid scripting library path given $path.");
                    }
                }
            }
        }

        \Cache::add('scripting.library_paths', static::$libraryPaths, static::DEFAULT_CACHE_TTL);

        if (empty(static::$libraryPaths)) {
            \Log::debug('No scripting library paths found.');
        }
    }

    /**
     * @return array
     */
    public static function getLibraries()
    {
        return static::$libraries;
    }

    /**
     * @return array
     */
    public static function getLibraryPaths()
    {
        return static::$libraryPaths;
    }

    /**
     * @param string $libraryPath An absolute path to a script library
     */
    public static function addLibraryPath($libraryPath)
    {
        if (!is_dir($libraryPath) || !is_readable($libraryPath)) {
            throw new \InvalidArgumentException('The path "' . $libraryPath . '" is invalid.');
        }

        if (!in_array($libraryPath, static::$libraryPaths)) {
            static::$libraryPaths[] = $libraryPath;
        }
    }

    /**
     * @param string $name   The name/id of this script
     * @param string $script The file for this script
     */
    public static function addLibrary($name, $script)
    {
        if (false === ($path = static::loadScript($name, $script, false))) {
            throw new \InvalidArgumentException('The script "' . $script . '" was not found.');
        }
    }

    /**
     * Locates and loads a library returning the contents
     *
     * @param string $id   The id of the library (i.e. "lodash", "underscore", etc.)
     * @param string $file The relative path/name of the library file
     *
     * @return string
     */
    protected static function getLibrary($id, $file = null)
    {
        if (null !== $file || array_key_exists($id, static::$libraries)) {
            $file = $file ?: static::$libraries[$id];

            //  Find the library
            foreach (static::$libraryPaths as $name => $path) {
                $filePath = $path . DIRECTORY_SEPARATOR . $file;

                if (file_exists($filePath) && is_readable($filePath)) {
                    return file_get_contents($filePath, 'r');
                }
            }
        }

        throw new \InvalidArgumentException('The library id "' . $id . '" could not be located.');
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed  $payload
     * @param array  $curlOptions
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    protected static function externalRequest($method, $url, $payload = [], $curlOptions = [])
    {
        if (!empty($parameters = (array)array_get($curlOptions, 'parameters'))) {
            unset($curlOptions['parameters']);
            $paramStr = '';
            foreach ($parameters as $key => $value) {
                if (!empty($paramStr)) {
                    $paramStr .= '&';
                }
                $paramStr .= $key . '=' . urlencode(strval($value));
            }
            $url .= (strpos($url, '?') ? '&' : '?') . $paramStr;
        }

        if (!empty($headers = (array)array_get($curlOptions, 'headers'))) {
            unset($curlOptions['headers']);
            $curlHeaders = [];
            if (!Arr::isAssoc($headers)) {
                $curlHeaders = $headers;
            } else {
                foreach ($headers as $key => $value) {
                    $curlHeaders[] = "$key: $value";
                }
            }

            $existing = (array)array_get($curlOptions, CURLOPT_HTTPHEADER);
            if (array_key_exists('CURLOPT_HTTPHEADER', $curlOptions)) {
                $existing = array_merge($existing, (array)$curlOptions['CURLOPT_HTTPHEADER']);
                unset($curlOptions['CURLOPT_HTTPHEADER']);
            }
            $curlOptions[CURLOPT_HTTPHEADER] = array_merge($existing, $curlHeaders);
        }

        if (!empty($curlOptions)) {
            $badKeys = [];
            foreach ($curlOptions as $key => $value) {
                if (!is_numeric($key)) {
                    if (defined($key)) {
                        $badKeys[] = $key;
                        $curlOptions[constant($key)] = $value;
                    }
                }
            }
            $curlOptions = array_except($curlOptions, $badKeys);
        }

        Curl::setDecodeToArray(true);
        $result = Curl::request($method, $url, $payload, $curlOptions);
        $contentType = Curl::getInfo('content_type');
        $status = Curl::getLastHttpCode();
        if ($status >= 300) {
            if (!is_string($result)) {
                $result = json_encode($result);
            }

            throw new RestException($status, $result, $status);
        }

        $resultHeaders = Curl::getLastResponseHeaders();
        if ('chunked' === array_get(array_change_key_case($resultHeaders, CASE_LOWER), 'transfer-encoding')) {
            // don't relay this header through to client as it isn't handled well in some cases
            unset($resultHeaders['Transfer-Encoding']); // normal header case
            unset($resultHeaders['transfer-encoding']); // Restlet has all lower for this header
        }

        return ResponseFactory::create($result, $contentType, $status, $resultHeaders);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $payload
     * @param array  $curlOptions Additional CURL options for external requests
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public static function inlineRequest($method, $path, $payload = null, $curlOptions = [])
    {
        if (null === $payload || 'null' == $payload) {
            $payload = [];
        }

        try {
            if ('https:/' == ($protocol = substr($path, 0, 7)) || 'http://' == $protocol) {
                $result = static::externalRequest($method, $path, $payload, $curlOptions);
            } else {
                $result = null;
                $params = (array)array_get($curlOptions, 'parameters');
                $headers = (array)array_get($curlOptions, 'headers');
                if (false !== $pos = strpos($path, '?')) {
                    $paramString = substr($path, $pos + 1);
                    if (!empty($paramString)) {
                        $pArray = explode('&', $paramString);
                        foreach ($pArray as $k => $p) {
                            if (!empty($p)) {
                                $tmp = explode('=', $p);
                                $name = array_get($tmp, 0, $k);
                                $value = array_get($tmp, 1);
                                $params[$name] = urldecode($value);
                            }
                        }
                    }
                    $path = substr($path, 0, $pos);
                }

                if (false === ($pos = strpos($path, '/'))) {
                    $serviceName = $path;
                    $resource = null;
                } else {
                    $serviceName = substr($path, 0, $pos);
                    $resource = substr($path, $pos + 1);

                    //	Fix removal of trailing slashes from resource
                    if (!empty($resource)) {
                        if ((false === strpos($path, '?') && '/' === substr($path, strlen($path) - 1, 1)) ||
                            ('/' === substr($path, strpos($path, '?') - 1, 1))
                        ) {
                            $resource .= '/';
                        }
                    }
                }

                if (empty($serviceName)) {
                    return null;
                }

                $format = DataFormats::PHP_ARRAY;
                if (!is_array($payload)) {
                    $format = DataFormats::TEXT;
                }

                $request = new ScriptServiceRequest($method, $params, $headers);
                $request->setContent($payload, $format);

                //  Now set the request object and go...
                $result = ServiceManager::handleServiceRequest($request, $serviceName, $resource);
            }
        } catch (\Exception $ex) {
            $result = static::exceptionToServiceResponse($ex);

            \Log::error('Exception: ' . $ex->getMessage(), ['response' => $result]);
        }

        static::formatResult($result);

        return ResponseFactory::sendScriptResponse($result);
    }

    protected static function formatResult(&$result)
    {
        // nothing special here
    }

    /**
     * @return \stdClass
     */
    protected static function getExposedApi()
    {
        static $api;

        if (null !== $api) {
            return $api;
        }

        $api = new \stdClass();

        $api->call = function ($method, $path, $payload = null, $curlOptions = []){
            return static::inlineRequest($method, $path, $payload, $curlOptions);
        };

        $api->get = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::GET, $path, $payload, $curlOptions);
        };

        $api->post = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::POST, $path, $payload, $curlOptions);
        };

        $api->put = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::PUT, $path, $payload, $curlOptions);
        };

        $api->patch = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::PATCH, $path, $payload, $curlOptions);
        };

        $api->delete = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::DELETE, $path, $payload, $curlOptions);
        };

        return $api;
    }

    public static function buildPlatformAccess($identifier)
    {
        return [
            'api'     => static::getExposedApi(),
            'config'  => Config::get('df'),
            'session' => Session::all(),
            'store'   => new ScriptSession(Config::get("script.$identifier.store"), app('cache'))
        ];
    }

    /**
     * Converts single bytes into proper form (kb, gb, mb, etc.) with precision 2 (i.e. 102400 > 100.00kb)
     * Found on php.net's memory_usage page
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function resizeBytes($bytes)
    {
        static $units = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        /** @noinspection PhpIllegalArrayKeyTypeInspection */

        return @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), 2) . $units[$i];
    }

    /**
     * Json Encodes an array safely by base64 encoding
     * strings (binary) that cannot be json encoded.
     *
     * @param array $data
     * @param bool  $base64
     *
     * @return string
     */
    protected function safeJsonEncode(array $data, $base64 = true)
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->makeJsonSafe($value, $base64);
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Base64 encodes any string or array of strings that
     * cannot be JSON encoded.
     *
     * @param string|array $data
     * @param bool         $base64
     *
     * @return array|string
     */
    protected function makeJsonSafe($data, $base64 = true)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->makeJsonSafe($value, $base64);
            }
        }
        if (!$this->isJsonEncodable($data)) {
            if (true === $base64) {
                return 'base64:' . base64_encode($data);
            } else {
                return '--non-parsable-data--';
            }
        }

        return $data;
    }

    /**
     * Checks to see if a string can be json encoded.
     *
     * @param string $data
     *
     * @return bool
     */
    protected function isJsonEncodable($data)
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return true;
    }
}