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
                    'queue' => 'async',
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
                    'topic' => 'async',
                    'group_id' => '%env(default:KAFKA_CONSUMER_GROUP:MESSENGER_KAFKA_GROUP_ID)%',
                ],
                'retry_strategy' => $retryStrategy,
            ],
            'failed' => [
                'dsn' => '%env(MESSENGER_FAILED_TRANSPORT_DSN_KAFKA)%',
                'options' => [
                    'topic' => 'failed',
                    'group_id' => '%env(default:KAFKA_CONSUMER_GROUP:MESSENGER_KAFKA_FAILED_GROUP_ID)%',
                ],
            ],
        ],
    ];

    $routing = [
        'App\Application\Command\*' => 'async',
        'App\Domain\Event\*' => 'async',
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
                'failed' => $transportsByBackend[$backend]['failed'],
            ],
            'routing' => $routing,
        ],
    ]);
};
