<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

/**
 * A single property of a translatable area can opt out of being translated.
 *
 * `multilingual="false"` is Sulu's own attribute: `PropertiesXmlParser` reads it into
 * `FieldMetadata`, and `TemplateDataMapper` writes such a property to the unlocalized dimension
 * content instead of the localized one. The bundle persists through `ContentPersisterInterface`,
 * so this works without the bundle knowing about it - which is exactly what has to be proven, and
 * kept proven.
 */
class SettingsMultilingualTest extends WebspaceSettingsTestCase
{
    public function testSharesAPropertyThatOptsOutOfTranslation(): void
    {
        static::getSettingsManager()->save('website', 'contact', 'en', [
            'opening_hours' => 'Mon-Fri 9:00-17:00',
            'vat_number' => 'BE0123456789',
        ]);

        $german = static::getSettings()->forWebspace('website')->forLocale('de')->raw('contact');

        $this->assertSame(
            'BE0123456789',
            $german['vat_number'] ?? null,
            'A property marked multilingual="false" is stored once and read in every locale',
        );
        $this->assertArrayNotHasKey(
            'opening_hours',
            $german,
            'Everything else stays per locale',
        );
    }

    public function testKeepsTheSharedPropertyWhenAnotherLocaleIsSaved(): void
    {
        static::getSettingsManager()->save('website', 'contact', 'en', [
            'opening_hours' => 'Mon-Fri 9:00-17:00',
            'vat_number' => 'BE0123456789',
        ]);

        static::getSettingsManager()->save('website', 'contact', 'de', [
            'opening_hours' => 'Mo-Fr 9:00-17:00',
            'vat_number' => 'BE0123456789',
        ]);

        $english = static::getSettings()->forWebspace('website')->forLocale('en')->raw('contact');
        $german = static::getSettings()->forWebspace('website')->forLocale('de')->raw('contact');

        $this->assertSame('Mon-Fri 9:00-17:00', $english['opening_hours']);
        $this->assertSame('Mo-Fr 9:00-17:00', $german['opening_hours']);
        $this->assertSame('BE0123456789', $english['vat_number']);
        $this->assertSame('BE0123456789', $german['vat_number']);
    }
}
