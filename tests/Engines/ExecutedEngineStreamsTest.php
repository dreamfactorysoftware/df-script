<?php

namespace DreamFactory\Core\Script\Tests\Engines;

use DreamFactory\Core\Script\Engines\Python3;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;

/**
 * Executed engines read the interpreter's output and error streams; a script may write
 * any amount to either one.
 */
class ExecutedEngineStreamsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    protected function command(string $code): string
    {
        return escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);
    }

    public function testLargeErrorOutputIsReadAlongsideTheOutput(): void
    {
        Log::spy();
        $engine = new Python3(['command_path' => '/bin/true']);

        $last = $engine->execute(
            $this->command('fwrite(STDERR, str_repeat("e", 300000) . "\nFinal error line\n"); echo "first\nlast\n"; exit(1);'),
            $output,
            $return
        );

        $this->assertSame(1, $return);
        $this->assertSame(['first', 'last'], $output);
        $this->assertSame('last', $last);
        Log::shouldHaveReceived('error')->once()->withArgs(function ($message) {
            return str_contains($message, 'Final error line') &&
                str_contains($message, 'bytes not shown]') &&
                strlen($message) < Python3::STDERR_LOG_LIMIT + 200;
        });
    }

    public function testLargeOutputOnBothStreamsIsReadInFull(): void
    {
        Log::spy();
        $engine = new Python3(['command_path' => '/bin/true']);

        $engine->execute(
            $this->command(
                'for ($i = 0; $i < 50; $i++) { fwrite(STDERR, str_repeat("e", 10000)); echo str_repeat("o", 10000); } ' .
                'echo "\nend\n";'
            ),
            $output,
            $return
        );

        $this->assertSame(0, $return);
        $this->assertSame([str_repeat('o', 500000), 'end'], $output);
        Log::shouldNotHaveReceived('error');
    }

    public function testShortErrorOutputIsLoggedAsWritten(): void
    {
        Log::spy();
        $engine = new Python3(['command_path' => '/bin/true']);

        $engine->execute($this->command('fwrite(STDERR, "NameError: x\n"); exit(2);'), $output, $return);

        $this->assertSame(2, $return);
        $this->assertSame([], $output);
        Log::shouldHaveReceived('error')->once()->with("Script execution failed (exit=2). stderr:\nNameError: x");
    }
}
