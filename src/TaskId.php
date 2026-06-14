<?php

namespace TuiBackground;

final class TaskId
{
    private function __construct(public readonly string $value)
    {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(8)));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
