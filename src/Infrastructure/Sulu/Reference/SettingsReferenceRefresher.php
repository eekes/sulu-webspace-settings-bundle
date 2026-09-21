<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference;

use Doctrine\ORM\EntityManagerInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsLoader;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettings;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Sulu\Bundle\ReferenceBundle\Application\Refresh\ReferenceRefresherInterface;

/**
 * Writes the reference rows of a settings record.
 *
 * This is both halves of reference tracking. Sulu's own `ReferenceDoctrineEventListener` dispatches
 * a `RefreshReferenceMessage` after every flush that touched a dimension content, so saving an area
 * already lands here with a filter; `bin/console sulu:reference:refresh` calls the same method
 * without one to rebuild everything.
 *
 * Reference rows only appear on the next save of existing content, so consumers have to run that
 * command once after installing or upgrading, or references look broken for existing records.
 *
 * @internal
 */
final class SettingsReferenceRefresher implements ReferenceRefresherInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly SettingsLoader $loader,
        private readonly SettingsReferenceUpdater $referenceUpdater,
    ) {
    }

    public static function getResourceKey(): string
    {
        return WebspaceSettingsInterface::RESOURCE_KEY;
    }

    public function refresh(?array $filter = null): \Generator
    {
        foreach ($this->findSettings($filter) as $settings) {
            if (!$this->areaRegistry->hasArea($settings->getArea())) {
                // the area was removed from the code but its rows are still in the database
                continue;
            }

            $area = $this->areaRegistry->getArea($settings->getArea());

            foreach ($this->getLocales($settings, $filter) as $locale) {
                $dimensionContent = $this->loader->aggregate($settings, $locale);

                if (null === $dimensionContent) {
                    continue;
                }

                $this->referenceUpdater->update($dimensionContent, $area);

                yield $dimensionContent;
            }
        }
    }

    /**
     * @param array{resourceId: string, resourceKey: string, locale: string, stage: string}|null $filter
     *
     * @return list<WebspaceSettingsInterface>
     */
    private function findSettings(?array $filter): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('settings', 'dimensionContent')
            ->from(WebspaceSettings::class, 'settings')
            // eager loaded on purpose: ContentAggregator refuses an uninitialised collection in
            // debug mode, and a fetch join cannot be iterated
            ->leftJoin('settings.dimensionContents', 'dimensionContent')
            ->orderBy('settings.uuid', 'ASC');

        if (null !== $filter) {
            $queryBuilder->where('settings.uuid = :uuid')
                ->setParameter('uuid', $filter['resourceId']);
        }

        /** @var list<WebspaceSettingsInterface> $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    /**
     * @param array{resourceId: string, resourceKey: string, locale: string, stage: string}|null $filter
     *
     * @return list<string>
     */
    private function getLocales(WebspaceSettingsInterface $settings, ?array $filter): array
    {
        if (null !== $filter) {
            return [$filter['locale']];
        }

        $locales = [];

        foreach ($settings->getDimensionContents() as $dimensionContent) {
            $locale = $dimensionContent->getLocale();

            if (null !== $locale && !\in_array($locale, $locales, true)) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }
}
