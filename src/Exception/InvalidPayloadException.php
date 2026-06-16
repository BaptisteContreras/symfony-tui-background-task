<?php

namespace TuiBackground\Exception;

final class InvalidPayloadException extends \InvalidArgumentException
{
    public function __construct(\JsonException $previous)
    {
        parent::__construct('Failed to encode payload: '.$previous->getMessage(), previous: $previous);
    }
}
