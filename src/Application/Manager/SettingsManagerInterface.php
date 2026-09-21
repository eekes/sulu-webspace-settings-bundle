<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Manager;

use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotAvailableException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;

interface SettingsManagerInterface
{
    /**
     * Returns null when the area has never been saved for this webspace.
     *
     * @throws SettingsAreaNotFoundException
     * @throws SettingsAreaNotAvailableException
     */
    public function load(
        string $webspaceKey,
        string $areaKey,
        string $locale,
    ): ?WebspaceSettingsDimensionContentInterface;

    /**
     * Persists the area, creating the record if this is the first save.
     *
     * Dispatches an activity event and invalidates the HTTP cache only when the stored data
     * actually changed.
     *
     * @param array<string, mixed> $data
     *
     * @throws SettingsAreaNotFoundException
     * @throws SettingsAreaNotAvailableException
     */
    public function save(
        string $webspaceKey,
        string $areaKey,
        string $locale,
        array $data,
    ): WebspaceSettingsDimensionContentInterface;
}
