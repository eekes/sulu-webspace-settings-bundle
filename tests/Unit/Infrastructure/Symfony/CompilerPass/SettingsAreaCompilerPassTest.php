<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Infrastructure\Symfony\CompilerPass;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\AsSettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Symfony\CompilerPass\SettingsAreaCompilerPass;
use Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\ContactSettings;
use Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\ShopSettings;
use Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\SocialSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class SettingsAreaCompilerPassTest extends TestCase
{
    public function testCollectsTaggedAreasInOrder(): void
    {
        $container = $this->createContainer([ShopSettings::class, SocialSettings::class, ContactSettings::class]);

        (new SettingsAreaCompilerPass())->process($container);

        /** @var array<string, array<string, mixed>> $areas */
        $areas = $container->getParameter(SettingsAreaCompilerPass::AREAS_PARAMETER);

        $this->assertSame(['social', 'contact', 'shop'], \array_keys($areas));
        $this->assertSame([
            'key' => 'social',
            'title' => 'app.settings.social',
            'template' => 'social',
            'webspaces' => ['*'],
            'order' => 10,
            'group' => 'app.settings.group.marketing',
            'icon' => 'su-share',
            'model' => SocialSettings::class,
        ], $areas['social']);
    }

    /**
     * A translation key would be the obvious default, but the bundle can never ship a translation
     * for one built from the consumer's area key - the dropdown would read the key itself.
     */
    public function testDefaultsTemplateToTheAreaKeyAndTitleToAReadableLabel(): void
    {
        $container = $this->createContainer([MinimalSettings::class]);

        (new SettingsAreaCompilerPass())->process($container);

        /** @var array<string, array<string, mixed>> $areas */
        $areas = $container->getParameter(SettingsAreaCompilerPass::AREAS_PARAMETER);

        $this->assertSame('minimal', $areas['minimal']['template']);
        $this->assertSame('Minimal', $areas['minimal']['title']);
    }

    public function testHumanizesASeparatedAreaKey(): void
    {
        $this->assertSame('Social media', SettingsAreaCompilerPass::humanize('social_media'));
        $this->assertSame('Social media', SettingsAreaCompilerPass::humanize('social-media'));
        $this->assertSame('Social', SettingsAreaCompilerPass::humanize('social'));
    }

    /**
     * An area that names no group lands in one dropdown labelled "Settings", so a project with a
     * handful of areas never has to think about grouping at all.
     */
    public function testDefaultsGroupAndIconWhenTheAttributeNamesNeither(): void
    {
        $container = $this->createContainer([MinimalSettings::class]);

        (new SettingsAreaCompilerPass())->process($container);

        /** @var array<string, array<string, mixed>> $areas */
        $areas = $container->getParameter(SettingsAreaCompilerPass::AREAS_PARAMETER);

        $this->assertSame('sulu_webspace_settings.settings', $areas['minimal']['group']);
        $this->assertSame('su-cog', $areas['minimal']['icon']);
    }

    public function testRejectsDuplicateAreaKeys(): void
    {
        $container = $this->createContainer([SocialSettings::class, DuplicateSocialSettings::class]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('declared twice');

        (new SettingsAreaCompilerPass())->process($container);
    }

    public function testSetsAnEmptyParameterWithoutAreas(): void
    {
        $container = new ContainerBuilder();

        (new SettingsAreaCompilerPass())->process($container);

        $this->assertSame([], $container->getParameter(SettingsAreaCompilerPass::AREAS_PARAMETER));
    }

    /**
     * @param list<class-string> $classNames
     */
    private function createContainer(array $classNames): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($classNames as $className) {
            $definition = new Definition($className);
            $definition->addTag(AsSettingsArea::TAG);
            $container->setDefinition($className, $definition);
        }

        return $container;
    }
}

#[AsSettingsArea(key: 'minimal')]
final class MinimalSettings
{
}

#[AsSettingsArea(key: 'social')]
final class DuplicateSocialSettings
{
}
