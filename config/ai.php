<?php

declare(strict_types=1);


use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('ai', [
        'agent' => [
            'domain_tracing_operation_namer' => [
                'platform' => 'domain_tracing.platform',
                'model' => [
                    'name' => 'opus',
                    'options' => ['tools' => []],
                ],
            ],
            'domain_tracing_trace_narrator' => [
                'platform' => 'domain_tracing.platform',
                'model' => [
                    'name' => 'opus',
                    'options' => ['tools' => []],
                ],
            ],
            'domain_tracing_context_writer' => [
                'platform' => 'domain_tracing.platform',
                'model' => [
                    'name' => 'opus',
                    'options' => ['tools' => []],
                ],
            ],
        ],
    ], prepend: true);
};
