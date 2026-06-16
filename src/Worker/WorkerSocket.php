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

    public function read(): string
    {
        return (string) stream_get_contents($this->input);
    }

    public function writeLine(string $line): void
    {
        fwrite($this->output, $line."\n");
        fflush($this->output);
    }
}
