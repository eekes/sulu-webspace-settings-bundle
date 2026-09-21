<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotAvailableException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\UnknownSettingsModelException;

/**
 * @phpstan-import-type SettingsAreaConfig from SettingsArea
 */
final class SettingsAreaRegistry implements SettingsAreaRegistryInterface
{
    /**
     * @var array<string, SettingsArea>|null
     */
    private ?array $areas = null;

    /**
     * @param array<string, SettingsAreaConfig> $areaConfigs collected by the compiler pass
     */
    public function __construct(private readonly array $areaConfigs = [])
    {
    }

    public function getAreas(): array
    {
        if (null !== $this->areas) {
            return $this->areas;
        }

        $areas = [];
        foreach ($this->areaConfigs as $key => $config) {
            $areas[$key] = SettingsArea::fromArray($config);
        }

        \uasort(
            $areas,
            static fn (SettingsArea $a, SettingsArea $b) => [$a->order, $a->key] <=> [$b->order, $b->key],
        );

        return $this->areas = $areas;
    }

    public function hasArea(string $areaKey): bool
    {
        return \array_key_exists($areaKey, $this->getAreas());
    }

    public function getArea(string $areaKey): SettingsArea
    {
        $areas = $this->getAreas();

        if (!\array_key_exists($areaKey, $areas)) {
            throw new SettingsAreaNotFoundException($areaKey, \array_keys($areas));
        }

        return $areas[$areaKey];
    }

    public function getAreasForWebspace(string $webspaceKey): array
    {
        return \array_filter(
            $this->getAreas(),
            static fn (SettingsArea $area) => $area->isAvailableForWebspace($webspaceKey),
        );
    }

    public function getDefaultAreaKey(): ?string
    {
        return \array_key_first($this->getAreas());
    }

    public function getDefaultAreaKeyForWebspace(string $webspaceKey): ?string
    {
        return \array_key_first($this->getAreasForWebspace($webspaceKey));
    }

    public function getAreaForModel(string $className): SettingsArea
    {
        $models = [];
        foreach ($this->getAreas() as $area) {
            if (null === $area->model) {
                continue;
            }

            if ($area->model === $className) {
                return $area;
            }

            $models[] = $area->model;
        }

        throw new UnknownSettingsModelException($className, $models);
    }

    public function getAreaForWebspace(string $areaKey, string $webspaceKey): SettingsArea
    {
        $area = $this->getArea($areaKey);

        if (!$area->isAvailableForWebspace($webspaceKey)) {
            throw new SettingsAreaNotAvailableException($areaKey, $webspaceKey);
        }

        return $area;
    }
}
