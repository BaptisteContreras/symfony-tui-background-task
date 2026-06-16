<?php

namespace TuiBackground\Worker;

use TuiBackground\EventType;
use TuiBackground\Exception\InvalidPayloadException;

final class WorkerTask
{
    public function __construct(private readonly WorkerSocket $socket)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function readPayload(): array
    {
        $raw = $this->socket->read();

        if ('' === $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidPayloadException($e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $event
     */
    public function emit(array $event): void
    {
        try {
            $line = json_encode($event, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidPayloadException($e);
        }

        $this->socket->writeLine($line);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function complete(?string $message = null, array $data = []): void
    {
        $event = ['type' => EventType::Done->value];

        if (null !== $message) {
            $event['message'] = $message;
        }

        if ([] !== $data) {
            $event['data'] = $data;
        }

        $this->emit($event);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fail(?string $message = null, array $data = []): void
    {
        $event = ['type' => EventType::Error->value];

        if (null !== $message) {
            $event['message'] = $message;
        }

        if ([] !== $data) {
            $event['data'] = $data;
        }

        $this->emit($event);
    }
}
