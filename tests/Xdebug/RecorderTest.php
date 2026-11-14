<?php

namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[RequiresPhpExtension('xdebug')]
final class RecorderTest extends TestCase
{
    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function startWithRequest(): iterable
    {
        yield 'Xdebug does not start the trace' => ['no'];
        yield 'Xdebug starts the trace with the request' => ['yes'];
    }

    #[DataProvider('startWithRequest')]
    public function testRecordsTheRequestWithoutAPrependFile(string $startWithRequest): void
    {
        $result = $this->record('/greet?name=Ada', $startWithRequest);

        static::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $result['spanId']);
        static::assertNull($result['traceparent'], 'The trace context of the request is cleared when it ends');

        $request = json_decode(strtok((string) file_get_contents($this->directory.'/'.$result['spanId'].'.jsonl'), "\n"), true);
        static::assertSame(['request', 'GET', '/greet?name=Ada', 'project'], [$request['type'], $request['method'], $request['uri'], $request['service']]);
        static::assertFileExists($request['traceFile']);
        $trace = (string) file_get_contents('compress.zlib://'.$request['traceFile']);
        static::assertStringContainsString('Acme\Shop\Greeter->greet', $trace);
        static::assertStringNotContainsString('Valtzu\DomainTracingBundle', $trace, 'Only the application is traced');

        $outcome = json_decode((string) file_get_contents($request['traceFile'].'.outcome.json'), true);
        static::assertSame([500, 'RuntimeException', 'Greeting failed'], [$outcome['status'], $outcome['exception']['class'], $outcome['exception']['message']]);
        static::assertArrayHasKey(realpath(\dirname(__DIR__).'/Fixtures/project/src/Greeter.php'), json_decode((string) file_get_contents($request['traceFile'].'.lines.json'), true));
        static::assertFileExists($this->directory.'/codeindex-'.$request['codeIndex'].'.json');
        static::assertSame([$request['traceFile']], glob($this->directory.'/trace.*.xt*[!n]'), 'The trace that Xdebug started with the request is removed');
    }

    #[DataProvider('startWithRequest')]
    public function testDoesNotRecordAnExcludedRequest(string $startWithRequest): void
    {
        static::assertNull($this->record('/health', $startWithRequest)['spanId']);
        static::assertSame([], glob($this->directory.'/*'));
    }

    /**
     * @return array{spanId: ?string, traceparent: ?string}
     */
    private function record(string $uri, string $startWithRequest): array
    {
        $process = new Process([
            \PHP_BINARY,
            '-d', 'xdebug.mode=trace,coverage',
            '-d', 'xdebug.start_with_request='.$startWithRequest,
            '-d', 'xdebug.trace_format=1',
            '-d', 'xdebug.collect_return=1',
            '-d', 'xdebug.output_dir='.$this->directory,
            '-d', 'xdebug.trace_output_name=trace.%t.%r',
            \dirname(__DIR__).'/Fixtures/record.php',
            $this->directory,
            $uri,
        ]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
