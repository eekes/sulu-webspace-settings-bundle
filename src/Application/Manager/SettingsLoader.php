<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Manager;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;

/**
 * Loads the merged dimension content of one area, or null when it has never been saved.
 *
 * Records are created lazily, so "not there yet" is a normal state and never an error: the admin
 * shows an empty form and the read API returns defaults.
 *
 * @internal
 */
final class SettingsLoader
{
    public function __construct(
        private readonly WebspaceSettingsRepositoryInterface $repository,
        private readonly ContentAggregatorInterface $contentAggregator,
    ) {
    }

    public function load(
        string $webspaceKey,
        SettingsArea $area,
        string $locale,
    ): ?WebspaceSettingsDimensionContentInterface {
        $settings = $this->repository->findOneBy($webspaceKey, $area->key);

        if (null === $settings) {
            return null;
        }

        return $this->aggregate($settings, $locale);
    }

    public function aggregate(
        WebspaceSettingsInterface $settings,
        string $locale,
    ): ?WebspaceSettingsDimensionContentInterface {
        try {
            $dimensionContent = $this->contentAggregator->aggregate(
                $settings,
                $this->dimensionAttributes($locale),
            );
        } catch (ContentNotFoundException) {
            return null;
        }

        if (null === $dimensionContent->getLocale()) {
            // A non-translatable area keeps its values in the unlocalized dimension content, so
            // reading it in a locale that was never saved merges to a record without a locale.
            // The merged instance is in memory only, so stamping the requested locale on it is
            // safe and is what every downstream resolver expects.
            $dimensionContent->setLocale($locale);
        }

        return $dimensionContent;
    }

    /**
     * The stage is pinned by the dimension content, a caller never supplies one.
     *
     * @return array{locale: string, stage: string}
     */
    public function dimensionAttributes(string $locale): array
    {
        return [
            'locale' => $locale,
            'stage' => WebspaceSettingsDimensionContentInterface::STAGE,
        ];
    }
}
