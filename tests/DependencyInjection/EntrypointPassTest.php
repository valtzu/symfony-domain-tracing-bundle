<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Valtzu\DomainTracingBundle\DependencyInjection\EntrypointPass;
use Valtzu\DomainTracingBundle\Xdebug\Recorder;

final class EntrypointPassTest extends TestCase
{
    public function testFindsTheEntrypointsFromTheTagsOfTheirServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.handler_class', 'App\Billing\ChargeHandler');
        $container->register('domain_tracing.recorder', Recorder::class);
        $container->register('App\Controller\ShopController')->addTag('controller.service_arguments');
        // A command configured in YAML, without the AsCommand attribute
        $container->register('app.import', 'App\Command\ImportCommand')->addTag('console.command', ['command' => 'app:import']);
        $container->register('app.charge_handler', '%app.handler_class%')->addTag('messenger.message_handler');
        $container->register('App\EventListener\LocaleListener')->addTag('kernel.event_listener', ['event' => 'kernel.request']);
        $container->register('App\Checkout\Pricing');
        $container->register('domain_tracing.recording_listener', 'Valtzu\DomainTracingBundle\EventListener\RecordingListener')->addTag('kernel.event_listener');

        (new EntrypointPass())->process($container);

        static::assertSame([
            'App\Billing\ChargeHandler',
            'App\Command\ImportCommand',
            'App\Controller\ShopController',
            'App\EventListener\LocaleListener',
        ], $container->getDefinition('domain_tracing.recorder')->getArgument('$entrypoints'));
    }
}
