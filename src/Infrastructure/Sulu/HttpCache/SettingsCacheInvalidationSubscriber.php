<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\HttpCache;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsIdentifier;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\AbstractSettingsEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsCreatedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsModifiedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates the pages that render a setting, using the resource key and the same identifier the
 * reference store tags them with.
 *
 * @internal no backwards compatibility promise is given for this class; register your own
 *           subscriber or override this service to change the behaviour
 */
final class SettingsCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ?CacheManagerInterface $cacheManager = null)
    {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SettingsCreatedEvent::class => 'invalidate',
            SettingsModifiedEvent::class => 'invalidate',
        ];
    }

    public function invalidate(AbstractSettingsEvent $event): void
    {
        $this->cacheManager?->invalidateReference(
            WebspaceSettingsInterface::RESOURCE_KEY,
            SettingsIdentifier::create($event->getWebspaceKey(), $event->getArea()->key),
        );
    }
}
