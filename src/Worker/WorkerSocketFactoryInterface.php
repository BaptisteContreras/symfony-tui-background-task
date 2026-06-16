<?php

namespace TuiBackground\Worker;

interface WorkerSocketFactoryInterface
{
    public function create(): WorkerSocket;
}
