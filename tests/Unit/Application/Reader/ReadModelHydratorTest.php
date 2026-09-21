<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Reader;

use Eekes\SuluWebspaceSettingsBundle\Application\Reader\ReadModelHydrator;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\MissingSettingValueException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingValueTypeMismatchException;
use Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings\SocialSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReadModelHydratorTest extends TestCase
{
    public function testMapsSnakeCaseToCamelCase(): void
    {
        $model = (new ReadModelHydrator())->hydrate(SocialSettings::class, [
            'facebook_url' => 'https://facebook.com/sulu',
            'links' => [['label' => 'Docs', 'url' => 'https://docs.sulu.io']],
        ]);

        $this->assertSame('https://facebook.com/sulu', $model->facebookUrl);
        $this->assertSame([['label' => 'Docs', 'url' => 'https://docs.sulu.io']], $model->links);
    }

    public function testFallsBackToTheDefaultsOfAnAreaThatWasNeverSaved(): void
    {
        $model = (new ReadModelHydrator())->hydrate(SocialSettings::class, []);

        $this->assertNull($model->facebookUrl);
        $this->assertSame([], $model->links);
        $this->assertSame([], $model->teasers);
    }

    public function testAlsoMatchesAnExactPropertyName(): void
    {
        $model = (new ReadModelHydrator())->hydrate(SocialSettings::class, ['facebookUrl' => 'https://example.com']);

        $this->assertSame('https://example.com', $model->facebookUrl);
    }

    public function testFailsLoudlyOnAMissingRequiredValue(): void
    {
        $this->expectException(MissingSettingValueException::class);
        $this->expectExceptionMessage('checkout_notice');

        (new ReadModelHydrator())->hydrate(RequiredNoticeSettings::class, []);
    }

    public function testHydratesAModelWithoutConstructor(): void
    {
        $model = (new ReadModelHydrator())->hydrate(NoConstructorSettings::class, ['anything' => 'ignored']);

        $this->assertInstanceOf(NoConstructorSettings::class, $model);
    }

    /**
     * A raw TypeError names the constructor and the argument position, never the model - which is
     * the one thing that points at the read model and template that disagree.
     */
    public function testExplainsAValueThatDoesNotFitTheConstructor(): void
    {
        $this->expectException(SettingValueTypeMismatchException::class);
        $this->expectExceptionMessage(RequiredNoticeSettings::class);

        (new ReadModelHydrator())->hydrate(RequiredNoticeSettings::class, ['checkout_notice' => ['not', 'a', 'string']]);
    }

    public function testKeepsTheOriginalTypeErrorAsThePreviousException(): void
    {
        try {
            (new ReadModelHydrator())->hydrate(RequiredNoticeSettings::class, ['checkout_notice' => ['still', 'not', 'a', 'string']]);
        } catch (SettingValueTypeMismatchException $exception) {
            $this->assertInstanceOf(\TypeError::class, $exception->getPrevious());
            $this->assertSame(RequiredNoticeSettings::class, $exception->getModelClass());

            return;
        }

        $this->fail('Expected a ' . SettingValueTypeMismatchException::class);
    }

    #[DataProvider('provideSnakeCaseCases')]
    public function testToSnakeCase(string $input, string $expected): void
    {
        $this->assertSame($expected, ReadModelHydrator::toSnakeCase($input));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideSnakeCaseCases(): iterable
    {
        yield ['facebookUrl', 'facebook_url'];
        yield ['url', 'url'];
        yield ['openingHoursMonday', 'opening_hours_monday'];
        yield ['seoURL', 'seo_u_r_l'];
    }
}

final class RequiredNoticeSettings
{
    public function __construct(public readonly string $checkoutNotice)
    {
    }
}

final class NoConstructorSettings
{
}
