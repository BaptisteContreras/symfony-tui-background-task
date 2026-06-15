# symfony-tui-background-task

Background task runner for PHP CLI/TUI applications. Spawn a worker subprocess, stream progress events back via JSON, and display them in a terminal UI widget.

## Requirements

- PHP 8.4+
- `revolt/event-loop` ^1.0
- `symfony/event-dispatcher` ^7.0 || ^8.0
- `symfony/tui` ^8.0 (for `BackgroundTaskWidget` only)

## Installation

```bash
composer require bapt/symfony-tui-background-task
```

## How it works

The library follows a two-process model:

1. **Runner** – your main app calls `BackgroundProcess::start()`. It spawns the worker as a subprocess, sends the payload as JSON on stdin, and polls stdout via the EventLoop.
2. **Worker** – a separate PHP script that reads the JSON payload from stdin and writes JSON events to stdout, one per line.

The runner dispatches Symfony events based on the lines it reads, which your app listens to and reacts to.

## Worker protocol

A worker reads its payload from stdin and writes JSON lines to stdout:

```php
// Read payload
$params = json_decode(file_get_contents('php://stdin'), true);

// Report progress (any JSON object without type=done or type=error)
echo json_encode(['type' => 'progress', 'step' => 1, 'total' => 3]) . "\n";

// Signal success
echo json_encode(['type' => 'done']) . "\n";

// Or signal failure
echo json_encode(['type' => 'error', 'message' => 'Something went wrong']) . "\n";
```

Any JSON line with `type=done` dispatches `BackgroundTaskCompletedEvent`.
Any JSON line with `type=error` dispatches `BackgroundTaskFailedEvent`.
All other lines dispatch `BackgroundTaskProgressEvent` with the raw data.

## Usage

### BackgroundProcess

The low-level single-task runner:

```php
use Revolt\EventLoop;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TuiBackground\BackgroundTask;
use TuiBackground\Event\BackgroundTaskCompletedEvent;
use TuiBackground\Event\BackgroundTaskFailedEvent;
use TuiBackground\Event\BackgroundTaskProgressEvent;

$dispatcher = new EventDispatcher();

$dispatcher->addListener(BackgroundTaskProgressEvent::class, function (BackgroundTaskProgressEvent $e): void {
    printf("[%d/%d] %s\n", $e->data['step'], $e->data['total'], $e->data['label']);
});

$dispatcher->addListener(BackgroundTaskCompletedEvent::class, function (BackgroundTaskCompletedEvent $e): void {
    echo "Done!\n";
    EventLoop::getDriver()->stop();
});

$dispatcher->addListener(BackgroundTaskFailedEvent::class, function (BackgroundTaskFailedEvent $e): void {
    fprintf(STDERR, "Error: %s\n", $e->message);
    EventLoop::getDriver()->stop();
});

$process = new BackgroundTask(
    command: [PHP_BINARY, __DIR__ . '/worker.php'],
    dispatcher: $dispatcher,
    timeoutSeconds: 120,
);
$process->start(['key' => 'value']);

EventLoop::run();
```

`BackgroundProcess` is single-use: calling `start()` twice throws `\LogicException`.

### BackgroundProcessManager

Manages multiple concurrent background tasks with a shared event dispatcher:

```php
use Symfony\Component\EventDispatcher\EventDispatcher;use TuiBackground\Event\BackgroundTaskStartedEvent;use TuiBackground\Event\BackgroundTaskStoppedEvent;use TuiBackground\Manager\BackgroundTaskManager;

$globalDispatcher = new EventDispatcher();

// Track lifecycle on the global dispatcher
$globalDispatcher->addListener(BackgroundTaskStartedEvent::class, function (BackgroundTaskStartedEvent $e): void {
    printf("Started: %s (%s)\n", $e->label, $e->id);
});
$globalDispatcher->addListener(BackgroundTaskStoppedEvent::class, function (BackgroundTaskStoppedEvent $e): void {
    printf("Stopped: %s (%s)\n", $e->label, $e->id);
});

$manager = new BackgroundTaskManager($globalDispatcher);

// Start a task – returns a TaskId
$id = $manager->start(
    label: 'Export CSV',
    command: [PHP_BINARY, __DIR__ . '/worker.php'],
    payload: ['outputPath' => '/tmp/export.csv'],
    timeoutSeconds: 300,
);

// Register per-task listeners by event type
$manager->onProcessProgress($id, function (BackgroundTaskProgressEvent $e): void { /* ... */ });
$manager->onProcessCompleted($id, function (BackgroundTaskCompletedEvent $e): void { /* ... */ });
$manager->onProcessFailed($id, function (BackgroundTaskFailedEvent $e): void { /* ... */ });

// Cancel the task at any time
$manager->stop($id);
```

### BackgroundTaskWidget (TUI)

Displays a step list with a spinner inside a `symfony/tui` application.

Implement `RendererInterface` to bridge the widget to your TUI's render cycle:

```php
use TuiBackground\Tui\RendererInterface;

$renderer = new class($tui) implements RendererInterface {
    public function __construct(private readonly Tui $tui) {}
    public function requestPageRender(): void { $this->tui->requestRender(); }
};
```

Then create the widget:

```php
use TuiBackground\Tui\BackgroundTaskWidget;

$taskWidget = new BackgroundTaskWidget($renderer, 'Exporting data', [
    ['key' => 'fetch',  'label' => 'Fetching records'],
    ['key' => 'build',  'label' => 'Building export'],
    ['key' => 'write',  'label' => 'Writing file'],
]);

$root->add($taskWidget->getWidget());

// Drive the widget from your event listeners:
$manager->onProcessProgress($id, function (BackgroundTaskProgressEvent $e) use ($taskWidget): void {
    if ('fetched' === ($e->data['type'] ?? '')) {
        $taskWidget->setStepDone('fetch');
        $taskWidget->setStepRunning('build');
    }
});
$manager->onProcessCompleted($id, function (BackgroundTaskCompletedEvent $e) use ($taskWidget): void {
    $taskWidget->setStepDone('write');
    $taskWidget->setComplete('Export complete!');
});
$manager->onProcessFailed($id, function (BackgroundTaskFailedEvent $e) use ($taskWidget): void {
    $taskWidget->setFailed($e->message);
});
```

## Examples

```bash
make build    # build the dev image (requires Docker)
make install  # composer install

make example-01   # basic: BackgroundTask + EventDispatcher, stdout output
make example-02   # TUI: BackgroundTaskWidget in a terminal UI (needs a real TTY)
```

## Dev

```bash
make cs     # PHP CS Fixer
make stan   # PHPStan (level max)
make check  # cs + stan
```

The Dockerfile in `dev/` uses the official `php:8.4-cli` image by default.
Build with `--build-arg PHP_IMAGE=your-registry/php:8.4-cli` to use a private mirror.
