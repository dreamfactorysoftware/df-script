<?php

namespace DreamFactory\Core\Script\Tests\Engines;

use DreamFactory\Core\Script\Engines\Python;
use DreamFactory\Core\Script\Engines\Python3;
use Orchestra\Testbench\TestCase;

/**
 * The Python wrappers hand the event and platform data to the script; every JSON
 * value has to arrive as the matching Python value, wherever it appears.
 */
class PythonEventDataTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    protected function payload(bool $unicode = true): array
    {
        $payload = [
            'flags'  => [true, null, false],
            'nested' => [['on' => false, 'off' => null], [1, 2.5, -3]],
            'text'   => 'x:true, y:null, z:false',
            'quotes' => "it's \"quoted\" \\ and ''' and \"\"\"",
            'lines'  => "first\nsecond\tthird",
            'path'   => 'a/b\\c',
            'empty'  => [],
            'object' => ['k' => 'v'],
        ];
        if ($unicode) {
            $payload['unicode'] = "caf\u{00e9} \u{1F600} \u{2028}";
        }

        return $payload;
    }

    protected function event(array $payload): array
    {
        return ['request' => ['method' => 'POST', 'headers' => ['host' => 'localhost'], 'payload' => $payload]];
    }

    public function testPython3WrapperEmbedsTheDataForDecoding(): void
    {
        $engine = new Python3EventDataProbe(['command_path' => '/bin/true']);

        $script = $engine->enrobe('return event.request.payload', $this->event($this->payload()));

        $this->assertStringContainsString("eventJson = json.loads(base64.b64decode('", $script);
        $this->assertStringContainsString("platformJson = json.loads(base64.b64decode('", $script);
        $this->assertStringNotContainsString('x:true', $script);
    }

    public function testPythonWrapperDefinesTheJsonLiterals(): void
    {
        $engine = new PythonEventDataProbe(['command_path' => '/bin/true']);

        $script = $engine->enrobe('return event.request.payload', $this->event($this->payload()));

        $this->assertStringContainsString("true, false, null = True, False, None;\neventJson = {", $script);
        $this->assertStringContainsString('"flags":[true,null,false]', $script);
        $this->assertStringContainsString('"text":"x:true, y:null, z:false"', $script);
    }

    public function testPython3ScriptReceivesEveryValueUnchanged(): void
    {
        $python = $this->interpreter(['python3'], 3, 'munch');
        $payload = $this->payload();

        $event = $this->runWrapped(new Python3EventDataProbe(['command_path' => $python]), $python, $payload);

        $this->assertSame(['bool', 'NoneType', 'bool'], $event['script_result']['types']);
        $this->assertSame(true, $event['script_result']['platform']);
        $this->assertSame(json_encode($payload), json_encode($event['script_result']['payload']));
    }

    public function testPythonScriptReceivesEveryValueUnchanged(): void
    {
        $python = $this->interpreter(['python2', 'python'], 2, 'bunch');
        $payload = $this->payload(false);

        $event = $this->runWrapped(new PythonEventDataProbe(['command_path' => $python]), $python, $payload);

        $this->assertSame(['bool', 'NoneType', 'bool'], $event['script_result']['types']);
        $this->assertSame(true, $event['script_result']['platform']);
        $this->assertSame(json_encode($payload), json_encode($event['script_result']['payload']));
    }

    /**
     * Path of a Python interpreter of the given major version with $module installed;
     * skips the test when there is none.
     */
    protected function interpreter(array $commands, int $major, string $module): string
    {
        foreach ($commands as $command) {
            $path = trim((string)shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
            if ('' === $path) {
                continue;
            }
            $check = "import sys, $module; sys.exit(0 if sys.version_info[0] == $major else 1)";
            exec(escapeshellarg($path) . ' -c ' . escapeshellarg($check) . ' 2>/dev/null', $ignored, $status);
            if (0 === $status) {
                return $path;
            }
        }
        $this->markTestSkipped("no Python $major interpreter with the $module module");
    }

    protected function runWrapped($engine, string $python, array $payload): array
    {
        $file = tempnam(sys_get_temp_dir(), 'py');
        file_put_contents($file, $engine->enrobe(
            "p = event.request.payload\n" .
            "return {'payload': p, 'types': [type(v).__name__ for v in p.flags], 'platform': platform.session.flag}",
            $this->event($payload)
        ));
        exec(escapeshellarg($python) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        unlink($file);

        $this->assertSame(0, $status, implode("\n", $output));

        return json_decode((string)end($output), true);
    }
}

/**
 * Fixed platform data for the wrappers.
 */
trait PythonEventDataProbeTrait
{
    public static function buildPlatformAccess($identifier)
    {
        return ['session' => ['app' => ['id' => 1], 'user' => ['id' => 1], 'flag' => true, 'list' => [false, null]]];
    }

    public function enrobe($script, array $data)
    {
        ob_start();
        try {
            return $this->enrobeScript($script, $data, static::buildPlatformAccess('test'));
        } finally {
            ob_end_clean();
        }
    }
}

class Python3EventDataProbe extends Python3
{
    use PythonEventDataProbeTrait;
}

class PythonEventDataProbe extends Python
{
    use PythonEventDataProbeTrait;
}
