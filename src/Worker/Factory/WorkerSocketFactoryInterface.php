<?php

namespace TuiBackground\Worker\Factory;

use TuiBackground\Worker\WorkerSocket;

interface WorkerSocketFactoryInterface
{
    public function create(): WorkerSocket;
}
