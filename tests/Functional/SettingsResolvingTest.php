<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Application\Reader\ResolvedSettings;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The sharpest test in the project.
 *
 * The PHPCR-free content model was chosen so that an area containing a block and a smart content
 * property resolves without a request context. If this breaks, the premise of the bundle breaks.
 */
class SettingsResolvingTest extends WebspaceSettingsTestCase
{
    public function testResolvesABlockAndSmartContentFromAConsoleCommand(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', [
            'facebook_url' => 'https://facebook.com/sulu',
            'links' => [
                ['type' => 'link', 'label' => 'Docs', 'url' => 'https://docs.sulu.io'],
                ['type' => 'link', 'label' => 'Blog', 'url' => 'https://sulu.io/blog'],
            ],
            'teasers' => [
                'dataSource' => null,
                'includeSubFolders' => true,
                'limitResult' => 3,
                'sortBy' => 'title',
                'sortMethod' => 'asc',
                'tags' => [],
                'categories' => [],
            ],
        ]);

        $resolved = $this->runDumpCommand('website', 'social', 'en');

        $this->assertSame('https://facebook.com/sulu', $resolved['facebook_url']);

        $this->assertCount(2, $resolved['links']);
        $this->assertSame('Docs', $resolved['links'][0]['label']);
        $this->assertSame('link', $resolved['links'][0]['type']);

        $this->assertArrayHasKey('teasers', $resolved);
        $this->assertIsArray($resolved['teasers']);
    }

    /**
     * The regression test for the per-property read path: `all()` used to be the only path that
     * worked, so a template reading one property saw null while `dump` printed the value.
     */
    public function testResolvesASinglePropertyWithoutResolvingThroughAll(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', [
            'facebook_url' => 'https://facebook.com/sulu',
            'links' => [
                ['type' => 'link', 'label' => 'Docs', 'url' => 'https://docs.sulu.io'],
            ],
        ]);

        $resolved = static::getSettings()->forWebspace('website')->forLocale('en')->resolved('social');

        $this->assertSame('https://facebook.com/sulu', $resolved->get('facebook_url'));
        $this->assertSame('https://facebook.com/sulu', $resolved['facebook_url']);
        $this->assertSame('https://facebook.com/sulu', $this->readMagicProperty($resolved, 'facebook_url'));
        $this->assertSame('Docs', $resolved->get('links')[0]['label']);
    }

    /**
     * Twig under `strict_variables` throws unless the object answers the method call, and a
     * never-saved area is a normal state, so this must read as null rather than blow up a page.
     */
    public function testReadsAnEmptyAreaAsNullInsteadOfFailing(): void
    {
        $resolved = static::getSettings()->forWebspace('website')->forLocale('en')->resolved('social');

        $this->assertNull($resolved->get('facebook_url'));
        $this->assertNull($this->callMagicMethod($resolved, 'facebook_url'));
    }

    public function testResolvesANonTranslatableAreaInAnyLocale(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'https://facebook.com/sulu']);

        $resolved = $this->runDumpCommand('website', 'social', 'de');

        $this->assertSame('https://facebook.com/sulu', $resolved['facebook_url']);
    }

    public function testResolvesAnAreaThatWasNeverSavedToNothing(): void
    {
        $resolved = $this->runDumpCommand('website', 'social', 'en');

        $this->assertSame([], $resolved);
    }

    public function testFallsBackToTheDefaultLocaleOfTheWebspace(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'https://facebook.com/sulu']);

        $resolved = $this->runDumpCommand('website', 'social');

        $this->assertSame('https://facebook.com/sulu', $resolved['facebook_url']);
    }

    /**
     * The README promises an area is resolved once, and a template that reads two properties of
     * the same area reads them through two separate `settings()` calls - each of which asks the
     * reader again. Without a memo that re-runs every smart content query in the area.
     */
    public function testResolvesAnAreaOnceAcrossRepeatedCalls(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $settings = static::getSettings()->forWebspace('website')->forLocale('en');

        $this->assertSame($settings->resolved('social'), $settings->resolved('social'));
    }

    /**
     * `forWebspace()` hands back a new reader, so the memo has to travel with it - otherwise
     * `{{ settings('social', 'shop') }}` twice in one template is two full resolves.
     */
    public function testSharesTheMemoWithEveryScopedReader(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $settings = static::getSettings();

        $this->assertSame(
            $settings->forWebspace('website')->forLocale('en')->resolved('social'),
            $settings->forWebspace('website')->forLocale('en')->resolved('social'),
        );
    }

    /**
     * The memo is keyed by webspace, area and locale, so scoping it away has to reach a different
     * entry - a shared memo that ignored the scope would serve another webspace's settings.
     */
    public function testKeepsTheMemoOfEveryScopeApart(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'website']);
        $manager->save('shop', 'social', 'en', ['facebook_url' => 'shop']);

        $settings = static::getSettings();

        $this->assertSame(
            'website',
            $settings->forWebspace('website')->forLocale('en')->resolved('social')->get('facebook_url'),
        );
        $this->assertSame(
            'shop',
            $settings->forWebspace('shop')->forLocale('en')->resolved('social')->get('facebook_url'),
        );
    }

    /**
     * Twig falls back to a method call, so that path is called the way Twig calls it.
     */
    private function callMagicMethod(ResolvedSettings $settings, string $property): mixed
    {
        return $settings->$property();
    }

    /**
     * Twig reaches values through __get as well, so that path is read the way Twig uses it.
     */
    private function readMagicProperty(ResolvedSettings $settings, string $property): mixed
    {
        return $settings->$property;
    }

    /**
     * @return array<string, mixed>
     */
    private function runDumpCommand(string $webspace, string $area, ?string $locale = null): array
    {
        $application = static::createConsoleApplication();
        $application->setAutoExit(false);

        $commandTester = new CommandTester($application->find('test:settings:dump'));
        $commandTester->execute(\array_filter([
            'webspace' => $webspace,
            'area' => $area,
            'locale' => $locale,
        ]));

        $commandTester->assertCommandIsSuccessful();

        /** @var array<string, mixed> $resolved */
        $resolved = \json_decode(\trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);

        return $resolved;
    }
}
