<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollector;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Content\Application\ContentResolver\ContentViewResolver\ContentViewResolverInterface;

/**
 * Writes the reference rows for everything an area's template data points at.
 *
 * Without this the bundle would be a regression against the default-snippet pattern it replaces:
 * snippets are tracked references, so changing one invalidates the pages that render it.
 *
 * @internal
 */
final class SettingsReferenceUpdater
{
    public function __construct(
        private readonly ReferenceRepositoryInterface $referenceRepository,
        private readonly ContentViewResolverInterface $contentViewResolver,
    ) {
    }

    public function update(WebspaceSettingsDimensionContentInterface $dimensionContent, SettingsArea $area): void
    {
        $settings = $dimensionContent->getResource();
        $locale = $dimensionContent->getLocale() ?? '';

        $referenceCollector = new ReferenceCollector(
            referenceRepository: $this->referenceRepository,
            referenceResourceKey: $dimensionContent::getResourceKey(),
            // the record uuid, because Sulu removes reference rows by the id of the content rich
            // entity when a dimension content disappears
            referenceResourceId: (string) $settings->getId(),
            referenceLocale: $locale,
            referenceTitle: $area->title,
            referenceContext: $dimensionContent->getStage(),
            referenceRouterAttributes: [
                'webspace' => $settings->getWebspaceKey(),
                'area' => $area->key,
                'locale' => $locale,
            ],
        );

        foreach ($this->contentViewResolver->getContentViews($dimensionContent) as $key => $contentView) {
            $basePath = 'template' !== $key ? (string) $key : '';

            foreach ($contentView->getAllReferencesRecursively($basePath) as $reference) {
                $referenceCollector->addReference(
                    $reference->getResourceKey(),
                    (string) $reference->getResourceId(),
                    $reference->getPath(),
                );
            }
        }

        $referenceCollector->persistReferences();
    }
}
