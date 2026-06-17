<?php

/**
 * Example 03 – Symfony Console integration.
 *
 * Shows how to host RunCommand (TUI + background task) and WorkerCommand
 * (the background worker) inside a single Symfony Console Application.
 * Both commands share this entry point; the worker is spawned by RunCommand
 * as a subprocess via [PHP_BINARY, __FILE__, 'worker'].
 *
 * Run with: php examples/03-console/console.php run
 */

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/RunCommand.php';
require __DIR__.'/WorkerCommand.php';

use Symfony\Component\Console\Application;
use TuiBackground\Worker\Factory\StdioWorkerSocketFactory;
use TuiBackground\Worker\Factory\WorkerTaskFactory;

$app = new Application('tui-background-example', '1.0.0');
$app->addCommand(new RunCommand());
$app->addCommand(new WorkerCommandDemo(new WorkerTaskFactory(new StdioWorkerSocketFactory())));
$app->run();
