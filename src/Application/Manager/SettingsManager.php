<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Manager;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsCache;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsCreatedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsModifiedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference\SettingsReferenceUpdater;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal use {@see SettingsManagerInterface}
 */
final class SettingsManager implements SettingsManagerInterface
{
    public function __construct(
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly WebspaceSettingsRepositoryInterface $repository,
        private readonly SettingsLoader $loader,
        private readonly SettingsAreaLocalization $localization,
        private readonly ContentPersisterInterface $contentPersister,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly TranslatorInterface $translator,
        private readonly SettingsCache $readerCache,
        private readonly SettingsSecurityChecker $securityChecker,
        private readonly SettingsReferenceUpdater $referenceUpdater,
    ) {
    }

    public function load(
        string $webspaceKey,
        string $areaKey,
        string $locale,
    ): ?WebspaceSettingsDimensionContentInterface {
        $area = $this->areaRegistry->getAreaForWebspace($areaKey, $webspaceKey);

        return $this->loader->load($webspaceKey, $area, $locale);
    }

    public function save(
        string $webspaceKey,
        string $areaKey,
        string $locale,
        array $data,
    ): WebspaceSettingsDimensionContentInterface {
        $area = $this->areaRegistry->getAreaForWebspace($areaKey, $webspaceKey);

        $settings = $this->repository->findOneBy($webspaceKey, $area->key);
        $isNew = null === $settings;

        if (null === $settings) {
            $settings = $this->repository->create($webspaceKey, $area->key);
            $this->repository->add($settings);
        }

        $dataBefore = $isNew
            ? []
            : ($this->loader->aggregate($settings, $locale)?->getTemplateData() ?? []);

        /** @var WebspaceSettingsDimensionContentInterface $dimensionContent */
        $dimensionContent = $this->contentPersister->persist(
            $settings,
            \array_merge($data, ['template' => $area->template]),
            $this->loader->dimensionAttributes($locale),
        );

        $this->repository->flush();

        // the reader memoises per process, so without this a command that writes and then reads,
        // or any worker runtime, would keep serving what it read before the write
        $this->readerCache->evict($webspaceKey, $area->key);

        // The comparison below decides both the activity entry and the cache invalidation, so it
        // is computed once and used twice.
        if (!$isNew && $this->isUnchanged($dataBefore, $dimensionContent->getTemplateData())) {
            return $dimensionContent;
        }

        // Sulu's own ReferenceDoctrineEventListener refreshes references after a flush, but it
        // skips dimension contents without a locale - and a non-translatable area keeps all of its
        // values in exactly that unlocalized row. So the write path updates them itself.
        $this->referenceUpdater->update($dimensionContent, $area);

        $this->domainEventCollector->collect($this->createEvent($isNew, $webspaceKey, $area, $locale));
        $this->domainEventCollector->dispatch();

        $this->repository->flush();

        return $dimensionContent;
    }

    private function createEvent(
        bool $isNew,
        string $webspaceKey,
        SettingsArea $area,
        string $locale,
    ): SettingsCreatedEvent|SettingsModifiedEvent {
        $title = $this->translator->trans($area->title, [], 'admin');
        $eventLocale = $this->localization->isLocalized($area) ? $locale : null;
        // the trail is scoped exactly like the endpoints, so an area a user may not open never
        // shows up in their log either
        $securityContext = $this->securityChecker->resolveAreaSecurityContext($webspaceKey, $area->key);

        return $isNew
            ? new SettingsCreatedEvent($webspaceKey, $area, $eventLocale, $title, $securityContext)
            : new SettingsModifiedEvent($webspaceKey, $area, $eventLocale, $title, $securityContext);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function isUnchanged(array $before, array $after): bool
    {
        $this->sortRecursively($before);
        $this->sortRecursively($after);

        return $before === $after;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sortRecursively(array &$data): void
    {
        foreach ($data as &$value) {
            if (\is_array($value)) {
                $this->sortRecursively($value);
            }
        }
        unset($value);

        \ksort($data);
    }
}
