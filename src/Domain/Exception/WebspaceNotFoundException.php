<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when settings are addressed for a webspace that is not configured at all.
 *
 * Distinct from {@see SettingsAreaNotAvailableException}: that one means the webspace exists and
 * the area is not declared for it, which is a different thing to tell a developer.
 */
class WebspaceNotFoundException extends \InvalidArgumentException implements SettingsExceptionInterface
{
    /**
     * @param string[] $availableWebspaceKeys
     */
    public function __construct(
        private readonly string $webspaceKey,
        array $availableWebspaceKeys = [],
    ) {
        parent::__construct(\sprintf(
            'There is no webspace "%s". Configured webspaces: %s.',
            $webspaceKey,
            [] === $availableWebspaceKeys ? '(none)' : \implode(', ', $availableWebspaceKeys),
        ));
    }

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }
}
