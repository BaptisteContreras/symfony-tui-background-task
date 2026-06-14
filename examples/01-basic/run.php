<?php

/**
 * Example 01 – Basic background process (no TUI).
 *
 * Shows how to use BackgroundTask with an EventDispatcher
 * to run a worker script and print progress to the terminal.
 *
 * The EventLoop drives polling; the worker outputs JSON lines.
 */

require __DIR__.'/../../vendor/autoload.php';

use Revolt\EventLoop;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TuiBackground\BackgroundTask;
use TuiBackground\Event\BackgroundTaskCompletedEvent;
use TuiBackground\Event\BackgroundTaskFailedEvent;
use TuiBackground\Event\BackgroundTaskProgressEvent;

$dispatcher = new EventDispatcher();

$dispatcher->addListener(BackgroundTaskProgressEvent::class, function (BackgroundTaskProgressEvent $e): void {
    $step = is_int($e->data['step'] ?? null) ? $e->data['step'] : 0;
    $total = is_int($e->data['total'] ?? null) ? $e->data['total'] : 0;
    $label = is_string($e->data['label'] ?? null) ? $e->data['label'] : '';
    printf("  [%d/%d] %s\n", $step, $total, $label);
});

$dispatcher->addListener(BackgroundTaskCompletedEvent::class, function (BackgroundTaskCompletedEvent $e): void {
    echo "✓ Done\n";
    EventLoop::getDriver()->stop();
});

$dispatcher->addListener(BackgroundTaskFailedEvent::class, function (BackgroundTaskFailedEvent $e): void {
    fprintf(STDERR, "✗ Error: %s\n", $e->message);
    EventLoop::getDriver()->stop();
});

$process = new BackgroundTask(
    command: [PHP_BINARY, __DIR__.'/worker.php'],
    dispatcher: $dispatcher,
);

echo "Starting background task...\n";
$process->start(['steps' => 4]);

EventLoop::run();
