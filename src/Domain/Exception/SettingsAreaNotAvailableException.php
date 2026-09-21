<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when an area is requested for a webspace it is not declared for.
 *
 * Scoping is enforced server side, so a hand-edited URL cannot expose a form that should not
 * exist for that webspace.
 */
class SettingsAreaNotAvailableException extends \InvalidArgumentException implements SettingsExceptionInterface
{
    public function __construct(
        private readonly string $areaKey,
        private readonly string $webspaceKey,
    ) {
        parent::__construct(\sprintf(
            'The settings area "%s" is not available for webspace "%s".',
            $areaKey,
            $webspaceKey,
        ));
    }

    public function getAreaKey(): string
    {
        return $this->areaKey;
    }

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }
}
