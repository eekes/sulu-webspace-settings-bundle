<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Application\Command;

use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exists purely so the test suite can prove that resolving works without a request context.
 *
 * The PHPCR-free model was chosen so that a block and a smart content property resolve from a
 * console command; if this ever breaks, the premise of the bundle is broken.
 */
#[AsCommand(name: 'test:settings:dump')]
final class DumpSettingsCommand extends Command
{
    public function __construct(private readonly SettingsInterface $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('webspace', InputArgument::REQUIRED)
            ->addArgument('area', InputArgument::REQUIRED)
            ->addArgument('locale', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $webspace */
        $webspace = $input->getArgument('webspace');
        /** @var string $area */
        $area = $input->getArgument('area');
        /** @var string|null $locale */
        $locale = $input->getArgument('locale');

        $settings = $this->settings->forWebspace($webspace);

        if (null !== $locale) {
            $settings = $settings->forLocale($locale);
        }

        $output->writeln((string) \json_encode($settings->resolved($area)->all()));

        return Command::SUCCESS;
    }
}
