<?php
namespace DreamFactory\Core\Script\Enums;

use DreamFactory\Core\Enums\FactoryEnum;

/**
 * ScriptLanguages
 * Supported and future DreamFactory scripting languages
 */
class ScriptLanguages extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    const __default = self::PHP;

    /**
     * @var string
     */
    const NODEJS = 'nodejs';
    /**
     * @var string
     */
    const LUA = 'lua';
    /**
     * @var string
     */
    const PYTHON = 'py';
    /**
     * @var string
     */
    const PYTHON3 = 'py';
    /**
     * @var string
     */
    const PHP = 'php';
    /**
     * @var string
     */
    const RUBY = 'rb';
}