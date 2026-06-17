<?php

namespace TuiBackground;

use Revolt\EventLoop;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TuiBackground\Event\BackgroundTaskCompletedEvent;
use TuiBackground\Event\BackgroundTaskFailedEvent;
use TuiBackground\Event\BackgroundTaskProgressEvent;
use TuiBackground\Event\EventType;
use TuiBackground\Exception\BackgroundTaskAlreadyStartedException;
use TuiBackground\Exception\InvalidPayloadException;
use TuiBackground\Exception\LogicException;

final class BackgroundTask
{
    private ?TaskSocket $socket = null;
    private ?string $timerId = null;
    private bool $started = false;
    private float $startTime = 0.0;

    /**
     * @param non-empty-list<string> $command
     */
    public function __construct(
        private readonly array $command,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly int $timeoutSeconds = 120,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function start(array $payload): void
    {
        if ($this->started) {
            throw new BackgroundTaskAlreadyStartedException();
        }
        $this->started = true;

        try {
            $encoded = json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidPayloadException($e);
        }

        try {
            $socket = new TaskSocket($this->command);
            $socket->write($encoded);
        } catch (\RuntimeException $e) {
            $this->dispatcher->dispatch(new BackgroundTaskFailedEvent($e->getMessage()));

            return;
        }

        $this->socket = $socket;
        $this->startTime = microtime(true);
        $this->timerId = EventLoop::repeat(0.05, function (): void {
            $socket = $this->socket;
            if (null === $socket) {
                return;
            }

            while (null !== ($line = $socket->readLine())) {
                if ('' === $line) {
                    continue;
                }
                $event = json_decode($line, true);
                if (!is_array($event)) {
                    $this->terminate(new BackgroundTaskFailedEvent(sprintf('Worker sent malformed JSON: %s', $line)));

                    return;
                }
                /** @var array{type: string, sub_type?: string, message?: string, data?: array<string, mixed>} $event */
                if ($this->dispatchProgressOrTerminal($event)) {
                    return;
                }
            }

            if (microtime(true) - $this->startTime > $this->timeoutSeconds) {
                $this->terminate(new BackgroundTaskFailedEvent(sprintf('Worker timed out after %ds', $this->timeoutSeconds)));

                return;
            }

            if ($socket->isDone()) {
                $this->terminate(new BackgroundTaskFailedEvent('Worker process exited unexpectedly'));
            }
        });
    }

    public function kill(): void
    {
        if (null !== $this->timerId) {
            EventLoop::cancel($this->timerId);
            $this->timerId = null;
        }
        $this->socket?->close();
        $this->socket = null;
    }

    private function terminate(object $event): void
    {
        if (null === $this->timerId) {
            throw new LogicException('terminate() called without an active timer');
        }

        EventLoop::cancel($this->timerId);
        $this->timerId = null;
        $this->socket?->close();
        $this->socket = null;
        $this->dispatcher->dispatch($event);
    }

    /**
     * Dispatches the event to the appropriate listener based on its type.
     *
     * Returns true if the event was a terminal one (done or error), meaning
     * the caller should stop polling. Returns false for progress events.
     *
     * @param array{type: string, sub_type?: string, message?: string, data?: array<string, mixed>} $event
     */
    private function dispatchProgressOrTerminal(array $event): bool
    {
        $typeValue = $event['type'];
        $type = EventType::from($typeValue);

        if (EventType::Done === $type) {
            $this->terminate(new BackgroundTaskCompletedEvent());

            return true;
        }

        if (EventType::Error === $type) {
            $messageValue = $event['message'] ?? null;
            $message = is_string($messageValue) ? $messageValue : 'Unknown error';
            $this->terminate(new BackgroundTaskFailedEvent($message));

            return true;
        }

        $this->dispatcher->dispatch(new BackgroundTaskProgressEvent($event));

        return false;
    }
}
