<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $rawBackend = $_SERVER['QUEUE_BACKEND'] ?? $_ENV['QUEUE_BACKEND'] ?? 'postgres';
    $backend = is_string($rawBackend) ? strtolower($rawBackend) : 'postgres';
    if (!in_array($backend, ['postgres', 'redis', 'kafka'], true)) {
        $backend = 'postgres';
    }

    $retryStrategy = [
        'max_retries' => 5,
        'delay' => 1000,
        'multiplier' => 2,
        'max_delay' => 60000,
        'jitter' => 0.1,
    ];

    $transportsByBackend = [
        'postgres' => [
            'async' => [
                'dsn' => '%env(MESSENGER_TRANSPORT_DSN_POSTGRES)%',
                'options' => [
                    'queue_name' => 'booking_events',
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'derivations_events' => [
                'dsn' => '%env(MESSENGER_TRANSPORT_DSN_POSTGRES)%',
                'options' => [
                    'queue_name' => 'derivations_events',
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'failed' => [
                'dsn' => '%env(MESSENGER_FAILED_TRANSPORT_DSN_POSTGRES)%',
            ],
        ],
        'redis' => [
            'async' => [
                'dsn' => '%env(MESSENGER_TRANSPORT_DSN_REDIS)%',
                'options' => [
                    'queue' => 'booking_events',
                    'visibility_timeout_ms' => 60000,
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'derivations_events' => [
                'dsn' => '%env(MESSENGER_TRANSPORT_DSN_REDIS)%',
                'options' => [
                    'queue' => 'derivations_events',
                    'visibility_timeout_ms' => 60000,
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'failed' => [
                'dsn' => '%env(MESSENGER_FAILED_TRANSPORT_DSN_REDIS)%',
                'options' => [
                    'queue' => 'failed',
                    'visibility_timeout_ms' => 60000,
                ],
            ],
        ],
        'kafka' => [
            'async' => [
                'dsn' => '%env(MESSENGER_TRANSPORT_DSN_KAFKA)%',
                'options' => [
                    'topic' => 'booking_events',
                    'group_id' => '%env(MESSENGER_KAFKA_GROUP_ID)%',
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'derivations_events' => [
                'dsn' => '%env(MESSENGER_TRANSPORT_DSN_KAFKA)%',
                'options' => [
                    'topic' => 'derivations_events',
                    'group_id' => '%env(MESSENGER_KAFKA_GROUP_ID)%',
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'failed' => [
                'dsn' => '%env(MESSENGER_FAILED_TRANSPORT_DSN_KAFKA)%',
                'options' => [
                    'topic' => 'failed',
                    'group_id' => '%env(MESSENGER_KAFKA_FAILED_GROUP_ID)%',
                ],
            ],
        ],
    ];

    $routing = [
        'App\Application\Command\*' => 'async',
        'App\Domain\Event\*' => 'async',
        'App\Application\Command\GenerateQuotesCommand' => 'derivations_events',
        'App\Domain\Event\QuoteRequested' => 'derivations_events',
    ];

    if ($container->env() === 'test') {
        $routing['App\Tests\Infrastructure\Messenger\Fixtures\*'] = 'async';
    }

    $container->extension('framework', [
        'messenger' => [
            'failure_transport' => 'failed',
            'transports' => [
                'sync' => 'sync://',
                'async' => $transportsByBackend[$backend]['async'],
                'derivations_events' => $transportsByBackend[$backend]['derivations_events'],
                'failed' => $transportsByBackend[$backend]['failed'],
            ],
            'routing' => $routing,
        ],
    ]);
};
