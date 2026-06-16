<?php

namespace TuiBackground\Worker;

final class WorkerSocket
{
    /**
     * @param resource $input
     * @param resource $output
     */
    public function __construct(
        private mixed $input,
        private mixed $output,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function readPayload(): array
    {
        $raw = (string) stream_get_contents($this->input);

        if ('' === $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $event
     */
    public function emit(array $event): void
    {
        $this->write($event);
    }

    public function terminate(?string $error = null): void
    {
        if (null !== $error) {
            $this->write(['type' => 'error', 'message' => $error]);
        } else {
            $this->write(['type' => 'done']);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function write(array $event): void
    {
        fwrite($this->output, json_encode($event)."\n");
        fflush($this->output);
    }
}
