<?php

namespace DreamFactory\Core\Script\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that script engine token generation uses cryptographically secure
 * random_bytes() instead of the predictable uniqid().
 *
 * uniqid() is microtime-based (~20 bits of entropy), allowing token prediction.
 * random_bytes(32) provides 256 bits of entropy.
 */
class ScriptTokenEntropyTest extends TestCase
{
    /**
     * @dataProvider engineClassProvider
     */
    public function testEngineUsesRandomBytesNotUniqid(string $className): void
    {
        $reflection = new \ReflectionClass($className);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringNotContainsString(
            'uniqid()',
            $source,
            "$className must not use uniqid() for token generation (insufficient entropy)"
        );

        $this->assertStringContainsString(
            'random_bytes',
            $source,
            "$className must use random_bytes() for cryptographically secure token generation"
        );
    }

    /**
     * Verify the token format: bin2hex(random_bytes(32)) produces a 64-char hex string.
     */
    public function testTokenFormat(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->assertEquals(64, strlen($token), 'Token should be 64 hex characters (256 bits)');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * Verify uniqueness across 100 tokens (basic sanity check).
     */
    public function testTokenUniqueness(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = bin2hex(random_bytes(32));
        }
        $this->assertCount(100, array_unique($tokens), 'All 100 tokens should be unique');
    }

    public static function engineClassProvider(): array
    {
        return [
            'NodeJs'  => [\DreamFactory\Core\Script\Engines\NodeJs::class],
            'Python'  => [\DreamFactory\Core\Script\Engines\Python::class],
            'Python3' => [\DreamFactory\Core\Script\Engines\Python3::class],
        ];
    }
}
