<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when settings are read without a locale and none can be derived from the request or from
 * the webspace's default localization.
 */
class MissingLocaleContextException extends \RuntimeException implements SettingsExceptionInterface
{
    public function __construct(private readonly string $webspaceKey)
    {
        parent::__construct(\sprintf(
            'Cannot determine the locale for webspace "%s". There is no current request, and the webspace '
            . 'declares no default localization. Name the locale explicitly with '
            . '$settings->forLocale(\'<locale>\')->...',
            $webspaceKey,
        ));
    }

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }
}
