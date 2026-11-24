<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsIdentifier;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;
use FOS\HttpCache\TagHeaderFormatter\TagHeaderFormatter;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Toflar\Psr6HttpCacheStore\Psr6Store;

/**
 * Without reference tracking the bundle would be worse than the default-snippet pattern it
 * replaces: an editor changes a setting, the page does not change, and nothing says why. This
 * fails silently, so it is covered by tests rather than by hand.
 */
class SettingsReferenceTest extends WebspaceSettingsTestCase
{
    public function testWritesReferenceRowsForEverythingAnAreaPointsAt(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', [
            'media' => ['ids' => [42, 43]],
        ]);

        $references = $this->findReferences();

        $this->assertCount(2, $references);
        $this->assertSame(['42', '43'], \array_column($references, 'resourceId'));
        $this->assertSame('media', $references[0]['resourceKey']);
        $this->assertSame('webspace_settings', $references[0]['referenceResourceKey']);
        $this->assertSame($this->uuidOf('website', 'social'), $references[0]['referenceResourceId']);
        $this->assertSame('media', $references[0]['referenceProperty']);
    }

    public function testReplacesTheReferenceRowsOfTheAreaOnSave(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['media' => ['ids' => [42]]]);
        $manager->save('website', 'social', 'en', ['media' => ['ids' => [43]]]);

        $this->assertSame(['43'], \array_column($this->findReferences(), 'resourceId'));
    }

    public function testKeepsTheReferenceRowsOfOtherWebspacesApart(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['media' => ['ids' => [42]]]);
        $manager->save('shop', 'social', 'en', ['media' => ['ids' => [43]]]);

        $this->assertCount(2, $this->findReferences());
        $this->assertSame(
            ['42'],
            \array_column($this->findReferences($this->uuidOf('website', 'social')), 'resourceId'),
        );
        $this->assertSame(
            ['43'],
            \array_column($this->findReferences($this->uuidOf('shop', 'social')), 'resourceId'),
        );
    }

    public function testRefreshRebuildsTheReferenceRows(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['media' => ['ids' => [42]]]);

        $referenceRepository = $this->getReferenceRepository();
        $referenceRepository->removeBy(['referenceResourceKey' => 'webspace_settings']);
        $referenceRepository->flush();

        $this->assertCount(0, $this->findReferences());

        /** @var \Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference\SettingsReferenceRefresher $refresher */
        $refresher = static::getContainer()->get('sulu_webspace_settings.reference_refresher');

        $refreshed = 0;
        foreach ($refresher->refresh() as $dimensionContent) {
            $this->assertNotNull($dimensionContent);
            ++$refreshed;
        }

        $referenceRepository->flush();

        $this->assertSame(1, $refreshed);
        $this->assertSame(['42'], \array_column($this->findReferences(), 'resourceId'));
    }

    public function testTagsTheResponseWithTheSettingsIdentifierOnRead(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        /** @var \Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface $referenceStore */
        $referenceStore = static::getContainer()->get('sulu_http_cache.reference_store');

        static::getSettings()->forWebspace('website')->forLocale('en')->raw('social');

        // the same tag the cache manager invalidates with, otherwise nothing is ever invalidated
        $this->assertContains('webspace_settings-website~social', $referenceStore->getAll());
    }

    /**
     * Sulu's default HTTP cache (the Symfony HttpCache with toflar/psr6-symfony-http-cache-store)
     * refused the tags of a page that read a setting, and in prod it swallows that error: no such
     * page was ever cached.
     */
    public function testAPageThatReadASettingIsCachedAndInvalidatedOnSave(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        /** @var \Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface $referenceStore */
        $referenceStore = static::getContainer()->get('sulu_http_cache.reference_store');

        static::getSettings()->forWebspace('website')->forLocale('en')->raw('social');

        $store = new Psr6Store([
            'cache' => new TagAwareAdapter(new ArrayAdapter()),
            'lock_factory' => new LockFactory(new InMemoryStore()),
            'cache_tags_header' => TagHeaderFormatter::DEFAULT_HEADER_NAME,
        ]);
        $response = new Response('<p>a</p>');
        $response->setPublic();
        $response->setSharedMaxAge(600);
        $response->headers->set(TagHeaderFormatter::DEFAULT_HEADER_NAME, \implode(',', $referenceStore->getAll()));

        $store->write(Request::create('https://example.org/'), $response);

        $this->assertNotNull($store->lookup(Request::create('https://example.org/')), 'The page was not cached.');

        // the tag CacheManager::invalidateReference() sends when the area is saved
        $store->invalidateTags([WebspaceSettingsInterface::RESOURCE_KEY . '-' . SettingsIdentifier::create('website', 'social')]);

        $this->assertNull($store->lookup(Request::create('https://example.org/')), 'Saving the area did not invalidate the page.');
    }

    private function uuidOf(string $webspaceKey, string $area): string
    {
        /** @var WebspaceSettingsRepositoryInterface $repository */
        $repository = static::getContainer()->get(WebspaceSettingsRepositoryInterface::class);

        $settings = $repository->findOneBy($webspaceKey, $area);

        $this->assertNotNull($settings);

        return $settings->getUuid();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findReferences(?string $referenceResourceId = null): array
    {
        $filters = ['referenceResourceKey' => 'webspace_settings'];

        if (null !== $referenceResourceId) {
            $filters['referenceResourceId'] = $referenceResourceId;
        }

        /** @var list<array<string, mixed>> $references */
        $references = \iterator_to_array($this->getReferenceRepository()->findFlatBy(
            $filters,
            ['resourceId' => 'asc'],
            ['resourceKey', 'resourceId', 'referenceResourceKey', 'referenceResourceId', 'referenceProperty'],
        ));

        return \array_values($references);
    }

    private function getReferenceRepository(): ReferenceRepositoryInterface
    {
        /** @var ReferenceRepositoryInterface $repository */
        $repository = static::getContainer()->get('sulu_reference.reference_repository');

        return $repository;
    }
}
