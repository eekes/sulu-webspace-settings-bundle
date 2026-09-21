<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Symfony\Command;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes settings records whose area is no longer declared, or whose webspace no longer exists.
 *
 * Nothing removes them on its own: an area is declared in code and a webspace in XML, and neither
 * disappearing is an event the bundle can see. The rows are then invisible in the admin and still
 * in the database, which is exactly the state that makes a later `doctrine:migrations:diff`
 * confusing.
 *
 * Reports by default and only deletes with `--force`, because the records hold content that
 * cannot be recovered and an area is quite often gone only because a branch is checked out.
 */
#[AsCommand(
    name: 'sulu:webspace-settings:prune',
    description: 'Report, and with --force delete, settings records of areas or webspaces that no longer exist',
)]
final class PruneSettingsCommand extends Command
{
    public function __construct(
        private readonly WebspaceSettingsRepositoryInterface $repository,
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete the records');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = true === $input->getOption('force');

        $orphans = [];

        foreach ($this->repository->findAll() as $settings) {
            $reason = $this->orphanReason($settings);

            if (null !== $reason) {
                $orphans[] = [$settings, $reason];
            }
        }

        if ([] === $orphans) {
            $io->success('No orphaned settings records.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Webspace', 'Area', 'Reason'],
            \array_map(
                static fn (array $orphan) => [
                    $orphan[0]->getWebspaceKey(),
                    $orphan[0]->getArea(),
                    $orphan[1],
                ],
                $orphans,
            ),
        );

        if (!$force) {
            $io->warning(\sprintf(
                '%d orphaned record(s) found. Re-run with --force to delete them.',
                \count($orphans),
            ));

            return Command::SUCCESS;
        }

        foreach ($orphans as [$settings, $reason]) {
            $this->repository->remove($settings);
        }

        $this->repository->flush();

        $io->success(\sprintf('Deleted %d orphaned record(s).', \count($orphans)));

        return Command::SUCCESS;
    }

    private function orphanReason(WebspaceSettingsInterface $settings): ?string
    {
        if (null === $this->webspaceManager->findWebspaceByKey($settings->getWebspaceKey())) {
            return 'the webspace is not configured';
        }

        if (!$this->areaRegistry->hasArea($settings->getArea())) {
            return 'no class declares this area';
        }

        if (!$this->areaRegistry->getArea($settings->getArea())->isAvailableForWebspace($settings->getWebspaceKey())) {
            return 'the area is no longer declared for this webspace';
        }

        return null;
    }
}
