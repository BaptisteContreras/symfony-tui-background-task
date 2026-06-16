<?php

namespace TuiBackground;

enum EventType: string
{
    case Progress = 'progress';
    case Done = 'done';
    case Error = 'error';
}
