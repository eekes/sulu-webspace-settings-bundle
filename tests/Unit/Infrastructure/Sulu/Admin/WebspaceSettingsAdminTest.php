<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Infrastructure\Sulu\Admin;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistry;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\WebspaceSettingsAdmin;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Our toolbar is built from this config, so an area the user may not open must not appear in it.
 * The endpoints reject it either way, but a select full of dead options is its own bug.
 */
class WebspaceSettingsAdminTest extends TestCase
{
    public function testExposesEveryWebspaceAnAreaIsAvailableAndAllowedIn(): void
    {
        $admin = $this->createAdmin(
            ['website', 'shop'],
            ['social' => ['*'], 'shop_settings' => ['shop']],
            static fn (string $context) => true,
        );

        $areas = $admin->getConfig()['areas'];

        $this->assertSame(['website', 'shop'], $areas[0]['permittedWebspaces']);
        $this->assertSame(
            ['shop'],
            $areas[1]['permittedWebspaces'],
            'An area declared for one webspace is never offered in another',
        );
    }

    public function testHidesAnAreaTheUserMayNotOpen(): void
    {
        $admin = $this->createAdmin(
            ['website', 'shop'],
            ['social' => ['*'], 'shop_settings' => ['*']],
            // the editor may not touch the shop settings of the shop webspace
            static fn (string $context) => 'sulu.webspaces.shop.settings_shop_settings' !== $context,
        );

        $areas = $admin->getConfig()['areas'];

        $this->assertSame(['website', 'shop'], $areas[0]['permittedWebspaces']);
        $this->assertSame(['website'], $areas[1]['permittedWebspaces']);
    }

    public function testHidesEveryAreaOfAWebspaceTheUserMayNotSee(): void
    {
        $admin = $this->createAdmin(
            ['website', 'shop'],
            ['social' => ['*'], 'shop_settings' => ['*']],
            // the umbrella context gates the whole webspace
            static fn (string $context) => 'sulu.webspaces.shop.settings' !== $context,
        );

        $areas = $admin->getConfig()['areas'];

        $this->assertSame(['website'], $areas[0]['permittedWebspaces']);
        $this->assertSame(['website'], $areas[1]['permittedWebspaces']);
    }

    /**
     * The admin config is served to every logged-in user, so an area nobody-in-this-account may
     * open is not just unselectable - its key and title have no business being in the payload.
     */
    public function testLeavesOutAnAreaTheUserCannotOpenAnywhere(): void
    {
        $admin = $this->createAdmin(
            ['website', 'shop'],
            ['social' => ['*'], 'shop_settings' => ['*']],
            static fn (string $context) => !\str_ends_with($context, '.settings_shop_settings'),
        );

        $this->assertSame(['social'], \array_column($admin->getConfig()['areas'], 'key'));
    }

    public function testHidesTheActivityButtonWithoutTheActivityPermission(): void
    {
        $admin = $this->createAdmin(['website'], ['social' => ['*']], static fn (string $context) => true);

        $this->assertFalse($admin->getConfig()['activity']);
    }

    /**
     * @param list<string> $webspaceKeys
     * @param array<string, string[]> $areas area key to the webspaces it is declared for
     * @param \Closure(string): bool $hasPermission
     */
    private function createAdmin(array $webspaceKeys, array $areas, \Closure $hasPermission): WebspaceSettingsAdmin
    {
        $securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')
            ->willReturnCallback(static fn (string $context) => $hasPermission($context));

        $configs = [];
        $order = 0;
        foreach ($areas as $areaKey => $webspaces) {
            $configs[$areaKey] = (new SettingsArea(
                $areaKey,
                'title',
                $areaKey,
                $webspaces,
                $order++,
                null,
            ))->toArray();
        }

        $registry = new SettingsAreaRegistry($configs);

        $webspaces = [];
        foreach ($webspaceKeys as $webspaceKey) {
            $webspace = new Webspace();
            $webspace->setKey($webspaceKey);
            $webspaces[$webspaceKey] = $webspace;
        }

        $webspaceManager = $this->createMock(WebspaceManagerInterface::class);
        $webspaceManager->method('getWebspaceCollection')->willReturn(new WebspaceCollection($webspaces));

        return new WebspaceSettingsAdmin(
            $this->createMock(ViewBuilderFactoryInterface::class),
            $this->createMock(ActivityViewBuilderFactoryInterface::class),
            $securityChecker,
            new SettingsSecurityChecker($securityChecker, $registry),
            $webspaceManager,
            $registry,
            new SettingsAreaLocalization(
                $this->createMock(FormMetadataLoaderInterface::class),
                $this->createMock(TranslatorInterface::class),
            ),
        );
    }
}
