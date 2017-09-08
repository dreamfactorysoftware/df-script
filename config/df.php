<?php

return [
    'scripting' => [
        // 'all' to disable all scripting, or comma-delimited list of v8js, nodejs, python, and/or php
        'disable'     => env('DF_SCRIPTING_DISABLE'),
        // path to the installed nodejs executable
        'nodejs_path' => env('DF_NODEJS_PATH'),
        // path to the installed python executable
        'python_path' => env('DF_PYTHON_PATH'),
        // protocol to use for Node.js and Python when making internal calls back to DreamFactory
        'default_protocol' => env('DF_SCRIPTING_DEFAULT_PROTOCOL', 'http'), // http or https
    ],
];
