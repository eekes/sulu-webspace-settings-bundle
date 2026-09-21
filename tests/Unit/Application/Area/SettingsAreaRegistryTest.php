<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistry;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotAvailableException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\UnknownSettingsModelException;
use Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\ShopSettings;
use Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\SocialSettings;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type SettingsAreaConfig from SettingsArea
 */
class SettingsAreaRegistryTest extends TestCase
{
    public function testOrdersByOrderThenKey(): void
    {
        $registry = new SettingsAreaRegistry([
            'zeta' => $this->config('zeta', order: 10),
            'shop' => $this->config('shop', order: 30),
            'alpha' => $this->config('alpha', order: 10),
        ]);

        $this->assertSame(['alpha', 'zeta', 'shop'], \array_keys($registry->getAreas()));
        $this->assertSame('alpha', $registry->getDefaultAreaKey());
    }

    public function testDefaultAreaKeyIsNullWithoutAreas(): void
    {
        $this->assertNull((new SettingsAreaRegistry())->getDefaultAreaKey());
    }

    /**
     * The first area overall may be declared for other webspaces only, and opening a webspace on
     * an area it does not have is a 404 where the editor expects a form.
     */
    public function testDefaultAreaKeyIsResolvedPerWebspace(): void
    {
        $registry = new SettingsAreaRegistry([
            'shop' => $this->config('shop', webspaces: ['shop'], order: 10),
            'social' => $this->config('social', order: 20),
        ]);

        $this->assertSame('shop', $registry->getDefaultAreaKey());
        $this->assertSame('shop', $registry->getDefaultAreaKeyForWebspace('shop'));
        $this->assertSame('social', $registry->getDefaultAreaKeyForWebspace('website'));
    }

    public function testDefaultAreaKeyForWebspaceIsNullWhenTheWebspaceHasNoArea(): void
    {
        $registry = new SettingsAreaRegistry(['shop' => $this->config('shop', webspaces: ['shop'])]);

        $this->assertNull($registry->getDefaultAreaKeyForWebspace('website'));
    }

    public function testGetAreaThrowsForUnknownKey(): void
    {
        $registry = new SettingsAreaRegistry(['social' => $this->config('social')]);

        $this->expectException(SettingsAreaNotFoundException::class);
        $this->expectExceptionMessage('social');

        $registry->getArea('nope');
    }

    public function testGetAreasForWebspaceFilters(): void
    {
        $registry = new SettingsAreaRegistry([
            'social' => $this->config('social', order: 10),
            'shop' => $this->config('shop', webspaces: ['shop'], order: 20),
        ]);

        $this->assertSame(['social'], \array_keys($registry->getAreasForWebspace('website')));
        $this->assertSame(['social', 'shop'], \array_keys($registry->getAreasForWebspace('shop')));
    }

    public function testGetAreaForWebspaceRejectsAreaOfAnotherWebspace(): void
    {
        $registry = new SettingsAreaRegistry(['shop' => $this->config('shop', webspaces: ['shop'])]);

        $this->expectException(SettingsAreaNotAvailableException::class);

        $registry->getAreaForWebspace('shop', 'website');
    }

    public function testGetAreaForModel(): void
    {
        $registry = new SettingsAreaRegistry([
            'social' => $this->config('social', model: SocialSettings::class),
        ]);

        $this->assertSame('social', $registry->getAreaForModel(SocialSettings::class)->key);
    }

    public function testGetAreaForModelThrowsForUndeclaredClass(): void
    {
        $registry = new SettingsAreaRegistry([
            'social' => $this->config('social', model: SocialSettings::class),
        ]);

        $this->expectException(UnknownSettingsModelException::class);

        $registry->getAreaForModel(ShopSettings::class);
    }

    /**
     * @param string[] $webspaces
     * @param class-string|null $model
     *
     * @return SettingsAreaConfig
     */
    private function config(
        string $key,
        array $webspaces = ['*'],
        int $order = 0,
        ?string $model = null,
    ): array {
        return (new SettingsArea($key, 'title.' . $key, $key, $webspaces, $order, $model))->toArray();
    }
}
