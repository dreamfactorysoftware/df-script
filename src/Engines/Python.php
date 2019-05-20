<?php

namespace DreamFactory\Core\Script\Engines;

use Cache;

class Python extends ExecutedEngine
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    public function __construct(array $settings = [])
    {
        if (!isset($settings['command_name'])) {
            $settings['command_name'] = 'python';
        }
        if (!isset($settings['command_path'])) {
            $settings['command_path'] = config('df.scripting.python_path');
        }
        if (!isset($settings['file_extension'])) {
            $settings['file_extension'] = 'py';
        }
        if (!isset($settings['supports_inline_execution'])) {
            $settings['supports_inline_execution'] = true;
            $settings['inline_arguments'] = '-c';
        }

        parent::__construct($settings);
    }

    protected function enrobeScript($script, array &$data = [], array $platform = [])
    {
        $jsonEvent = $this->safeJsonEncode($data, false);
        $jsonPlatform = json_encode($platform, JSON_UNESCAPED_SLASHES);
        
        $jsonPlatform = str_replace(
                [
                    ":null,", ":null",
                    ":false,", ":false",
                    ":true,", ":true"
                ], [
                    ":None,", ":None",
                    ":False,", ":False",
                    ":True,", ":True"
                ],
                $jsonPlatform
            );
    
        $jsonEvent = str_replace(
        [
                ":null,", ":null",
                ":false,", ":false",
                ":true,", ":true"
        ], [
                ":None,", ":None",
                ":False,", ":False",
                ":True,", ":True"
        ],
        $jsonEvent
        );

        $protocol = config('df.scripting.default_protocol', 'http');
        $https = array_get($_SERVER, 'HTTPS');
        if ((!empty($https) && ('off' != $https)) || (443 == array_get($_SERVER, 'SERVER_PORT'))) {
            $protocol = 'https';
        }
        $token = uniqid();
        $tokenCache = [
            'app_id'  => array_get($platform, 'session.app.id'),
            'user_id' => array_get($platform, 'session.user.id')
        ];
        Cache::add('script-token:'.$token, $tokenCache, 5); // script should not take longer than 5 minutes to run

        if (empty($script)) {
            $script = 'pass;';
        }
        $scriptLines = explode("\n", $script);

        $enrobedScript = <<<python
import httplib, json;
from bunch import bunchify, unbunchify;

eventJson = $jsonEvent;
platformJson = $jsonPlatform;

_event = bunchify(eventJson);
_platform = bunchify(platformJson);


__protocol = '$protocol';
__host = _event.request.headers.host;
__headers = {
    'x-dreamfactory-script-token':'$token'
    };

class Api:
        def __init__(self, host, header, protocol):
                self.host = host;
                self.header = header;
                self.protocol = protocol;

        def get(self, path, options=''):
                return self.call('GET', path, '', options);

        def post(self, path, payload='', options=''):
                return self.call('POST', path, payload, options);

        def put(self, path, payload='', options=''):
                return self.call('PUT', path, payload, options);

        def patch(self, path, payload='', options=''):
                return self.call('PATCH', path, payload, options);

        def delete(self, path, payload='', options=''):
                return self.call('DELETE', path, payload, options);

        def call(self, verb, path, payload='', options={}):
                if(type(options) is dict):
                        options = bunchify(options);
                if(options and ('headers' in options)):
                        header = options.headers;
                elif(options and ('parameters' in options)):
                        header = bunchify({});
                elif(options):
                        header = options;
                else:
                        header = bunchify({});

                path = self.cleanPath(path);
                if(options and ('parameters' in options)):
                        for key in options.parameters:
                                if(path.find('?') == -1):
                                        path = path + '?' + str(key) + '=' + str(options.parameters[key]);
                                else:
                                        path = path + '&' + str(key) + '=' + str(options.parameters[key]);
                
                conn = self.getConnection(path);
                if(self.isInternalApi(path)):
                        for key in self.header:
                                header[key] = self.header[key];
                            
                conn.request(verb, path, payload, header);
                response = conn.getresponse();
                return response;

        def getConnection(self, path):
                host = self.getHost(path);
                if(self.getProtocol(path) == 'https'):
                        return httplib.HTTPSConnection(host);
                else:
                        return httplib.HTTPConnection(host);

        def getHost(self, path):
                path = path.strip();
                if(path[0:7] == 'http://'):
                        path = path[7:];
                        return path[0:path.find('/')];
                elif(path[0:8] == 'https://'):          
                        path = path[8:];
                        return path[0:path.find('/')];
                else:
                        return self.host;
            
        def getProtocol(self, path):
                if(path[0:7] == 'http://'):
                        return 'http';
                elif(path[0:8] == 'https://'):
                        return 'https';
                else:
                        return self.protocol;

        def isInternalApi(self, path):
                path = path.strip();
                return False if (path[0:7] == 'http://' or path[0:8] == 'https://') else True;

        def cleanPath(self, path):
                path = path.strip();
                if(self.isInternalApi(path)):
                        if(path[0:1] != '/'):
                                path = '/'+path;
                        if(path[0:8] != '/api/v2/'):
                                path = '/api/v2'+path;

                return path;
		
_platform.api = Api(__host, __headers, __protocol);

try:
    def my_closure(event, platform):
python;
        foreach ($scriptLines as $sl) {
            $enrobedScript .= "\n        " . $sl;
        }

        $enrobedScript .= <<<python

    _event.script_result =  my_closure(_event, _platform);
except Exception as e:
    _event.script_result = {'error':str(e)};
    _event.exception = str(e)

print json.dumps(_event);
python;
        $enrobedScript = trim($enrobedScript);

        return $enrobedScript;
    }

    /** @inheritdoc */
    protected function checkOutputStringForData($output)
    {
        return ((strlen($output) > 10) && (false !== strpos($output, 'request')));
    }
}