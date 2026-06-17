<?php

namespace TuiBackground\Worker\Factory;

use TuiBackground\Worker\WorkerTask;

interface WorkerTaskFactoryInterface
{
    public function create(): WorkerTask;
}
