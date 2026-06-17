<?php

namespace TuiBackground\Worker\Factory;

use TuiBackground\Worker\WorkerSocket;

final class StdioWorkerSocketFactory implements WorkerSocketFactoryInterface
{
    public function create(): WorkerSocket
    {
        return new WorkerSocket(\STDIN, \STDOUT);
    }
}
