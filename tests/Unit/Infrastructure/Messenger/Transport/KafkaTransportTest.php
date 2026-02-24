<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Messenger\Transport;

use App\Infrastructure\Messenger\Transport\KafkaTransport;
use App\Infrastructure\Messenger\Transport\Stamp\KafkaReceivedStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class KafkaTransportTest extends TestCase
{
    public function testAckCommitsConsumedMessage(): void
    {
        $serializer = new KafkaDummySerializer();
        $transport = new KafkaTransport('kafka:9092', 'async', 'group-1', $serializer);
        $consumer = new FakeKafkaConsumer(
            [
                (object) [
                    'err' => 0,
                    'payload' => '{"id":"kafka-msg-1","envelope":{"body":"{\"ok\":true}","headers":{"type":"dummy"}}}',
                ],
            ]
        );
        $this->injectConsumer($transport, $consumer);

        $messages = iterator_to_array($transport->get());
        self::assertCount(1, $messages);

        $received = $messages[0];
        self::assertInstanceOf(KafkaReceivedStamp::class, $received->last(KafkaReceivedStamp::class));
        self::assertInstanceOf(
            TransportMessageIdStamp::class,
            $received->last(TransportMessageIdStamp::class)
        );

        $transport->ack($received);

        self::assertCount(1, $consumer->committed);
    }

    public function testRejectAlsoCommitsConsumedMessage(): void
    {
        $serializer = new KafkaDummySerializer();
        $transport = new KafkaTransport('kafka:9092', 'async', 'group-1', $serializer);
        $consumer = new FakeKafkaConsumer(
            [
                (object) [
                    'err' => 0,
                    'payload' => '{"id":"kafka-msg-2","envelope":{"body":"{\"ok\":true}","headers":{"type":"dummy"}}}',
                ],
            ]
        );
        $this->injectConsumer($transport, $consumer);

        $received = iterator_to_array($transport->get())[0];
        $transport->reject($received);

        self::assertCount(1, $consumer->committed);
    }

    private function injectConsumer(KafkaTransport $transport, FakeKafkaConsumer $consumer): void
    {
        $reflection = new \ReflectionClass($transport);
        $property = $reflection->getProperty('consumer');
        $property->setValue($transport, $consumer);
    }
}

final readonly class KafkaDummySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        return new Envelope((object) ['body' => $encodedEnvelope['body']]);
    }

    public function encode(Envelope $envelope): array
    {
        return [
            'body' => '{"ok":true}',
            'headers' => ['type' => 'dummy'],
        ];
    }
}

final class FakeKafkaConsumer
{
    /** @var list<object> */
    private array $messages;
    /** @var list<object> */
    public array $committed = [];

    /**
     * @param list<object> $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function consume(int $timeoutMs): object
    {
        if ($this->messages !== []) {
            return array_shift($this->messages);
        }

        return (object) [
            'err' => -185, // RD_KAFKA_RESP_ERR__TIMED_OUT fallback code in transport.
            'payload' => '',
        ];
    }

    public function commit(object $message): void
    {
        $this->committed[] = $message;
    }
}
