<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Infrastructure\Sulu\Admin;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistry;
use Eekes\SuluWebspaceSettingsBundle\Application\Security\AreaPermissionMode;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\WebspaceSettingsAdmin;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;

class SettingsSecurityCheckerTest extends TestCase
{
    public function testUsesTheUmbrellaContextOnlyWithASingleArea(): void
    {
        $checker = $this->createSecurityChecker(['social']);

        $this->assertNull($checker->resolveAreaSecurityContext('website', 'social'));
    }

    public function testAddsAPerAreaContextWithSeveralAreas(): void
    {
        $checker = $this->createSecurityChecker(['social', 'contact']);

        $this->assertSame(
            'sulu.webspaces.website.settings_social',
            $checker->resolveAreaSecurityContext('website', 'social'),
        );
    }

    public function testChecksTheUmbrellaContextAsWellAsThePerAreaContext(): void
    {
        $checkedContexts = [];

        $securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $securityChecker->method('checkPermission')
            ->willReturnCallback(static function (SecurityCondition $condition) use (&$checkedContexts): bool {
                $checkedContexts[] = $condition->getSecurityContext();

                return true;
            });

        $checker = new SettingsSecurityChecker($securityChecker, $this->createRegistry(['social', 'contact']));
        $checker->checkPermission('website', 'social', PermissionTypes::EDIT);

        $this->assertSame(
            ['sulu.webspaces.website.settings', 'sulu.webspaces.website.settings_social'],
            $checkedContexts,
            'The umbrella context gates the endpoints and is always required',
        );
    }

    /**
     * The default starts handing out per-area contexts at the second area, which means the deploy
     * that adds it changes the shape of the permission set. "always" is the way out for a project
     * that expects to grow.
     */
    public function testAlwaysModeUsesAPerAreaContextWithASingleArea(): void
    {
        $checker = $this->createSecurityChecker(['social'], AreaPermissionMode::ALWAYS);

        $this->assertTrue($checker->usesAreaSecurityContexts());
        $this->assertSame(
            'sulu.webspaces.website.settings_social',
            $checker->resolveAreaSecurityContext('website', 'social'),
        );
    }

    public function testNeverModeKeepsOnePermissionPerWebspace(): void
    {
        $checker = $this->createSecurityChecker(['social', 'contact'], AreaPermissionMode::NEVER);

        $this->assertFalse($checker->usesAreaSecurityContexts());
        $this->assertNull($checker->resolveAreaSecurityContext('website', 'social'));
    }

    public function testAutoModeFollowsTheAreaCount(): void
    {
        $this->assertFalse($this->createSecurityChecker(['social'])->usesAreaSecurityContexts());
        $this->assertTrue($this->createSecurityChecker(['social', 'contact'])->usesAreaSecurityContexts());
    }

    public function testSecurityContextNames(): void
    {
        $this->assertSame('sulu.webspaces.website.settings', WebspaceSettingsAdmin::getSecurityContext('website'));
        $this->assertSame(
            'sulu.webspaces.website.settings_social',
            WebspaceSettingsAdmin::getAreaSecurityContext('website', 'social'),
        );
    }

    /**
     * @param list<string> $areaKeys
     */
    private function createSecurityChecker(
        array $areaKeys,
        AreaPermissionMode $mode = AreaPermissionMode::AUTO,
    ): SettingsSecurityChecker {
        return new SettingsSecurityChecker(
            $this->createMock(SecurityCheckerInterface::class),
            $this->createRegistry($areaKeys),
            $mode,
        );
    }

    /**
     * @param list<string> $areaKeys
     */
    private function createRegistry(array $areaKeys): SettingsAreaRegistry
    {
        $configs = [];

        foreach ($areaKeys as $index => $areaKey) {
            $configs[$areaKey] = (new SettingsArea($areaKey, 'title', $areaKey, ['*'], $index, null))->toArray();
        }

        return new SettingsAreaRegistry($configs);
    }
}
