<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

class SettingsAreaNotFoundException extends \InvalidArgumentException implements SettingsExceptionInterface
{
    /**
     * @param string[] $availableAreaKeys
     */
    public function __construct(
        private readonly string $areaKey,
        array $availableAreaKeys = [],
    ) {
        parent::__construct(\sprintf(
            'No settings area "%s" is declared. Declared areas: %s.',
            $areaKey,
            [] === $availableAreaKeys ? '(none)' : \implode(', ', $availableAreaKeys),
        ));
    }

    public function getAreaKey(): string
    {
        return $this->areaKey;
    }
}
