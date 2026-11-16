<?php

namespace DreamFactory\Core\Script\Tests\Engines;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Script\Engines\ExecutedEngine;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;

/**
 * The one-time script token that Node.js and Python scripts use to call back
 * into the platform must exist while the script process runs and must be gone
 * once it exits. Before this test existed the token was left to expire via its
 * TTL, which the file and database cache drivers never enforce, so every script
 * execution left one cache entry behind for good.
 */
class ScriptTokenLifecycleTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    protected function makeEngine(): TokenProbeEngine
    {
        return new TokenProbeEngine(['command_name' => 'true', 'command_path' => '/bin/true']);
    }

    public function testTokenIsCachedWhileTheScriptRunsAndRemovedAfterwards(): void
    {
        $engine = $this->makeEngine();
        $seen = null;
        $engine->onRun = function (TokenProbeEngine $e) use (&$seen) {
            $seen = Cache::get($e->tokenKey());
        };

        $data = [];
        $result = $engine->executeString('noop', 'test', $data);

        $this->assertSame(['app_id' => 5, 'user_id' => null], $seen, 'token must resolve the caller while the script runs');
        $this->assertSame(['request' => ['from' => 'script']], $result);
        $this->assertFalse(Cache::has($engine->lastTokenKey), 'token must be removed once the script process exits');
        $this->assertNull($engine->tokenKey());
    }

    public function testTokenIsRemovedWhenTheScriptExitsWithAnError(): void
    {
        $engine = $this->makeEngine();
        $engine->exitCode = 3;

        try {
            $data = [];
            $engine->executeString('noop', 'test', $data);
            $this->fail('expected an exception for a non-zero exit code');
        } catch (InternalServerErrorException $e) {
            $this->assertStringContainsString('error code: 3', $e->getMessage());
        }

        $this->assertFalse(Cache::has($engine->lastTokenKey));
    }

    public function testTokenIsRemovedWhenTheProcessCannotBeStarted(): void
    {
        $engine = $this->makeEngine();
        $engine->onRun = function () {
            throw new \RuntimeException('proc_open failed');
        };

        $data = [];
        $this->assertNull($engine->executeString('noop', 'test', $data));
        $this->assertFalse(Cache::has($engine->lastTokenKey));
    }

    public function testEveryExecutionGetsItsOwnToken(): void
    {
        $engine = $this->makeEngine();
        $data = [];
        $engine->executeString('noop', 'a', $data);
        $first = $engine->lastTokenKey;
        $engine->executeString('noop', 'b', $data);

        $this->assertNotSame($first, $engine->lastTokenKey);
        $this->assertFalse(Cache::has($first));
        $this->assertFalse(Cache::has($engine->lastTokenKey));
    }

    public function testReleaseIsIdempotentWithoutAToken(): void
    {
        $engine = $this->makeEngine();
        $engine->release();
        $this->assertNull($engine->tokenKey());
    }
}

/**
 * Minimal ExecutedEngine: registers a token the way the real engines do and
 * lets the test observe the cache from "inside" the script process.
 */
class TokenProbeEngine extends ExecutedEngine
{
    /** @var callable|null Called in place of the script process */
    public $onRun = null;
    public $exitCode = 0;
    public $lastTokenKey = null;

    public static function buildPlatformAccess($identifier)
    {
        return ['session' => ['app' => ['id' => 5], 'user' => ['id' => null]]];
    }

    protected function enrobeScript($script, array &$data = [], array $platform = [])
    {
        $this->registerScriptToken(bin2hex(random_bytes(32)), $platform);
        $this->lastTokenKey = $this->scriptTokenKey;

        return $script;
    }

    protected function buildCommand($payload = '', $storage_location = '')
    {
        return 'true';
    }

    public function execute($command, array &$output = null, &$return = null)
    {
        if ($this->onRun) {
            ($this->onRun)($this);
        }
        $output = ['{"request":{"from":"script"}}'];
        $return = $this->exitCode;

        return end($output);
    }

    public function tokenKey()
    {
        return $this->scriptTokenKey;
    }

    public function release()
    {
        $this->releaseScriptToken();
    }
}
