<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Symfony\CompilerPass;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\AsSettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects every class carrying #[AsSettingsArea] into a container parameter.
 *
 * The classes are tagged by `registerAttributeForAutoconfiguration()` in the bundle, so a
 * consumer never writes a line of YAML to add a settings screen.
 *
 * @internal
 *
 * @phpstan-import-type SettingsAreaConfig from SettingsArea
 */
final class SettingsAreaCompilerPass implements CompilerPassInterface
{
    public const AREAS_PARAMETER = 'sulu_webspace_settings.areas';

    /**
     * The fallback when an area names no title.
     *
     * A translation key would be the obvious default, but it is one the bundle can never ship a
     * translation for - the key contains the consumer's area key - so an area declared without a
     * title would read as `sulu_webspace_settings.area.social` in the dropdown. A humanised key
     * is wrong in no language and right in most, and a project that wants a real translation
     * passes `title:`.
     */
    public static function humanize(string $areaKey): string
    {
        return \ucfirst(\trim(\str_replace(['_', '-', '.'], ' ', $areaKey)));
    }

    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, SettingsAreaConfig> $areas */
        $areas = [];
        /** @var array<string, class-string> $declaredBy */
        $declaredBy = [];

        foreach ($container->findTaggedServiceIds(AsSettingsArea::TAG, true) as $id => $tags) {
            $definition = $container->getDefinition($id);
            /** @var class-string $className */
            $className = $definition->getClass() ?? $id;

            $reflection = $container->getReflectionClass($className, false);

            if (null === $reflection) {
                continue;
            }

            foreach ($reflection->getAttributes(AsSettingsArea::class) as $attribute) {
                /** @var AsSettingsArea $area */
                $area = $attribute->newInstance();

                if (isset($declaredBy[$area->key])) {
                    throw new \LogicException(\sprintf(
                        'The settings area "%s" is declared twice, by "%s" and by "%s". Area keys must be unique.',
                        $area->key,
                        $declaredBy[$area->key],
                        $reflection->getName(),
                    ));
                }

                $declaredBy[$area->key] = $reflection->getName();

                $areas[$area->key] = [
                    'key' => $area->key,
                    'title' => $area->title ?? self::humanize($area->key),
                    'template' => $area->template ?? $area->key,
                    'webspaces' => \array_values($area->webspaces),
                    'order' => $area->order,
                    'group' => $area->group ?? SettingsArea::DEFAULT_GROUP,
                    'icon' => $area->icon ?? SettingsArea::DEFAULT_ICON,
                    'model' => $reflection->getName(),
                ];
            }
        }

        \uasort(
            $areas,
            static fn (array $a, array $b) => [$a['order'], $a['key']] <=> [$b['order'], $b['key']],
        );

        $container->setParameter(self::AREAS_PARAMETER, $areas);
    }
}
