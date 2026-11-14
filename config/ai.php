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
                'prompt' => ['file' => __DIR__.'/prompts/operation_namer.md'],
            ],
            'trace_narrator' => [
                'platform' => 'domain_tracing.platform',
                'model' => [
                    'name' => 'opus',
                    'options' => ['tools' => []],
                ],
                'prompt' => ['file' => __DIR__.'/prompts/trace_narrator.md'],
            ],
        ],
    ]);
};
