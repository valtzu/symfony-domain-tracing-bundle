<?php

namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[RequiresPhpExtension('xdebug')]
final class RecorderTest extends TestCase
{
    private string $directory;
    private string $traceDirectory;
    private string $ownDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $this->traceDirectory = $this->directory.'/traces';
        $this->ownDirectory = $this->directory.'/own';
        mkdir($this->ownDirectory, recursive: true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testRecordsTheRequestWithOnlyTheXdebugMode(): void
    {
        $result = $this->record('/greet?name=Ada');

        $request = $this->request($result['spanId']);
        static::assertSame(['request', 'GET', '/greet?name=Ada', 'project'], [$request['type'], $request['method'], $request['uri'], $request['service']]);
        static::assertStringStartsWith($this->traceDirectory.'/trace.'.$result['spanId'].'.xt', $request['traceFile']);
        static::assertFalse($result['traceFile'], 'The trace stops with the request');
        static::assertNull($result['traceparent'], 'The trace context of the request is cleared when it ends');
        static::assertSame('0', $result['collectReturn'], 'The Xdebug settings are restored when the request ends');

        $trace = (string) file_get_contents('compress.zlib://'.$request['traceFile']);
        static::assertStringContainsString('Acme\Shop\Greeter->greet', $trace);
        static::assertStringContainsString("\tR\t\t\t'Hello Ada'", $trace, 'The return values are in the trace');
        static::assertStringNotContainsString('Valtzu\DomainTracingBundle', $trace, 'Only the application is traced');

        $outcome = json_decode((string) file_get_contents($this->traceDirectory.'/'.$result['spanId'].'.outcome.json'), true);
        static::assertSame([500, 'RuntimeException', 'Greeting failed'], [$outcome['status'], $outcome['exception']['class'], $outcome['exception']['message']]);
        static::assertArrayHasKey(realpath(\dirname(__DIR__).'/Fixtures/project/src/Greeter.php'), json_decode((string) file_get_contents($this->traceDirectory.'/'.$result['spanId'].'.lines.json'), true));
        static::assertFileExists($this->traceDirectory.'/codeindex-'.$request['codeIndex'].'.json');
    }

    public function testRecordsTheRequestInTheTraceThatXdebugStartedInFormat1(): void
    {
        $result = $this->record('/greet?name=Ada', ['xdebug.start_with_request' => 'yes', 'xdebug.trace_format' => '1', 'xdebug.output_dir' => $this->ownDirectory]);

        $request = $this->request($result['spanId']);
        static::assertSame($result['traceFile'], $request['traceFile'], 'The trace of Xdebug goes on after the request');
        static::assertStringStartsWith($this->ownDirectory.'/', $request['traceFile']);
        static::assertStringContainsString("\tR\t\t\t'Hello Ada'", (string) file_get_contents('compress.zlib://'.$request['traceFile']));
        static::assertSame([], glob($this->traceDirectory.'/trace.*'), 'No trace of the bundle');
        static::assertFileExists($this->traceDirectory.'/'.$result['spanId'].'.outcome.json');
    }

    public function testDoesNotRecordTheRequestWhenXdebugTracesItInAnotherFormat(): void
    {
        $result = $this->record('/greet?name=Ada', ['xdebug.start_with_request' => 'yes', 'xdebug.output_dir' => $this->ownDirectory]);

        static::assertNull($result['spanId']);
        static::assertFileExists($result['traceFile'], 'The trace of Xdebug is left as it is');
        static::assertDirectoryDoesNotExist($this->traceDirectory);
    }

    public function testDoesNotRecordAnExcludedRequest(): void
    {
        static::assertNull($this->record('/health')['spanId']);
        static::assertNull($this->record('/health', ['xdebug.start_with_request' => 'yes', 'xdebug.trace_format' => '1', 'xdebug.output_dir' => $this->ownDirectory])['spanId']);
        static::assertDirectoryDoesNotExist($this->traceDirectory);
        static::assertCount(1, glob($this->ownDirectory.'/trace.*'), 'The trace of Xdebug is left as it is');
    }

    private function request(?string $spanId): array
    {
        static::assertIsString($spanId);

        return json_decode((string) strtok((string) file_get_contents($this->traceDirectory.'/'.$spanId.'.jsonl'), "\n"), true);
    }

    /**
     * @param array<string, string> $settings Xdebug settings besides xdebug.mode
     *
     * @return array{spanId: ?string, traceparent: ?string, collectReturn: string, traceFile: string|false}
     */
    private function record(string $uri, array $settings = []): array
    {
        $arguments = [\PHP_BINARY, '-d', 'xdebug.mode=trace,coverage', '-d', 'xdebug.start_with_request=no'];
        foreach ($settings as $name => $value) {
            $arguments = [...$arguments, '-d', $name.'='.$value];
        }
        $process = new Process([...$arguments, \dirname(__DIR__).'/Fixtures/record.php', $this->traceDirectory, $uri]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
