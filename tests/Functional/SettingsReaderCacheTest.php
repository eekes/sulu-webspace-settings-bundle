<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsCache;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

/**
 * The reader memoises for the whole process, which is only safe because something empties the
 * memo again. Without that a messenger worker keeps serving the values it read first, and a
 * command that writes and then reads never sees its own write.
 */
class SettingsReaderCacheTest extends WebspaceSettingsTestCase
{
    public function testAReadAfterAWriteSeesTheWrite(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'first']);

        $settings = $this->reader();

        $this->assertSame('first', $settings->raw('social')['facebook_url']);

        $manager->save('website', 'social', 'en', ['facebook_url' => 'second']);

        $this->assertSame(
            'second',
            $settings->raw('social')['facebook_url'],
            'The reader that already read the area must not keep serving the old value',
        );
    }

    public function testAWriteAlsoReachesTheResolvedValues(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'first']);

        $settings = $this->reader();

        $this->assertSame('first', $settings->resolved('social')->get('facebook_url'));

        $manager->save('website', 'social', 'en', ['facebook_url' => 'second']);

        $this->assertSame('second', $settings->resolved('social')->get('facebook_url'));
    }

    /**
     * A non-translatable field is shared across locales, so a write in one locale changes what
     * every other locale reads back.
     */
    public function testAWriteEvictsEveryLocaleOfTheArea(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'first']);

        $settings = $this->reader();

        $this->assertSame('first', $settings->forLocale('de')->raw('social')['facebook_url']);

        $manager->save('website', 'social', 'en', ['facebook_url' => 'second']);

        $this->assertSame('second', $settings->forLocale('de')->raw('social')['facebook_url']);
    }

    public function testAWriteLeavesTheOtherAreasMemoised(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'contact', 'en', ['phone_number' => '+32 1']);

        $this->reader()->raw('contact');

        $cache = $this->readerCache();

        $this->assertTrue($cache->hasDimensionContent(SettingsCache::key('website', 'contact', 'en')));

        $manager->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $this->assertTrue(
            $cache->hasDimensionContent(SettingsCache::key('website', 'contact', 'en')),
            'Saving one area must not throw away the memo of another',
        );
    }

    /**
     * What a worker runtime does between requests, and messenger between messages.
     */
    public function testTheKernelResetEmptiesTheMemo(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $this->reader()->raw('social');

        $cache = $this->readerCache();

        $this->assertTrue($cache->hasDimensionContent(SettingsCache::key('website', 'social', 'en')));

        $resetter = static::getContainer()->get('services_resetter');
        $this->assertInstanceOf(ServicesResetter::class, $resetter);
        $resetter->reset();

        $this->assertFalse(
            $cache->hasDimensionContent(SettingsCache::key('website', 'social', 'en')),
            'The reader cache must be tagged kernel.reset',
        );
    }

    private function reader(): SettingsInterface
    {
        /** @var SettingsInterface $settings */
        $settings = static::getContainer()->get(SettingsInterface::class);

        return $settings->forWebspace('website')->forLocale('en');
    }

    private function readerCache(): SettingsCache
    {
        /** @var SettingsCache $cache */
        $cache = static::getContainer()->get('sulu_webspace_settings.reader_cache');

        return $cache;
    }
}
