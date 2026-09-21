<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use PHPUnit\Framework\TestCase;

class SettingsAreaTest extends TestCase
{
    public function testWildcardIsAvailableEverywhere(): void
    {
        $area = $this->createArea(['*']);

        $this->assertTrue($area->isAvailableForWebspace('website'));
        $this->assertTrue($area->isAvailableForWebspace('shop'));
    }

    public function testConcreteListRestrictsTheArea(): void
    {
        $area = $this->createArea(['shop', 'intranet']);

        $this->assertTrue($area->isAvailableForWebspace('shop'));
        $this->assertTrue($area->isAvailableForWebspace('intranet'));
        $this->assertFalse($area->isAvailableForWebspace('website'));
    }

    public function testEmptyListIsAvailableNowhere(): void
    {
        $this->assertFalse($this->createArea([])->isAvailableForWebspace('website'));
    }

    public function testRoundTripsThroughArray(): void
    {
        $area = $this->createArea(['shop']);

        $this->assertSame($area->toArray(), SettingsArea::fromArray($area->toArray())->toArray());
    }

    /**
     * @param string[] $webspaces
     */
    private function createArea(array $webspaces): SettingsArea
    {
        return new SettingsArea('social', 'app.settings.social', 'social', $webspaces, 10, null);
    }
}
