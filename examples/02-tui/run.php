<?php

/**
 * Example 02 – TUI with BackgroundTaskWidget.
 *
 * Shows how to integrate BackgroundTask and BackgroundTaskWidget
 * inside a symfony/tui application.
 *
 * RendererInterface is implemented inline using an anonymous class
 * wrapping $tui->requestRender().
 */

require __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use TuiBackground\BackgroundTask;
use TuiBackground\BackgroundTaskWidget;
use TuiBackground\Event\BackgroundTaskCompletedEvent;
use TuiBackground\Event\BackgroundTaskFailedEvent;
use TuiBackground\Event\BackgroundTaskProgressEvent;
use TuiBackground\RendererInterface;

// --- TUI setup ---

$root = new ContainerWidget();
$root->setStyle(new Style(direction: Direction::Vertical));

$tui = new Tui();
$tui->add($root);
$tui->addListener(static function (InputEvent $event) use ($tui, &$process): void {
    if ("\x03" === $event->getData() || "\r" === $event->getData()) {
        $process?->kill();
        $tui->stop();
    }
});

// Wrap Tui in RendererInterface so BackgroundTaskWidget can request redraws.
$renderer = new class($tui) implements RendererInterface {
    public function __construct(private readonly Tui $tui)
    {
    }

    public function requestPageRender(): void
    {
        $this->tui->requestRender();
    }
};

// --- Widget ---

$taskWidget = new BackgroundTaskWidget($renderer, 'Running task', [
    ['key' => 'init', 'label' => 'Initializing'],
    ['key' => 'process', 'label' => 'Processing'],
    ['key' => 'finalize', 'label' => 'Finalizing'],
]);
$root->add($taskWidget->getWidget());

// --- Event wiring ---

$dispatcher = new EventDispatcher();

$dispatcher->addListener(BackgroundTaskProgressEvent::class, function (BackgroundTaskProgressEvent $e) use ($taskWidget): void {
    $type = is_string($e->data['type'] ?? null) ? $e->data['type'] : '';

    if ('initialized' === $type) {
        $taskWidget->setStepDone('init', 'Initialized');
        $taskWidget->setStepRunning('process');
    } elseif ('processing' === $type) {
        $step = is_int($e->data['step'] ?? null) ? $e->data['step'] : 0;
        $total = is_int($e->data['total'] ?? null) ? $e->data['total'] : 0;
        $taskWidget->setStepRunning('process', sprintf('Processing (%d/%d)', $step, $total));
    } elseif ('finalized' === $type) {
        $taskWidget->setStepDone('process', 'Processed');
        $taskWidget->setStepRunning('finalize');
    }
});

$dispatcher->addListener(BackgroundTaskCompletedEvent::class, function (BackgroundTaskCompletedEvent $e) use ($taskWidget): void {
    $taskWidget->setStepDone('finalize', 'Finalized');
    $taskWidget->setComplete('', 'All done! Press Ctrl+C or Enter to exit.');
});

$dispatcher->addListener(BackgroundTaskFailedEvent::class, function (BackgroundTaskFailedEvent $e) use ($taskWidget): void {
    $taskWidget->setFailed($e->message);
});

// --- Start ---

$process = new BackgroundTask(
    command: [PHP_BINARY, __DIR__.'/worker.php'],
    dispatcher: $dispatcher,
);

$process->start([]);
$taskWidget->setStepRunning('init');

$tui->run();
