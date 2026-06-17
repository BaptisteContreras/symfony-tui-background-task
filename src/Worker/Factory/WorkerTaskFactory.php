<?php

namespace TuiBackground\Worker\Factory;

use TuiBackground\Worker\WorkerTask;

final class WorkerTaskFactory implements WorkerTaskFactoryInterface
{
    public function __construct(private readonly WorkerSocketFactoryInterface $socketFactory)
    {
    }

    public function create(): WorkerTask
    {
        return new WorkerTask($this->socketFactory->create());
    }
}
