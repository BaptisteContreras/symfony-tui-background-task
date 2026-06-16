<?php

namespace TuiBackground\Worker;

final class StdioWorkerSocketFactory implements WorkerSocketFactoryInterface
{
    public function create(): WorkerSocket
    {
        return new WorkerSocket(\STDIN, \STDOUT);
    }
}
