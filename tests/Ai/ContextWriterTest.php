<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Valtzu\DomainTracingBundle\Command\ContextGenerateCommand;
use Valtzu\DomainTracingBundle\Ai\ContextWriter;
use Valtzu\DomainTracingBundle\Ai\ProjectSummary;

final class ContextWriterTest extends TestCase
{
    private string $projectDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/domain-tracing-context-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->projectDir.'/README.md', "# Shirt shop\n\nSells shirts online.\n");
        $filesystem->dumpFile($this->projectDir.'/src/Checkout/Pricing.php', "<?php\n");
        $filesystem->dumpFile($this->projectDir.'/src/Checkout/FraudScreening.php', "<?php\n");
        $filesystem->dumpFile($this->projectDir.'/src/Kernel.php', "<?php\n");
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testSummarizesTheReadmeTheRoutesAndTheClasses(): void
    {
        $input = $this->project()->describe();

        static::assertSame(<<<'INPUT'
            README:
            # Shirt shop

            Sells shirts online.


            Routes:
            checkout_place_order POST /checkout
            product_show /products/{id}

            Classes by directory:
            src/Checkout: FraudScreening, Pricing
            src: Kernel
            INPUT, $input);
    }

    public function testStreamsTheDescriptionOfTheAgent(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())->method('call')
            ->with(static::callback(static fn (MessageBag $messages): bool => str_starts_with((string) $messages->getSystemMessage()?->getContent(), 'You describe an application')))
            ->willReturn($this->execution(['The shop ', 'sells shirts.']));

        static::assertSame(['The shop ', 'sells shirts.'], iterator_to_array((new ContextWriter($agent, $this->project()))->write(), preserve_keys: false));
    }

    public function testCommandWritesTheContextFile(): void
    {
        $tester = new CommandTester(new Command(null, new ContextGenerateCommand(new ContextWriter($this->agentAnswering(['The shop sells shirts.']), $this->project()), $this->projectDir.'/config/domain_tracing.md')));

        static::assertSame(0, $tester->execute(['--write' => true]));
        static::assertStringEndsWith("\n".$this->projectDir."/config/domain_tracing.md\n", $tester->getDisplay());
        static::assertSame("The shop sells shirts.\n", file_get_contents($this->projectDir.'/config/domain_tracing.md'));
    }

    public function testCommandWritesNothingWithoutAsking(): void
    {
        $tester = new CommandTester(new Command(null, new ContextGenerateCommand(new ContextWriter($this->agentAnswering(['The shop sells shirts.']), $this->project()), $this->projectDir.'/config/domain_tracing.md')));

        static::assertSame(0, $tester->execute([], ['interactive' => false]));
        static::assertStringContainsString('The shop sells shirts.', $tester->getDisplay());
        static::assertStringEndsWith("\n".$this->projectDir."/config/domain_tracing.md\n", $tester->getDisplay());
        static::assertFileDoesNotExist($this->projectDir.'/config/domain_tracing.md');
    }

    private function project(): ProjectSummary
    {
        $routes = new RouteCollection();
        $routes->add('_profiler', new Route('/_profiler'));
        $routes->add('checkout_place_order', new Route('/checkout', methods: ['POST']));
        $routes->add('product_show', new Route('/products/{id}'));
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        return new ProjectSummary($router, $this->projectDir);
    }

    /**
     * @param list<string> $answer
     */
    private function agentAnswering(array $answer): AgentInterface
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn($this->execution($answer));

        return $agent;
    }

    /**
     * @param list<string> $answer
     */
    private function execution(array $answer): ExecutionInterface
    {
        $execution = $this->createStub(ExecutionInterface::class);
        $execution->method('asStream')->willReturn((static function () use ($answer): \Generator {
            foreach ($answer as $text) {
                yield new TextDelta($text);
            }
        })());

        return $execution;
    }
}
