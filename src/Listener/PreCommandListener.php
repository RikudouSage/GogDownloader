<?php

namespace App\Listener;

use App\Service\NewVersionChecker;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class PreCommandListener implements EventSubscriberInterface
{
    public function __construct(
        private NewVersionChecker $newVersionChecker,
        #[Autowire('%app.source_repository%')]
        private string $repository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleEvent',
        ];
    }

    public function onConsoleEvent(ConsoleCommandEvent $event): void
    {
        if (!$this->newVersionChecker->newVersionAvailable()) {
            return;
        }

        $output = $event->getOutput();
        $output->writeln("<fg=black;bg=yellow>There's a new version ({$this->newVersionChecker->getLatestVersion()}) available to download at {$this->repository}/releases/latest</>");
        $output->writeln("");
    }
}
