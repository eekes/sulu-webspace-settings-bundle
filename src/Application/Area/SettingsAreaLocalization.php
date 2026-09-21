<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Answers whether an area stores anything per locale, by reading its template.
 *
 * There is no `translatable` flag on the area: a field opts out of being translated with Sulu's
 * own `multilingual="false"`, and an area is localized exactly when at least one of its fields is
 * still multilingual. Deriving it keeps one source of truth - the template - instead of a
 * declaration that can disagree with it.
 *
 * What it decides: whether the admin shows a locale select, and whether an activity entry records
 * which language was edited.
 *
 * @internal
 */
final class SettingsAreaLocalization
{
    /**
     * @var array<string, bool>
     */
    private array $localized = [];

    public function __construct(
        private readonly FormMetadataLoaderInterface $templateFormMetadataLoader,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function isLocalized(SettingsArea $area): bool
    {
        if (\array_key_exists($area->template, $this->localized)) {
            return $this->localized[$area->template];
        }

        $typedFormMetadata = $this->templateFormMetadataLoader->getMetadata(
            WebspaceSettingsInterface::TEMPLATE_TYPE,
            $this->translator->getLocale(),
            [],
        );

        if (!$typedFormMetadata instanceof TypedFormMetadata) {
            return $this->localized[$area->template] = true;
        }

        $formMetadata = $typedFormMetadata->getForms()[$area->template] ?? null;

        if (null === $formMetadata) {
            // the template is missing, which the metadata loader reports with a better message
            // than a locale select ever could; assume the common case until it does
            return $this->localized[$area->template] = true;
        }

        foreach ($formMetadata->getFlatFieldMetadata() as $field) {
            if ($field->isMultilingual()) {
                return $this->localized[$area->template] = true;
            }
        }

        return $this->localized[$area->template] = false;
    }
}
