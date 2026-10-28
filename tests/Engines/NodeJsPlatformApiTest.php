<?php

namespace DreamFactory\Core\Script\Tests\Engines;

use DreamFactory\Core\Script\Engines\NodeJs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * df-script#70: renders the real Node wrapper, runs it under node against a local echo server.
 */
class NodeJsPlatformApiTest extends TestCase
{
    private static $server;
    private static $port;
    private static $log;

    public static function setUpBeforeClass(): void
    {
        if (!file_exists(getcwd() . '/bootstrap/app.php')) {
            self::markTestSkipped('Needs a Laravel app root (bootstrap/app.php) as the working directory.');
        }
        if (trim((string)shell_exec('command -v node')) === '') {
            self::markTestSkipped('Needs a node binary on PATH.');
        }
        $app = require getcwd() . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        self::$log = tempnam(sys_get_temp_dir(), 'echo');
        $echo = <<<'JS'
const fs=require('fs');const log=process.argv[1];
const s=require('http').createServer((q,r)=>{let b='';q.on('data',c=>b+=c);q.on('end',()=>{
fs.appendFileSync(log,JSON.stringify({method:q.method,path:q.url,headers:q.headers,body:b})+"\n");
r.setHeader('content-type','application/json');r.end(JSON.stringify({echo_method:q.method}));});});
s.listen(0,'127.0.0.1',()=>console.log(s.address().port));
JS;
        self::$server = proc_open(['node', '-e', $echo, self::$log], [1 => ['pipe', 'w']], $pipes);
        try {
            self::$port = (int)trim((string)fgets($pipes[1]));
            if (self::$port === 0) {
                throw new \RuntimeException('Echo server did not report a port.');
            }
        } catch (\Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            self::$server = null;
        }
        @unlink(self::$log);
    }

