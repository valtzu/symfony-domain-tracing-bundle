<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Valtzu\DomainTracingBundle\Xdebug\CodeIndex;

final class CodeIndexTest extends TestCase
{
    private string $root;

    #[\Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->root.'/composer.json', '{"autoload": {"psr-4": {"Shop\\\\": "src/"}}}');
        $filesystem->dumpFile($this->root.'/src/Checkout.php', <<<'PHP'
            <?php

            namespace Shop;

            #[SomeAttribute]
            final class Checkout extends ClassThatDoesNotExist
            {
                public function __construct(private Pricing $pricing)
                {
                }

                #[Route('/checkout')]
                public function placeOrder(
                    string $customer,
                    array &$lines = [self::DEFAULT],
                    string ...$coupons,
                ): Order {
                    $total = array_map(fn ($line) => $line->price, $lines);
                    $logger = new class {
                        public function log(string $message): void
                        {
                        }
                    };

                    return new Order(function () use ($total) {
                        return $total;
                    });
                }

                abstract protected function audit(Order $order): void;
            }
            PHP);
        $filesystem->dumpFile($this->root.'/src/functions.php', "<?php\n\nfunction shop_helper(\$x) { return \$x; }\n");
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testReadsTheMethodsWithoutLoadingTheClasses(): void
    {
        $index = CodeIndex::build($this->root, true, ['Shop\Checkout']);
        $file = $index['files'][(string) realpath($this->root.'/src/Checkout.php')] ?? null;
        static::assertIsArray($file);

        static::assertFalse(class_exists('Shop\Checkout', false), 'A class that extends a missing class would break the request');
        static::assertSame([
            '__construct' => [8, 10, ['pricing']],
            'placeOrder' => [13, 28, ['customer', 'lines', 'coupons']],
            'audit' => [30, 30, ['order']],
        ], $file['methods']);
        static::assertTrue($file['entrypoint']);
        static::assertSame([18, 25], $file['closureLines']);
    }

    public function testTakesTheEntrypointsFromTheContainer(): void
    {
        $index = CodeIndex::build($this->root, true, []);

        static::assertFalse($index['files'][(string) realpath($this->root.'/src/Checkout.php')]['entrypoint'] ?? null);
        static::assertNotSame($index['fingerprint'], CodeIndex::build($this->root, false, ['Shop\Checkout'])['fingerprint'], 'Another set of entrypoints needs another index');
    }
}
