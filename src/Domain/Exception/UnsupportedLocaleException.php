<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when settings are addressed in a locale the webspace does not have.
 *
 * Without this check any string in the `locale` query parameter would become a dimension content
 * row, and those rows are then read back by every consumer that iterates locales.
 */
class UnsupportedLocaleException extends \InvalidArgumentException implements SettingsExceptionInterface
{
    /**
     * @param string[] $availableLocales
     */
    public function __construct(
        private readonly string $locale,
        private readonly string $webspaceKey,
        array $availableLocales = [],
    ) {
        parent::__construct(\sprintf(
            'The webspace "%s" has no locale "%s". Its locales are: %s.',
            $webspaceKey,
            $locale,
            [] === $availableLocales ? '(none)' : \implode(', ', $availableLocales),
        ));
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }
}
