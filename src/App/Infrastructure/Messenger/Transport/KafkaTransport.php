<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Transport;

use App\Infrastructure\Messenger\Transport\Stamp\KafkaReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class KafkaTransport implements TransportInterface
{
    private object|null $producer = null;
    private object|null $consumer = null;

    public function __construct(
        private readonly string $brokers,
        private readonly string $topic,
        private readonly string $groupId,
        private readonly SerializerInterface $serializer,
    ) {}

    public function get(): iterable
    {
        /** @var \RdKafka\KafkaConsumer $consumer */
        $consumer = $this->consumer();
        $message = $consumer->consume(1000);
        $errorCode = $message->err;
        if ($errorCode === $this->rdKafkaErrTimedOut() || $errorCode === $this->rdKafkaErrPartitionEof()) {
            return [];
        }
        if ($errorCode !== $this->rdKafkaErrNoError()) {
            throw new TransportException(sprintf('Kafka consume error: %s', $message->errstr()));
        }

        $payload = $message->payload;
        if ($payload === '') {
            throw new TransportException('Kafka message payload is empty.');
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new TransportException('Kafka payload has invalid JSON envelope.');
        }

        $messageId = is_string($decoded['id'] ?? null) ? $decoded['id'] : uniqid('kafka-msg-', true);
        $envelope = $this->serializer->decode($this->toEncodedEnvelope($decoded['envelope'] ?? null))
            ->with(new KafkaReceivedStamp($message))
            ->with(new TransportMessageIdStamp($messageId));

        return [$envelope];
    }

    public function ack(Envelope $envelope): void
    {
        $this->commitOffset($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->commitOffset($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        $payload = json_encode([
            'id' => uniqid('kafka-msg-', true),
            'envelope' => $this->serializer->encode($envelope),
        ]);
        if (!is_string($payload)) {
            throw new TransportException('Unable to encode Kafka transport payload.');
        }

        /** @var \RdKafka\Producer $producer */
        $producer = $this->producer();
        $topic = $producer->newTopic($this->topic);
        $partitionUa = defined('RD_KAFKA_PARTITION_UA') ? (int) constant('RD_KAFKA_PARTITION_UA') : -1;
        $topic->produce($partitionUa, 0, $payload);
        $producer->poll(0);
        $flushResult = $producer->flush(10_000);

        if ($flushResult !== 0) {
            throw new TransportException('Kafka producer flush failed.');
        }

        return $envelope;
    }

    private function producer(): object
    {
        if ($this->producer !== null) {
            return $this->producer;
        }

        $producerClass = '\RdKafka\Producer';
        if (!class_exists($producerClass)) {
            throw new TransportException('php-rdkafka extension is not installed.');
        }

        /** @var \RdKafka\Producer $producer */
        $producer = new $producerClass();
        $producer->addBrokers($this->brokers);
        $this->producer = $producer;

        return $this->producer;
    }

    private function consumer(): object
    {
        if ($this->consumer !== null) {
            return $this->consumer;
        }

        $confClass = '\RdKafka\Conf';
        $consumerClass = '\RdKafka\KafkaConsumer';
        if (!class_exists($confClass) || !class_exists($consumerClass)) {
            throw new TransportException('php-rdkafka extension is not installed.');
        }

        $conf = new $confClass();
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->set('group.id', $this->groupId);
        $conf->set('enable.auto.commit', 'false');
        $conf->set('auto.offset.reset', 'earliest');

        /** @var \RdKafka\KafkaConsumer $consumer */
        $consumer = new $consumerClass($conf);
        $consumer->subscribe([$this->topic]);
        $this->consumer = $consumer;

        return $this->consumer;
    }

    private function rdKafkaErrNoError(): int
    {
        return defined('RD_KAFKA_RESP_ERR_NO_ERROR') ? (int) constant('RD_KAFKA_RESP_ERR_NO_ERROR') : 0;
    }

    private function rdKafkaErrPartitionEof(): int
    {
        return defined('RD_KAFKA_RESP_ERR__PARTITION_EOF') ? (int) constant('RD_KAFKA_RESP_ERR__PARTITION_EOF') : -191;
    }

    private function rdKafkaErrTimedOut(): int
    {
        return defined('RD_KAFKA_RESP_ERR__TIMED_OUT') ? (int) constant('RD_KAFKA_RESP_ERR__TIMED_OUT') : -185;
    }

    /**
     * @param mixed $payload
     * @return array{body: string, headers?: array<string, string>}
     */
    private function toEncodedEnvelope(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new TransportException('Invalid Kafka encoded envelope payload.');
        }

        $body = $payload['body'] ?? null;
        if (!is_string($body)) {
            throw new TransportException('Invalid Kafka encoded envelope body.');
        }

        $headers = $payload['headers'] ?? null;
        if ($headers === null) {
            return ['body' => $body];
        }

        if (!is_array($headers)) {
            throw new TransportException('Invalid Kafka encoded envelope headers.');
        }

        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new TransportException('Invalid Kafka encoded envelope header entry.');
            }
            $normalizedHeaders[$key] = $value;
        }

        return [
            'body' => $body,
            'headers' => $normalizedHeaders,
        ];
    }

    private function commitOffset(Envelope $envelope): void
    {
        $stamp = $envelope->last(KafkaReceivedStamp::class);
        if (!$stamp instanceof KafkaReceivedStamp) {
            return;
        }

        $consumer = $this->consumer();
        if (!method_exists($consumer, 'commit')) {
            return;
        }

        $consumer->commit($stamp->message);
    }
}
