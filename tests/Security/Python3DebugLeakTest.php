<?php

namespace DreamFactory\Core\Script\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: Python3 engine must not echo the serialized event payload.
 *
 * Engines/Python3.php had a bare `echo $jsonEvent;` left in from debugging.
 * The serialized event payload contains request headers, body, query params,
 * and session data. When the Python3 engine is invoked from a queue worker
 * (no output buffering), this writes to stdout/logs.
 */
class Python3DebugLeakTest extends TestCase
{
    public function testPython3EngineDoesNotEchoJsonEvent(): void
    {
        $absPath = __DIR__ . '/../../src/Engines/Python3.php';
        $this->assertFileExists($absPath);

        $contents = file_get_contents($absPath);

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*echo\s+\$jsonEvent\s*;/m',
            $contents,
            'Python3.php must not contain `echo $jsonEvent;` (debug leak of request/session data)'
        );
    }
}
