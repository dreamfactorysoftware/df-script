<?php
namespace DreamFactory\Core\Script\Engines;

use DreamFactory\Core\Script\Components\BaseEngineAdapter;
use DreamFactory\Core\Script\Exceptions\ScriptException;
use \Log;

/**
 * Wrapper around the php extension
 */
class Php extends BaseEngineAdapter
{
    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return void
     */
    public static function startup($options = null)
    {
        // TODO: Implement startup() method.
    }

    /**
     * Process a single script
     *
     * @param string $script          The string to execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @internal param string $eventName
     * @internal param \DreamFactory\Core\Events\PlatformEvent $event
     * @internal param \DreamFactory\Core\Events\EventDispatcher $dispatcher
     * @return mixed
     * @throws \Exception
     */
    public function executeString($script, $identifier, array &$data = [], array $engineArguments = [])
    {
        $data['__tag__'] = 'exposed_event';

        /** @noinspection PhpUnusedLocalVariableInspection */
        $platform = static::buildPlatformAccess($identifier);
        $event = &$data;

        // Line numbers in errors refer to the script as written: count the lines in front of
        // it in the wrapper, less the blank lines stripPhpTag() trims from its start.
        $script = (string)$script;
        $lineCount = substr_count($script, "\n") + 1;
        $lineOffset = $this->scriptLineOffset() -
            substr_count(substr($script, 0, strspn($script, " \t\n\r\0\x0B")), "\n");

        // todo Look for a better way!
        $script = static::stripPhpTag($script);
        $enrobedScript = $this->enrobeScript($script);
        try {
            $event = @eval($enrobedScript);
        } catch (\Error $ex) {
            // A parse error in the script, or an error the script did not catch, is reported
            // like any other script failure. The error itself is kept for the log.
            throw new ScriptException($this->describeError($ex, $lineOffset, $lineCount), null, null, $ex);
        }
        if (false === $event){
            $error = error_get_last();
            $message = (isset($error['message']) ? $error['message']: null);
            Log::error("Exception executing PHP script: $message");
            return null;
        }

        return $event;
    }

    /**
     * Describes an error raised while running a script, with the line of the script it
     * comes from. Parse errors keep the parser's message. Other errors are described by
     * their type and line, plus their message when app.debug is on. The error itself is
     * logged by runScript().
     *
     * @param \Error $error
     * @param int    $lineOffset Lines in front of the script in the evaluated code
     * @param int    $lineCount  Number of lines in the script
     *
     * @return string
     */
    protected function describeError(\Error $error, $lineOffset, $lineCount)
    {
        $toScriptLine = function ($line) use ($lineOffset, $lineCount) {
            return max(1, min($lineCount, (int)$line - $lineOffset));
        };

        $line = null;
        if ($this->isScriptCode($error->getFile())) {
            $line = $error->getLine();
        } else {
            // raised outside the script, e.g. in a function it called: use the calling line
            foreach ($error->getTrace() as $frame) {
                if (isset($frame['file'], $frame['line']) && $this->isScriptCode($frame['file'])) {
                    $line = $frame['line'];
                    break;
                }
            }
        }
        $where = (null === $line) ? '' : ' on line ' . $toScriptLine($line);

        if ($error instanceof \ParseError && $this->isScriptCode($error->getFile())) {
            $message = preg_replace_callback(
                '/\bon line (\d+)/',
                function ($match) use ($toScriptLine) {
                    return 'on line ' . $toScriptLine($match[1]);
                },
                $error->getMessage()
            );

            return "Parse error$where: $message";
        }

        $type = (new \ReflectionClass($error))->isInternal() ? get_class($error) : 'Error';
        $description = $type . $where;
        if (static::isDebugEnabled()) {
            $description .= ': ' . $error->getMessage();
        }

        return $description;
    }

    /**
     * @param string $file
     *
     * @return bool True when $file names the code evaluated by executeString()
     */
    protected function isScriptCode($file)
    {
        return str_starts_with((string)$file, __FILE__ . '(') && str_ends_with((string)$file, "eval()'d code");
    }

    /**
     * @return int Number of lines enrobeScript() puts in front of the script
     */
    protected function scriptLineOffset()
    {
        $marker = "\0";

        return substr_count((string)strstr($this->enrobeScript($marker), $marker, true), "\n");
    }

    /**
     * @return bool
     */
    protected static function isDebugEnabled()
    {
        try {
            return (bool)config('app.debug', false);
        } catch (\Throwable $ex) {
            return false;
        }
    }

    /**
     * Removes any <?PHP tags.
     *
     * @param $script
     *
     * @return mixed|string
     */
    protected static function stripPhpTag($script)
    {
        $script = trim($script);
        $tagOpen = strtolower(substr($script, 0, 5));
        $tagClose = substr($script, strlen($script)-2);

        if('<?php' === $tagOpen){
            $script = substr($script, 5);
        }
        if('?>' === $tagClose){
            $script = substr($script, 0, (strlen($script)-2));
        }

        return $script;
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
    public function executeScript($path, $identifier, array &$data = [], array $engineArguments = [])
    {
        return $this->executeString(file_get_contents($path), $identifier, $data, $engineArguments);
    }

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return void
     */
    public static function shutdown()
    {
        // TODO: Implement shutdown() method.
    }

    /**
     * @param string $script
     *
     * @return string
     */
    protected function enrobeScript($script)
    {
        $enrobedScript = <<<'PHP'

    try {
        $closure = function() use (&$event, $platform) {

PHP;
        $enrobedScript .= "$script";

        $enrobedScript .= <<<'PHP'

    	};

        $event['script_result'] = $closure();
	}
	catch ( \Exception $ex ) {
		$event['script_result'] = ['error' => $ex->getMessage()];
		$event['exception'] = $ex;
	}

	return $event;
PHP;

        return $enrobedScript;
    }

    protected function safe_eval($code, &$status)
    { //status 0=failed,1=all clear
        //Signs
        //Can't assign stuff
        $bl_signs = ["="];

        //Language constructs
        $bl_constructs = [
            "print",
            "echo",
            "require",
            "include",
            "if",
            "else",
            "while",
            "for",
            "switch",
            "exit",
            "break"
        ];

        //Functions
        $funcs = get_defined_functions();
        $funcs = array_merge($funcs['internal'], $funcs['user']);

        //Functions allowed
        //Math cant be evil, can it?
        $whitelist = ["pow", "exp", "abs", "sin", "cos", "tan"];

        //Remove whitelist elements
        foreach ($whitelist as $f) {
            unset($funcs[array_search($f, $funcs)]);
        }
        //Append '(' to prevent confusion (e.g. array() and array_fill())
        foreach ($funcs as $key => $val) {
            $funcs[$key] = $val . "(";
        }
        $blacklist = array_merge($bl_signs, $bl_constructs, $funcs);

        //Check
        $status = 1;
        foreach ($blacklist as $nono) {
            if (strpos($code, $nono) !== false) {
                $status = 0;

                return 0;
            }
        }

        //Eval
        return @eval($code);
    }

}
