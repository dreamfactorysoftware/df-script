<?php

namespace DreamFactory\Core\Script\Tests\Engines;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Script\Components\BaseEngineAdapter;
use DreamFactory\Core\Script\Engines\Php;
use DreamFactory\Core\Script\Engines\Python3;
use DreamFactory\Core\Script\Exceptions\ScriptException;
use Orchestra\Testbench\TestCase;

/**
 * Whatever a script prints is captured by runScript() and handed back through
 * $output; it must never end up in front of the API response, whether the
 * script succeeds or fails.
 */
class ScriptOutputTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    public function testOutputIsReturnedWhenTheScriptSucceeds(): void
    {
        $engine = new OutputProbeEngine(function () {
            echo 'hello';

            return ['ok' => true];
        });
        $level = ob_get_level();

        $data = [];
        $output = null;
        $result = $engine->runScript("line\none", 'test', [], $data, $output);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame('hello', $output);
        $this->assertSame($level, ob_get_level());
    }

    public function testOutputBufferIsClosedWhenTheEngineThrows(): void
    {
        $engine = new OutputProbeEngine(function () {
            echo 'partial output';
            throw new InternalServerErrorException('Executed command returned with error code: 1');
        });
        $level = ob_get_level();

        $data = [];
        $output = null;
        try {
            $engine->runScript("line\none", 'test', [], $data, $output);
            $this->fail('expected the engine exception to propagate');
        } catch (InternalServerErrorException $e) {
            $this->assertStringContainsString('error code: 1', $e->getMessage());
        }

        $this->assertSame($level, ob_get_level(), 'no output buffer may stay open');
        $this->assertSame('partial output', $output);
    }

    public function testScriptExceptionCarriesTheOutput(): void
    {
        $engine = new OutputProbeEngine(function () {
            echo 'before failing';
            throw new ScriptException('boom');
        });
        $level = ob_get_level();

        $data = [];
        try {
            $engine->runScript("line\none", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
            $this->assertSame('before failing', $e->getOutput());
        }

        $this->assertSame($level, ob_get_level());
    }

    public function testBuffersLeftOpenByTheScriptAreClosedInOrder(): void
    {
        $engine = new OutputProbeEngine(function () {
            echo 'outer ';
            ob_start();
            echo 'inner';

            return [];
        });
        $level = ob_get_level();

        $data = [];
        $output = null;
        $engine->runScript("line\none", 'test', [], $data, $output);

        $this->assertSame($level, ob_get_level());
        $this->assertSame('outer inner', $output);
    }

    public function testPhpScriptErrorIsReportedAsScriptException(): void
    {
        $level = ob_get_level();
        $data = ['request' => []];
        $output = null;

        try {
            (new Php())->runScript("echo 'partial';\nthrow new \\Error('script failed');", 'test', [], $data, $output);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertStringContainsString('script failed', $e->getMessage());
        }

        $this->assertSame($level, ob_get_level());
        $this->assertSame('partial', $output);
    }

    public function testPhpParseErrorIsReportedAsScriptException(): void
    {
        $level = ob_get_level();
        $data = ['request' => []];

        try {
            (new Php())->runScript("echo 'x';\nthis is not valid (", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertStringContainsString('syntax error', $e->getMessage());
        }

        $this->assertSame($level, ob_get_level());
    }

    public function testPhpScriptResultIsReturned(): void
    {
        $data = ['request' => ['method' => 'GET']];
        $output = null;

        $result = (new Php())->runScript("echo 'note';\nreturn ['verb' => \$event['request']['method']];", 'test', [], $data, $output);

        $this->assertSame(['verb' => 'GET'], $result['script_result']);
        $this->assertSame('note', $output);
    }

    public function testPython3WrapperDoesNotPrintTheEventOrPlatform(): void
    {
        $engine = new Python3EnrobeProbe(['command_path' => '/bin/true']);
        $data = ['request' => ['method' => 'GET', 'headers' => ['host' => 'localhost']]];

        ob_start();
        $script = $engine->enrobe("return {'ok': True}", $data);
        $printed = ob_get_clean();

        $this->assertSame('', $printed);
        $this->assertStringNotContainsString('print(eventJson)', $script);
        $this->assertStringNotContainsString('print(platformJson)', $script);
        $this->assertStringContainsString('print(json.dumps(_event));', $script);
    }
}

/**
 * Engine whose execution is a closure, so runScript() can be observed without
 * an interpreter.
 */
class OutputProbeEngine extends BaseEngineAdapter
{
    private $run;

    public function __construct(callable $run)
    {
        parent::__construct();
        $this->run = $run;
    }

    public function executeScript($path, $identifier, array &$data = [], array $engineArguments = [])
    {
        return ($this->run)();
    }

    public function executeString($path, $identifier, array &$data = [], array $engineArguments = [])
    {
        return ($this->run)();
    }
}

/**
 * Exposes the Python3 wrapper for inspection.
 */
class Python3EnrobeProbe extends Python3
{
    public static function buildPlatformAccess($identifier)
    {
        return ['session' => ['app' => ['id' => 1], 'user' => ['id' => 1]]];
    }

    public function enrobe($script, array $data)
    {
        return $this->enrobeScript($script, $data, static::buildPlatformAccess('test'));
    }
}
