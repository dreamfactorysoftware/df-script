<?php

namespace DreamFactory\Core\Script\Tests\Engines;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Script\Components\BaseEngineAdapter;
use DreamFactory\Core\Script\Engines\Php;
use DreamFactory\Core\Script\Engines\Python3;
use DreamFactory\Core\Script\Exceptions\ScriptException;
use Illuminate\Support\Facades\Log;
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
            $this->assertSame('Exception executing script: boom', $e->getMessage());
            $this->assertSame('before failing', $e->getOutput());
            $this->assertInstanceOf(ScriptException::class, $e->getPrevious());
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
        $this->app['config']->set('app.debug', false);
        $level = ob_get_level();
        $data = ['request' => []];
        $output = null;

        try {
            (new Php())->runScript("echo 'partial';\nthrow new \\Error('script failed');", 'test', [], $data, $output);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertSame('Exception executing script: Error on line 2', $e->getMessage());
            $this->assertSame('partial', $e->getOutput());
            $error = $e->getPrevious()?->getPrevious();
            $this->assertInstanceOf(\Error::class, $error, 'the original error stays attached');
            $this->assertSame('script failed', $error->getMessage());
        }

        $this->assertSame($level, ob_get_level());
        $this->assertSame('partial', $output);
    }

    public function testPhpScriptErrorMessageIsIncludedWithDebugOn(): void
    {
        $this->app['config']->set('app.debug', true);
        $data = ['request' => []];

        try {
            (new Php())->runScript("\$x = 1;\nthrow new \\Error('script failed');", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertSame('Exception executing script: Error on line 2: script failed', $e->getMessage());
        }
    }

    public function testPhpTypeErrorIsDescribedByTypeAndLine(): void
    {
        $this->app['config']->set('app.debug', false);
        $data = ['request' => []];

        try {
            (new Php())->runScript("\$f = function (int \$x) {\n    return \$x;\n};\nreturn \$f('abc');", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertSame('Exception executing script: TypeError on line 1', $e->getMessage());
            $this->assertInstanceOf(\TypeError::class, $e->getPrevious()?->getPrevious());
        }
    }

    public function testPhpErrorFromCalledCodeNamesTheCallingLine(): void
    {
        $this->app['config']->set('app.debug', false);
        $data = ['request' => []];
        $helper = '\\' . ScriptOutputHelper::class;

        try {
            (new Php())->runScript("\$a = 1;\n\n{$helper}::countOf('not countable');", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertSame('Exception executing script: TypeError on line 3', $e->getMessage());
        }
    }

    public function testPhpErrorIsLoggedWithTheOriginalError(): void
    {
        $this->app['config']->set('app.debug', false);
        Log::spy();
        $data = ['request' => []];

        try {
            (new Php())->runScript("\$x = 1;\nthrow new \\Error('script failed');", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
        }

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []) {
            return 'Exception executing script: Error on line 2' === $message &&
                ($context['exception'] ?? null) instanceof \Error &&
                'script failed' === $context['exception']->getMessage();
        })->once();
    }

    public function testPhpParseErrorIsReportedWithTheScriptLine(): void
    {
        $level = ob_get_level();
        $data = ['request' => []];

        try {
            (new Php())->runScript("echo 'x';\nthis is not valid (", 'test', [], $data);
            $this->fail('expected a ScriptException');
        } catch (ScriptException $e) {
            $this->assertStringStartsWith('Exception executing script: Parse error on line 2: syntax error', $e->getMessage());
            $this->assertInstanceOf(\ParseError::class, $e->getPrevious()?->getPrevious());
        }

        $this->assertSame($level, ob_get_level());
    }

    public function testPhpParseErrorLineCountsTheOpeningTagAndBlankLines(): void
    {
        $data = ['request' => []];

        foreach (["<?php\n\n\$a = 1;\n\$b = ;\n", "\n\n\$a = 1;\n\$b = ;"] as $script) {
            try {
                (new Php())->runScript($script, 'test', [], $data);
                $this->fail('expected a ScriptException');
            } catch (ScriptException $e) {
                $this->assertStringStartsWith('Exception executing script: Parse error on line 4: ', $e->getMessage());
            }
        }
    }

    public function testPhpScriptResultIsReturned(): void
    {
        $data = ['request' => ['method' => 'GET']];
        $output = null;

        $result = (new Php())->runScript("echo 'note';\nreturn ['verb' => \$event['request']['method']];", 'test', [], $data, $output);

        $this->assertSame(['verb' => 'GET'], $result['script_result']);
        $this->assertSame('note', $output);
    }

    public function testPython3WrapperHasNoDebugOutput(): void
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
 * Code a script calls into, outside the script itself.
 */
class ScriptOutputHelper
{
    public static function countOf($value)
    {
        return count($value);
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
