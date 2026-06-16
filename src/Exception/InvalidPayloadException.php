<?php

namespace TuiBackground\Exception;

final class InvalidPayloadException extends \InvalidArgumentException
{
    public function __construct(\JsonException $previous)
    {
        parent::__construct('Invalid payload: '.$previous->getMessage(), previous: $previous);
    }
}
