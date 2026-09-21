<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettings;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * An area lives in code and a webspace in XML, so neither disappearing is something the bundle
 * can notice. The rows stay behind, invisible in the admin and still in the database.
 */
class SettingsPruneCommandTest extends WebspaceSettingsTestCase
{
    public function testReportsNothingWhenEveryRecordStillBelongsSomewhere(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $tester = $this->execute();

        $this->assertStringContainsString('No orphaned settings records', $tester->getDisplay());
    }

    public function testReportsButKeepsOrphansWithoutForce(): void
    {
        $this->createOrphans();

        $tester = $this->execute();
        $display = $tester->getDisplay();

        $this->assertStringContainsString('no class declares this area', $display);
        $this->assertStringContainsString('the webspace is not configured', $display);
        $this->assertStringContainsString('the area is no longer declared for this webspace', $display);
        $this->assertStringContainsString('Re-run with --force', $display);

        $this->assertSame(3, $this->countRecords(), 'Content that cannot be recovered is never deleted by a report');
    }

    public function testDeletesOrphansWithForce(): void
    {
        $this->createOrphans();
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $tester = $this->execute(['--force' => true]);

        $this->assertStringContainsString('Deleted 3 orphaned record(s)', $tester->getDisplay());
        $this->assertSame(1, $this->countRecords(), 'The record that still belongs somewhere stays');
    }

    /**
     * One of each reason the command knows about.
     */
    private function createOrphans(): void
    {
        $entityManager = static::getEntityManager();

        $entityManager->persist(new WebspaceSettings('website', 'removed-area'));
        $entityManager->persist(new WebspaceSettings('removed-webspace', 'social'));
        // "shop" is declared for the shop webspace only
        $entityManager->persist(new WebspaceSettings('website', 'shop'));

        $entityManager->flush();
        $entityManager->clear();
    }

    private function countRecords(): int
    {
        return (int) static::getEntityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM ws_settings');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input = []): CommandTester
    {
        $command = static::createConsoleApplication()->find('sulu:webspace-settings:prune');

        $tester = new CommandTester($command);
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }
}
