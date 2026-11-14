<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('ai', [
        'agent' => [
            'operation_namer' => [
                'platform' => 'domain_tracing.platform',
                'model' => [
                    'name' => 'opus',
                    'options' => ['tools' => []],
                ],
            ],
            'trace_narrator' => [
                'platform' => 'domain_tracing.platform',
                'model' => [
                    'name' => 'opus',
                    'options' => ['tools' => []],
                ],
            ],
        ],
    ]);
};
