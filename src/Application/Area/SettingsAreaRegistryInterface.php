<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotAvailableException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\UnknownSettingsModelException;

interface SettingsAreaRegistryInterface
{
    /**
     * All declared areas, ordered by `order` and then by key.
     *
     * @return array<string, SettingsArea>
     */
    public function getAreas(): array;

    public function hasArea(string $areaKey): bool;

    /**
     * @throws SettingsAreaNotFoundException
     */
    public function getArea(string $areaKey): SettingsArea;

    /**
     * @return array<string, SettingsArea>
     */
    public function getAreasForWebspace(string $webspaceKey): array;

    /**
     * The area the settings view opens on when no area is given.
     */
    public function getDefaultAreaKey(): ?string;

    /**
     * The area the settings view of one webspace opens on.
     *
     * Not the same as {@see getDefaultAreaKey()}: the first area overall may well be declared for
     * other webspaces only, and opening a webspace on an area it does not have is a 404 where the
     * editor expects a form.
     */
    public function getDefaultAreaKeyForWebspace(string $webspaceKey): ?string;

    /**
     * @param class-string $className
     *
     * @throws UnknownSettingsModelException
     */
    public function getAreaForModel(string $className): SettingsArea;

    /**
     * Resolves an area and asserts it may be used for the given webspace.
     *
     * @throws SettingsAreaNotFoundException
     * @throws SettingsAreaNotAvailableException
     */
    public function getAreaForWebspace(string $areaKey, string $webspaceKey): SettingsArea;
}
