<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Application;

use Eekes\SuluWebspaceSettingsBundle\SuluWebspaceSettingsBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * The bundle tests itself against this minimal application, never against a surrounding Sulu
 * skeleton: a suite that needs one cannot be run in CI or by an outside contributor.
 */
class Kernel extends SuluTestKernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();

        yield new SuluWebspaceSettingsBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(__DIR__ . '/config/config.yaml');
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/var/cache/' . $this->getContext() . '/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return __DIR__ . '/var/log';
    }
}
