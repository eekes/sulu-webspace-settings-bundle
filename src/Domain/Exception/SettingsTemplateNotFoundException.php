<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Exception;

/**
 * Thrown when an area points at a template XML that does not exist.
 *
 * Almost always a typo in `template:` or a file in the wrong directory, so the message names both
 * what was looked for and what is there.
 */
class SettingsTemplateNotFoundException extends \RuntimeException implements SettingsExceptionInterface
{
    /**
     * @param string[] $availableTemplateKeys
     */
    public function __construct(
        private readonly string $areaKey,
        private readonly string $templateKey,
        array $availableTemplateKeys = [],
    ) {
        parent::__construct(\sprintf(
            'The settings area "%s" points at the template "%s", but no such template exists. '
            . 'Expected a template XML with <key>%s</key>, by default in config/templates/settings/. '
            . 'Available templates: %s.',
            $areaKey,
            $templateKey,
            $templateKey,
            [] === $availableTemplateKeys ? '(none)' : \implode(', ', $availableTemplateKeys),
        ));
    }

    public function getAreaKey(): string
    {
        return $this->areaKey;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }
}
