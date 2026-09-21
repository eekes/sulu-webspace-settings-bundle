<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Domain\Model;

use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettings;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;

class WebspaceSettingsDimensionContentTest extends TestCase
{
    public function testUsesOneStageOnly(): void
    {
        // draft is the stage ContentPersister writes to; picking live would mean writing our own
        // publishing, which is out of scope
        $defaults = WebspaceSettingsDimensionContent::getDefaultDimensionAttributes();

        $this->assertSame(WebspaceSettingsDimensionContentInterface::STAGE, $defaults['stage']);
        $this->assertNull($defaults['locale']);
        $this->assertSame(DimensionContentInterface::CURRENT_VERSION, $defaults['version']);
    }

    public function testNeverLetsAStageLeakInFromACaller(): void
    {
        $attributes = WebspaceSettingsDimensionContent::getEffectiveDimensionAttributes([
            'locale' => 'nl',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]);

        $this->assertSame('nl', $attributes['locale']);
        $this->assertSame(WebspaceSettingsDimensionContentInterface::STAGE, $attributes['stage']);
    }

    public function testIgnoresUnknownDimensionAttributes(): void
    {
        $attributes = WebspaceSettingsDimensionContent::getEffectiveDimensionAttributes(['webspace' => 'website']);

        $this->assertArrayNotHasKey('webspace', $attributes);
    }

    public function testTemplateKeyFallsBackToTheArea(): void
    {
        $dimensionContent = new WebspaceSettingsDimensionContent(new WebspaceSettings('website', 'social'));

        $this->assertSame('social', $dimensionContent->getTemplateKey());

        $dimensionContent->setTemplateKey('social');

        $this->assertSame('social', $dimensionContent->getTemplateKey());
    }

    public function testExposesTheResourceAndTemplateType(): void
    {
        $settings = new WebspaceSettings('website', 'social');
        $dimensionContent = new WebspaceSettingsDimensionContent($settings);

        $this->assertSame($settings, $dimensionContent->getResource());
        $this->assertSame('webspace_settings', WebspaceSettingsDimensionContent::getResourceKey());
        $this->assertSame('webspace_settings', WebspaceSettingsDimensionContent::getTemplateType());
    }
}
