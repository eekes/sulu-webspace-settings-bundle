<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\Metadata;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsTemplateNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;

/**
 * Serves every area from one form key.
 *
 * This is the load-bearing piece of the admin design: the settings view is a single form view
 * whose `area` router attribute is passed through as a metadata option, and this loader turns
 * that option into the area's template. Without it the form key would resolve to the whole
 * template type and the admin would render a template select instead of the area's fields.
 *
 * Without an `area` option the loader stands aside, so the plain typed metadata for the template
 * type keeps working - Sulu's own `TemplateDataMapper` and `TemplateResolver` ask for exactly
 * that.
 *
 * @internal
 */
final class SettingsAreaFormMetadataLoader implements FormMetadataLoaderInterface
{
    public function __construct(
        private readonly FormMetadataLoaderInterface $templateFormMetadataLoader,
        private readonly SettingsAreaRegistryInterface $areaRegistry,
    ) {
    }

    public function getMetadata(string $key, string $locale, array $metadataOptions): ?MetadataInterface
    {
        if (WebspaceSettingsInterface::TEMPLATE_TYPE !== $key) {
            return null;
        }

        $areaKey = $metadataOptions['area'] ?? null;

        if (!\is_string($areaKey) || '' === $areaKey) {
            return null;
        }

        $webspaceKey = $metadataOptions['webspace'] ?? null;

        // scoping is enforced here as well as in the controller, otherwise a hand-edited URL
        // exposes a form that should not exist for that webspace
        $area = \is_string($webspaceKey) && '' !== $webspaceKey
            ? $this->areaRegistry->getAreaForWebspace($areaKey, $webspaceKey)
            : $this->areaRegistry->getArea($areaKey);

        $typedFormMetadata = $this->templateFormMetadataLoader->getMetadata($key, $locale, []);

        if (!$typedFormMetadata instanceof TypedFormMetadata) {
            return null;
        }

        $formMetadata = $typedFormMetadata->getForms()[$area->template] ?? null;

        if (null === $formMetadata) {
            throw new SettingsTemplateNotFoundException(
                $area->key,
                $area->template,
                \array_keys($typedFormMetadata->getForms()),
            );
        }

        return $formMetadata;
    }
}
