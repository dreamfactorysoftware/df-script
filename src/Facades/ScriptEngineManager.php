<?php

namespace DreamFactory\Core\Script\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DreamFactory\Core\Script\Components\ScriptEngineManager
 * @see \DreamFactory\Core\Script\Contracts\ScriptEngineInterface
 */
class ScriptEngineManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'df.script';
    }
}
