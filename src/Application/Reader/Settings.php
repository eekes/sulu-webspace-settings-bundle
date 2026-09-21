<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Reader;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsIdentifier;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsLoader;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\MissingLocaleContextException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\MissingWebspaceContextException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;

/**
 * @internal use {@see SettingsInterface}
 */
final class Settings implements SettingsInterface
{
    public function __construct(
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly SettingsLoader $loader,
        private readonly ContentResolverInterface $contentResolver,
        private readonly ReadModelHydrator $hydrator,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ReferenceStoreInterface $referenceStore,
        private readonly SettingsCache $cache = new SettingsCache(),
        // the request analyzer belongs to the website context, and settings are read from the
        // admin and from the console as well
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
        private readonly ?string $webspaceKey = null,
        private readonly ?string $locale = null,
    ) {
    }

    public function forWebspace(string $webspaceKey): SettingsInterface
    {
        return new self(
            $this->areaRegistry,
            $this->loader,
            $this->contentResolver,
            $this->hydrator,
            $this->webspaceManager,
            $this->referenceStore,
            $this->cache,
            $this->requestAnalyzer,
            $webspaceKey,
            $this->locale,
        );
    }

    public function forLocale(string $locale): SettingsInterface
    {
        return new self(
            $this->areaRegistry,
            $this->loader,
            $this->contentResolver,
            $this->hydrator,
            $this->webspaceManager,
            $this->referenceStore,
            $this->cache,
            $this->requestAnalyzer,
            $this->webspaceKey,
            $locale,
        );
    }

    public function raw(string $areaKey): array
    {
        return $this->load($this->resolveArea($areaKey))?->getTemplateData() ?? [];
    }

    public function resolved(string $areaKey): ResolvedSettings
    {
        $area = $this->resolveArea($areaKey);
        $webspaceKey = $this->requireWebspaceKey($area->key);

        // memoised, so reading two properties of the same area does not resolve it - and re-run
        // every smart content query in it - twice
        return $this->cache->resolved(
            SettingsCache::key($webspaceKey, $area->key, $this->resolveLocale($webspaceKey)),
            fn () => new ResolvedSettings(
                function () use ($area): array {
                    $dimensionContent = $this->load($area);

                    if (null === $dimensionContent) {
                        return ['content' => [], 'view' => []];
                    }

                    $resolved = $this->contentResolver->resolve($dimensionContent);

                    return ['content' => $resolved['content'], 'view' => $resolved['view']];
                },
            ),
        );
    }

    public function get(string $modelClass): object
    {
        $area = $this->areaRegistry->getAreaForModel($modelClass);

        return $this->hydrator->hydrate($modelClass, $this->resolved($area->key)->all());
    }

    private function resolveArea(string $areaKey): SettingsArea
    {
        return $this->areaRegistry->getAreaForWebspace($areaKey, $this->requireWebspaceKey($areaKey));
    }

    private function load(SettingsArea $area): ?WebspaceSettingsDimensionContentInterface
    {
        $webspaceKey = $this->requireWebspaceKey($area->key);
        $locale = $this->resolveLocale($webspaceKey);
        $cacheKey = SettingsCache::key($webspaceKey, $area->key, $locale);

        // tag the response, so saving the area reaches the pages that render it. Outside the memo
        // check on purpose: the store is per request and the memo is not, so a second request
        // served by the same worker would otherwise render an untagged page
        $this->referenceStore->add(
            SettingsIdentifier::create($webspaceKey, $area->key),
            WebspaceSettingsInterface::RESOURCE_KEY,
        );

        if ($this->cache->hasDimensionContent($cacheKey)) {
            return $this->cache->getDimensionContent($cacheKey);
        }

        return $this->cache->setDimensionContent($cacheKey, $this->loader->load($webspaceKey, $area, $locale));
    }

    private function requireWebspaceKey(string $areaKey): string
    {
        if (null !== $this->webspaceKey) {
            return $this->webspaceKey;
        }

        $webspace = $this->requestAnalyzer?->getWebspace();

        if (null !== $webspace) {
            return $webspace->getKey();
        }

        // never fall back to "the first configured webspace": that produces a site quietly
        // serving another webspace's settings
        throw new MissingWebspaceContextException($areaKey);
    }

    private function resolveLocale(string $webspaceKey): string
    {
        if (null !== $this->locale) {
            return $this->locale;
        }

        $localization = $this->requestAnalyzer?->getCurrentLocalization();

        if (null !== $localization) {
            return $localization->getLocale();
        }

        $webspace = $this->webspaceManager->findWebspaceByKey($webspaceKey);
        $defaultLocalization = $webspace?->getDefaultLocalization();

        if (null !== $defaultLocalization) {
            return $defaultLocalization->getLocale();
        }

        throw new MissingLocaleContextException($webspaceKey);
    }
}
