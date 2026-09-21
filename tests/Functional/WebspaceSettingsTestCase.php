<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsManagerInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

abstract class WebspaceSettingsTestCase extends SuluTestCase
{
    private static bool $schemaCreated = false;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createAuthenticatedClient();

        if (!self::$schemaCreated) {
            self::createSchema();
            self::$schemaCreated = true;
        }

        static::purgeDatabase();
    }

    protected static function createSchema(): void
    {
        $entityManager = static::getEntityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * The console application of the kernel the test is already running against, so a command
     * sees the same database and the same container as the rest of the test.
     */
    protected static function createConsoleApplication(): Application
    {
        return new Application(static::$kernel ?? static::bootKernel());
    }

    protected static function getSettingsManager(): SettingsManagerInterface
    {
        /** @var SettingsManagerInterface $manager */
        $manager = static::getContainer()->get(SettingsManagerInterface::class);

        return $manager;
    }

    protected static function getSettings(): SettingsInterface
    {
        /** @var SettingsInterface $settings */
        $settings = static::getContainer()->get(SettingsInterface::class);

        return $settings;
    }
}
