<?php

namespace DreamFactory\Core\Script\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: scripts must not be able to issue HTTP requests to private,
 * loopback, or cloud-metadata addresses via the platform API.
 *
 * The April 2026 audit (df-script F-07) found that
 * `BaseEngineAdapter::externalRequest()` and the `inlineRequest()` path
 * accept any URL the script supplies, including:
 *   - http://169.254.169.254/         (AWS / Azure / GCP metadata)
 *   - http://127.0.0.1:8080/admin     (loopback)
 *   - http://10.0.0.5/internal        (RFC 1918)
 *   - file:///etc/passwd              (file scheme)
 *
 * After the fix, both entry points run any caller-supplied URL through
 * `SsrfValidator::validateExternalUrl()` — the same validator df-system
 * and df-email use — before any curl call.
 */
class ExternalRequestSsrfTest extends TestCase
{
    private string $sourcePath;
    private string $contents;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/../../src/Components/BaseEngineAdapter.php';
        $this->assertFileExists($this->sourcePath);
        $this->contents = file_get_contents($this->sourcePath);
    }

    public function testExternalRequestCallsSsrfValidator(): void
    {
        $start = strpos($this->contents, 'function externalRequest');
        $this->assertNotFalse($start);
        $end = strpos($this->contents, 'function ', $start + 10);
        $body = substr($this->contents, $start, $end === false ? null : ($end - $start));

        $this->assertMatchesRegularExpression(
            '/SsrfValidator::validateExternalUrl\s*\(/',
            $body,
            'externalRequest() must call SsrfValidator::validateExternalUrl() '
            . 'on the caller-supplied URL before issuing the curl request.'
        );
    }

    public function testValidatorIsCalledBeforeCurl(): void
    {
        $start = strpos($this->contents, 'function externalRequest');
        $this->assertNotFalse($start);
        $end = strpos($this->contents, 'function ', $start + 10);
        $body = substr($this->contents, $start, $end === false ? null : ($end - $start));

        $validatorPos = strpos($body, 'SsrfValidator::validateExternalUrl');
        $curlPos = strpos($body, 'Curl::request(');

        $this->assertNotFalse($validatorPos, 'validator call missing');
        $this->assertNotFalse($curlPos, 'Curl::request call missing');
        $this->assertLessThan(
            $curlPos,
            $validatorPos,
            'SsrfValidator::validateExternalUrl must be called BEFORE Curl::request'
        );
    }
}
