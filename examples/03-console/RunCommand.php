<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use TuiBackground\Event\BackgroundTaskCompletedEvent;
use TuiBackground\Event\BackgroundTaskFailedEvent;
use TuiBackground\Event\BackgroundTaskProgressEvent;
use TuiBackground\Manager\BackgroundTaskManager;
use TuiBackground\Manager\TuiBackgroundTaskManager;
use TuiBackground\Widget\BackgroundTaskWidget;

#[AsCommand('run')]
final class RunCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = new ContainerWidget();
        $root->setStyle(new Style(direction: Direction::Vertical));

        $tui = new Tui();
        $tui->add($root);

        $manager = new TuiBackgroundTaskManager(
            new BackgroundTaskManager(new EventDispatcher()),
            $tui,
        );

        $taskWidget = new BackgroundTaskWidget($manager->getRenderer(), 'Running task', [
            ['key' => 'init', 'label' => 'Initializing'],
            ['key' => 'process', 'label' => 'Processing'],
            ['key' => 'finalize', 'label' => 'Finalizing'],
        ]);
        $root->add($taskWidget->getWidget());

        $taskId = $manager->start('Running task', [PHP_BINARY, __DIR__.'/console.php', 'worker'], []);
        $taskWidget->setStepRunning('init');

        $manager->onTaskProgress($taskId, function (BackgroundTaskProgressEvent $e) use ($taskWidget): void {
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

        $manager->onTaskCompleted($taskId, function (BackgroundTaskCompletedEvent $e) use ($taskWidget): void {
            $taskWidget->setStepDone('finalize', 'Finalized');
            $taskWidget->setComplete('', 'All done! Press Ctrl+C or Enter to exit.');
        });

        $manager->onTaskFailed($taskId, function (BackgroundTaskFailedEvent $e) use ($taskWidget): void {
            $taskWidget->setFailed($e->message);
        });

        $tui->addListener(static function (InputEvent $event) use ($tui, $manager, $taskId): void {
            if ("\x03" === $event->getData() || "\r" === $event->getData()) {
                $manager->stop($taskId);
                $tui->stop();
            }
        });

        $tui->run();

        return Command::SUCCESS;
    }
}
