<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * The built-in web server of PHP, which the Symfony CLI runs when PHP-FPM is not installed, handles one request at a
 * time unless PHP_CLI_SERVER_WORKERS says otherwise. A stream that stays open would then block the application, so
 * the pages of the bundle poll instead.
 */
final class BuiltInServer
{
    public static function hasOneWorker(string $sapi = \PHP_SAPI, string|false|null $workers = null): bool
    {
        $workers ??= getenv('PHP_CLI_SERVER_WORKERS');

        return 'cli-server' === $sapi && (int) ($workers ?: 1) <= 1;
    }
}
