<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Infrastructure\Sulu\HttpCache;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsCreatedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsModifiedEvent;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\HttpCache\SettingsCacheInvalidationSubscriber;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;

/**
 * Both sides must use the resource key: Sulu 3.0.11 fixed a bug where invalidation still used the
 * 2.x aliases and therefore never invalidated anything.
 */
class SettingsCacheInvalidationSubscriberTest extends TestCase
{
    public function testInvalidatesByResourceKeyAndIdentifier(): void
    {
        $cacheManager = $this->createMock(CacheManagerInterface::class);
        $cacheManager->expects($this->once())
            ->method('invalidateReference')
            ->with('webspace_settings', 'website::social');

        (new SettingsCacheInvalidationSubscriber($cacheManager))->invalidate(
            new SettingsModifiedEvent('website', $this->area(), null, 'Social'),
        );
    }

    public function testInvalidatesOnCreationToo(): void
    {
        $cacheManager = $this->createMock(CacheManagerInterface::class);
        $cacheManager->expects($this->once())->method('invalidateReference');

        (new SettingsCacheInvalidationSubscriber($cacheManager))->invalidate(
            new SettingsCreatedEvent('website', $this->area(), null, 'Social'),
        );
    }

    public function testDoesNothingWithoutAnHttpCache(): void
    {
        $this->expectNotToPerformAssertions();

        (new SettingsCacheInvalidationSubscriber(null))->invalidate(
            new SettingsModifiedEvent('website', $this->area(), null, 'Social'),
        );
    }

    public function testSubscribesToBothEvents(): void
    {
        $this->assertSame(
            [SettingsCreatedEvent::class => 'invalidate', SettingsModifiedEvent::class => 'invalidate'],
            SettingsCacheInvalidationSubscriber::getSubscribedEvents(),
        );
    }

    private function area(): SettingsArea
    {
        return new SettingsArea('social', 'app.settings.social', 'social', ['*'], 10, null);
    }
}
