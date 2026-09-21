<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when settings are read without a webspace and none can be derived from the request.
 *
 * Deliberately never falls back to "the first configured webspace": that produces a site quietly
 * serving another webspace's settings, which only surfaces in production.
 */
class MissingWebspaceContextException extends \RuntimeException implements SettingsExceptionInterface
{
    public function __construct(string $areaKey)
    {
        parent::__construct(\sprintf(
            'Cannot determine the webspace while reading the settings area "%s". There is no current request, '
            . 'or the request has no webspace. Name the webspace explicitly with '
            . '$settings->forWebspace(\'<webspace-key>\')->...',
            $areaKey,
        ));
    }
}
