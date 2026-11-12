<?php

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use Valtzu\DomainTracingBundle\Ai\SecretRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretRedactorTest extends TestCase
{
    public function testRedactsValuesOfSecretEnvironmentVariables()
    {
        $redactor = new SecretRedactor([
            'APP_SECRET' => '71eeab6705819f0957d51dcaca8ef1b2',
            'DATABASE_DSN' => 'pgsql:host=db;password=hunter22',
            'APP_ENV' => 'dev',
            'SHORT_TOKEN' => 'abc',
        ]);

        $this->assertSame(
            'secret [APP_SECRET redacted], dsn [DATABASE_DSN redacted], env dev, abc',
            $redactor->redact('secret 71eeab6705819f0957d51dcaca8ef1b2, dsn pgsql:host=db;password=hunter22, env dev, abc'),
        );
    }

    #[DataProvider('provideSecrets')]
    public function testRedactsSecrets(string $text, string $expected)
    {
        $this->assertSame($expected, (new SecretRedactor([]))->redact($text));
    }

    public static function provideSecrets(): iterable
    {
        yield 'assignment' => ['$apiKey = \'abc123secret\';', '$apiKey = \'[redacted]\';'];
        yield 'array item' => ["'password' => \"correct horse\",", "'password' => \"[redacted]\","];
        yield 'constant' => ['private const STRIPE_SECRET_KEY = \'whatever-value\';', 'private const STRIPE_SECRET_KEY = \'[redacted]\';'];
        yield 'named argument' => ['new Client(authToken: \'s3cr3t-value\')', 'new Client(authToken: \'[redacted]\')'];
        yield 'url credentials' => ['redis://default:p4ssw0rd@cache:6379', 'redis://default:[redacted]@cache:6379'];
        yield 'query string' => ['/reset?user=bob&token=a1b2c3d4&x=1', '/reset?user=bob&token=[redacted]&x=1'];
        yield 'bearer header' => ['Authorization: Bearer abcdef123456789', 'Authorization: Bearer [redacted]'];
        yield 'anthropic key' => ['sk-ant-api03-AbCdEfGhIjKlMnOpQrStUv', '[redacted]'];
        yield 'github token' => ['ghp_aBcDeFgHiJkLmNoPqRsTuVwXyZ012345', '[redacted]'];
        yield 'aws key' => ['AKIAIOSFODNN7EXAMPLE', '[redacted]'];
        yield 'jwt' => ['eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NSJ9.c2lnbmF0dXJlLXZhbHVl', '[redacted]'];
        yield 'random literal' => ["\$client->setKey('Zm9vYmFyYmF6cXV4MTIzNDU2Nzg5MA');", "\$client->setKey('[redacted]');"];
        yield 'private key' => ["-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAKj34\n-----END RSA PRIVATE KEY-----", '[redacted]'];
    }

    #[DataProvider('provideHarmlessText')]
    public function testKeepsHarmlessText(string $text)
    {
        $this->assertSame($text, (new SecretRedactor([]))->redact($text));
    }

    public static function provideHarmlessText(): iterable
    {
        yield ["'customer' => \$request->request->getString('customer'),"];
        yield ["throw new CheckoutFailed('payment_declined', \\sprintf('Card was declined (%s) for %d cents.', \$declineCode, \$amountCents));"];
        yield ["\$this->stock[\$product->sku] -= \$quantity;"];
        yield ['src/Checkout/PaymentGateway.php:13  if ($declineCode = self::DECLINED_CUSTOMERS[$customer->id] ?? null) {'];
        yield ['in App\Checkout\Pricing->applyDiscounts():'];
        yield ['Request: POST /orders?page=2'];
        yield ["'BLACKFRIDAY' => new Coupon('BLACKFRIDAY', percentOff: 25),"];
    }
}
