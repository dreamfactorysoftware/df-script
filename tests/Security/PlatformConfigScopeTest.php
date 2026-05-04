<?php

namespace DreamFactory\Core\Script\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: scripts must only see a scoped allowlist of df config, never the
 * full df.* tree.
 *
 * The April 2026 audit (df-script F-04) found:
 *
 *     return [
 *         'api'    => static::getExposedApi(),
 *         'config' => Config::get('df'),     // <-- entire df.* namespace
 *         'session'=> Session::all(),
 *         ...
 *     ];
 *
 * `Config::get('df')` returns the entire DreamFactory configuration namespace
 * — DB connection strings, encryption keys, mail credentials, cache config,
 * and any secrets stored under df.*. This is then JSON-encoded and injected
 * directly into the user-authored script.
 *
 * After the fix, BaseEngineAdapter::buildPlatformAccess() must not pass the
 * unscoped `Config::get('df')` value through. Instead it must build a curated
 * allowlist of script-relevant keys.
 */
class PlatformConfigScopeTest extends TestCase
{
    private string $sourcePath;
    private string $contents;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/../../src/Components/BaseEngineAdapter.php';
        $this->assertFileExists($this->sourcePath);
        $this->contents = file_get_contents($this->sourcePath);
    }

    public function testBuildPlatformAccessDoesNotExposeFullDfConfig(): void
    {
        // Locate the buildPlatformAccess function body. The unscoped pattern
        // we are forbidding is `'config' => Config::get('df')` (entire tree).
        $this->assertDoesNotMatchRegularExpression(
            "/'config'\s*=>\s*Config::get\(\s*['\"]df['\"]\s*\)/",
            $this->contents,
            "buildPlatformAccess() must not expose the full Config::get('df') tree to scripts. "
            . 'Use a curated allowlist via getScriptSafeConfig() instead.'
        );
    }

    public function testScriptSafeConfigHelperExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/(public|protected)\s+static\s+function\s+getScriptSafeConfig\s*\(/',
            $this->contents,
            'BaseEngineAdapter must define a getScriptSafeConfig() helper that '
            . 'returns only the keys scripts need (no secrets, no db creds).'
        );
    }

    public function testScriptSafeConfigIsNotJustWildcardPassthrough(): void
    {
        // The helper must not simply return Config::get('df') under a different
        // name. We require an explicit allowlist — at minimum the scripting
        // subtree, which is what scripts actually need.
        $hasScriptingScope = preg_match(
            "/Config::get\s*\(\s*['\"]df\.scripting['\"]/",
            $this->contents
        ) === 1;

        $this->assertTrue(
            $hasScriptingScope,
            'getScriptSafeConfig() should expose the df.scripting subtree '
            . '(paths, default protocol) — and ONLY a curated set of safe keys.'
        );
    }
}