    /** @return array{0: array, 1: array} [final event, requests seen by the echo server] */
    private function runWrapper(string $js): array
    {
        file_put_contents(self::$log, '');
        $data = ['request' => ['headers' => ['host' => '127.0.0.1:' . self::$port]]];
        $engine = new NodeJs([]);
        $wrapper = (new \ReflectionMethod($engine, 'enrobeScript'))->invokeArgs($engine, [$js, &$data, []]);
        $base = tempnam(sys_get_temp_dir(), 'wrap');
        $file = $base . '.js';
        file_put_contents($file, $wrapper);
        exec('timeout 10 node ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
        unlink($file);
        unlink($base);
        $this->assertSame(0, $rc, implode("\n", $out));
        $event = null;
        foreach ($out as $line) {   // same rule as ExecutedEngine::processOutput: last {"request" line wins
            if (str_starts_with($line, '{"request"')) {
                $event = json_decode($line, true);
            }
        }
        $reqs = array_map(fn($l) => json_decode($l, true), array_filter(explode("\n", file_get_contents(self::$log))));
        return [$event, array_values($reqs)];
    }

    // callback records every firing; final setResponse carries the count
    private const CB = "var n=0; var cb=function(body, res){ n++; event.setResponse({fired:n}); };";

    public static function correctCalls(): array
    {
        $h = "{headers:{'x-t':'1'}}";
        return [
            'get'               => ["platform.api.get('e/g', $h, cb);", 'GET', '/api/v2/e/g', ''],
            'get parameters'    => ["platform.api.get('e/g', {parameters:{a:1,b:2}}, cb);", 'GET', '/api/v2/e/g?a=1&b=2', ''],
            'post'              => ["platform.api.post('e/p', {k:1}, $h, cb);", 'POST', '/api/v2/e/p', '{"k":1}'],
            'put'               => ["platform.api.put('e/p', {k:1}, $h, cb);", 'PUT', '/api/v2/e/p', '{"k":1}'],
            'patch'             => ["platform.api.patch('e/p', {k:1}, $h, cb);", 'PATCH', '/api/v2/e/p', '{"k":1}'],
            'delete no payload' => ["platform.api.delete('e/d', null, $h, cb);", 'DELETE', '/api/v2/e/d', null],
            'delete payload'    => ["platform.api.delete('e/d', {k:1}, $h, cb);", 'POST', '/api/v2/e/d', '{"k":1}'],
        ];
    }

    #[DataProvider('correctCalls')]
    public function testCorrectCallSendsAndFiresCallbackOnce(string $js, string $method, string $path, ?string $body): void
    {
        [$event, $reqs] = $this->runWrapper(self::CB . $js);
        $this->assertArrayNotHasKey('exception', $event);
        $this->assertCount(1, $reqs);
        $this->assertSame($method, $reqs[0]['method']);
        $this->assertSame($path, $reqs[0]['path']);
        if ($body !== null) {   // Node sends a bodyless DELETE without framing; the wrapper's "null" is not observable
            $this->assertSame($body, $reqs[0]['body']);
        }
        $this->assertNotEmpty($reqs[0]['headers']['x-dreamfactory-script-token']);
        if (!str_contains($path, '?')) {
            $this->assertSame('1', $reqs[0]['headers']['x-t'] ?? null);
        }
        if ($method === 'POST' && str_contains($path, '/e/d')) {
            $this->assertSame('DELETE', $reqs[0]['headers']['x-http-method'] ?? null);
        }
        $this->assertSame(1, $event['response']['content']['fired']);
    }

    public static function wrongOrderCalls(): array
    {
        $m = fn($v, $sig) => "platform.api.$v: callback must be the last argument: $v($sig)";
        $w = 'path, payload, options, callback';
        return [
            'get(path, fn)'              => ["platform.api.get('e/x', cb);", $m('get', 'path, options, callback')],
            'post(path, payload, fn)'    => ["platform.api.post('e/x', {k:1}, cb);", $m('post', $w)],
            'put(path, payload, fn)'     => ["platform.api.put('e/x', {k:1}, cb);", $m('put', $w)],
            'patch(path, payload, fn)'   => ["platform.api.patch('e/x', {k:1}, cb);", $m('patch', $w)],
            'delete(path, payload, fn)'  => ["platform.api.delete('e/x', {k:1}, cb);", $m('delete', $w)],
            'delete(path, null, fn)'     => ["platform.api.delete('e/x', null, cb);", $m('delete', $w)],
            'delete(path, fn)'           => ["platform.api.delete('e/x', cb);", $m('delete', $w)],
            'post(path, fn)'             => ["platform.api.post('e/x', cb);", $m('post', $w)],
            'call(verb, path, payload, fn)' => ["platform.api.call('DELETE', 'e/x', {k:1}, cb);",
                                               $m('call', 'verb, path, payload, options, callback')],
        ];
    }

    #[DataProvider('wrongOrderCalls')]
    public function testWrongOrderThrowsAndSendsNothing(string $js, string $message): void
    {
        [$event, $reqs] = $this->runWrapper(self::CB . $js);
        $this->assertSame($message, $event['exception']['message'] ?? null);
        $this->assertSame([], $reqs, 'no request may be sent');
        $this->assertArrayNotHasKey('response', $event, 'callback must never fire');
    }

    public static function noCallbackCalls(): array
    {
        return [
            'get(path)'                   => ["platform.api.get('e/n');", 'GET'],
            'get(path, options)'          => ["platform.api.get('e/n', {});", 'GET'],
            'post(path, payload)'         => ["platform.api.post('e/n', {k:1});", 'POST'],
            'post(path, payload, opts)'   => ["platform.api.post('e/n', {k:1}, {});", 'POST'],
            'put(path, payload)'          => ["platform.api.put('e/n', {k:1});", 'PUT'],
            'patch(path, payload)'        => ["platform.api.patch('e/n', {k:1});", 'PATCH'],
            'delete(path, null, opts)'    => ["platform.api.delete('e/n', null, {});", 'DELETE'],
            'delete(path, payload, opts)' => ["platform.api.delete('e/n', {k:1}, {});", 'POST', 'DELETE'],
        ];
    }

    /** Fire-and-forget calls are correct calls: they must not throw and must still send. */
    #[DataProvider('noCallbackCalls')]
    public function testCorrectCallWithoutCallbackStillSends(string $js, string $method, ?string $override = null): void
    {
        [$event, $reqs] = $this->runWrapper($js);
        $this->assertArrayNotHasKey('exception', $event);
        $this->assertCount(1, $reqs);
        $this->assertSame($method, $reqs[0]['method']);
        if ($override !== null) {
            $this->assertSame($override, $reqs[0]['headers']['x-http-method'] ?? null);
        }
    }
}
