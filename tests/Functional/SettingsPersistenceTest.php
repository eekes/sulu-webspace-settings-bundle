<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;

class SettingsPersistenceTest extends WebspaceSettingsTestCase
{
    public function testStoresANonTranslatableAreaOnceForEveryLocale(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', [
            'facebook_url' => 'https://facebook.com/sulu',
        ]);

        $rows = $this->findDimensionContents('website', 'social');

        $this->assertCount(2, $rows, 'An unlocalized row holding the values and the localized row Sulu always writes');

        $unlocalized = $this->rowByLocale($rows, null);
        $localized = $this->rowByLocale($rows, 'en');

        $this->assertSame(
            ['facebook_url' => 'https://facebook.com/sulu'],
            $this->onlyKeys($unlocalized->getTemplateData(), ['facebook_url']),
        );
        $this->assertSame([], $localized->getTemplateData(), 'Nothing is stored per locale for a non-translatable area');
    }

    public function testANonTranslatableAreaReadsTheSameInEveryLocale(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', [
            'facebook_url' => 'https://facebook.com/sulu',
        ]);

        $settings = static::getSettings()->forWebspace('website');

        $this->assertSame('https://facebook.com/sulu', $settings->forLocale('en')->raw('social')['facebook_url']);
        $this->assertSame('https://facebook.com/sulu', $settings->forLocale('de')->raw('social')['facebook_url']);
    }

    public function testStoresATranslatableAreaPerLocale(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'contact', 'en', ['phone_number' => '+32 1', 'opening_hours' => 'Mon-Fri']);
        $manager->save('website', 'contact', 'de', ['phone_number' => '+49 2', 'opening_hours' => 'Mo-Fr']);

        $rows = $this->findDimensionContents('website', 'contact');

        $this->assertCount(3, $rows, 'The unlocalized row plus one per locale');

        $settings = static::getSettings()->forWebspace('website');

        $this->assertSame('+32 1', $settings->forLocale('en')->raw('contact')['phone_number']);
        $this->assertSame('+49 2', $settings->forLocale('de')->raw('contact')['phone_number']);
    }

    public function testKeepsTheWebspacesApart(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'https://facebook.com/website']);
        $manager->save('shop', 'social', 'en', ['facebook_url' => 'https://facebook.com/shop']);

        $settings = static::getSettings()->forLocale('en');

        $this->assertSame(
            'https://facebook.com/website',
            $settings->forWebspace('website')->raw('social')['facebook_url'],
        );
        $this->assertSame(
            'https://facebook.com/shop',
            $settings->forWebspace('shop')->raw('social')['facebook_url'],
        );
    }

    public function testCreatesTheRecordLazily(): void
    {
        $this->assertSame([], static::getSettings()->forWebspace('website')->forLocale('en')->raw('social'));
        $this->assertSame([], $this->findDimensionContents('website', 'social'));

        $model = static::getSettings()->forWebspace('website')->forLocale('en')
            ->get(\Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\SocialSettings::class);

        $this->assertNull($model->facebookUrl, 'The read API returns defaults instead of throwing');
    }

    public function testOnlyEverUsesOneStage(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'a']);
        $manager->save('website', 'contact', 'en', ['phone_number' => 'b']);

        $stages = static::getEntityManager()->createQueryBuilder()
            ->select('DISTINCT dimensionContent.stage')
            ->from(WebspaceSettingsDimensionContent::class, 'dimensionContent')
            ->getQuery()
            ->getSingleColumnResult();

        $this->assertSame([WebspaceSettingsDimensionContentInterface::STAGE], $stages);
    }

    public function testSecondSaveUpdatesTheSameRecord(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'first']);
        $manager->save('website', 'social', 'en', ['facebook_url' => 'second']);

        $this->assertCount(2, $this->findDimensionContents('website', 'social'));
        $this->assertSame(
            'second',
            static::getSettings()->forWebspace('website')->forLocale('en')->raw('social')['facebook_url'],
        );
    }

    /**
     * @return list<WebspaceSettingsDimensionContentInterface>
     */
    private function findDimensionContents(string $webspaceKey, string $area): array
    {
        /** @var list<WebspaceSettingsDimensionContentInterface> $result */
        $result = static::getEntityManager()->createQueryBuilder()
            ->select('dimensionContent')
            ->from(WebspaceSettingsDimensionContent::class, 'dimensionContent')
            ->join('dimensionContent.settings', 'settings')
            ->where('settings.webspaceKey = :webspaceKey')
            ->andWhere('settings.area = :area')
            ->setParameter('webspaceKey', $webspaceKey)
            ->setParameter('area', $area)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @param list<WebspaceSettingsDimensionContentInterface> $rows
     */
    private function rowByLocale(array $rows, ?string $locale): WebspaceSettingsDimensionContentInterface
    {
        foreach ($rows as $row) {
            if ($row->getLocale() === $locale) {
                return $row;
            }
        }

        $this->fail(\sprintf('No dimension content for locale "%s".', $locale ?? 'null'));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    private function onlyKeys(array $data, array $keys): array
    {
        return \array_intersect_key($data, \array_flip($keys));
    }
}
