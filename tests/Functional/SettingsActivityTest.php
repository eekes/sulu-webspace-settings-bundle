<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\ActivityBundle\Domain\Model\ActivityInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * There is no draft/publish and no version UI, so the activity trail is the only answer to "who
 * changed this setting and when".
 */
class SettingsActivityTest extends WebspaceSettingsTestCase
{
    public function testLogsTheFirstSaveAsCreated(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'https://facebook.com/sulu']);

        $activities = $this->findActivities();

        $this->assertCount(1, $activities);
        $this->assertSame('created', $activities[0]->getType());
        $this->assertSame('webspace_settings', $activities[0]->getResourceKey());
        $this->assertSame('website', $activities[0]->getResourceId(), 'The log is per webspace');
        $this->assertSame(['area' => 'social'], $activities[0]->getContext());
        $this->assertSame(
            'sulu.webspaces.website.settings_social',
            $activities[0]->getResourceSecurityContext(),
            'The trail is scoped exactly like the endpoints: per webspace, and per area when areas '
            . 'carry a context of their own',
        );
    }

    public function testLogsALaterSaveAsModified(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'a']);
        $manager->save('website', 'social', 'en', ['facebook_url' => 'b']);

        $activities = $this->findActivities();

        $this->assertCount(2, $activities);
        $this->assertSame('created', $activities[0]->getType());
        $this->assertSame('modified', $activities[1]->getType());
    }

    public function testDoesNotLogASaveThatChangedNothing(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'social', 'en', ['facebook_url' => 'a']);
        $manager->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $this->assertCount(1, $this->findActivities(), 'The admin sends a PUT even when nothing was touched');
    }

    public function testDoesNotLogAnEmptySaveOfAnUntouchedArea(): void
    {
        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=social&locale=en', []);

        $this->assertCount(1, $this->findActivities(), 'The very first save is still a creation');

        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=social&locale=en', []);

        $this->assertCount(1, $this->findActivities());
    }

    public function testLogsTheLocaleOfATranslatableAreaOnly(): void
    {
        $manager = static::getSettingsManager();
        $manager->save('website', 'contact', 'de', ['phone_number' => '+49 2']);
        $manager->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $activities = $this->findActivities();

        $contact = $activities[0];
        $social = $activities[1];

        $this->assertSame('de', $contact->getResourceLocale());
        $this->assertNull($social->getResourceLocale());
    }

    public function testRecordsWhoMadeTheChange(): void
    {
        $this->client->jsonRequest(
            'PUT',
            '/admin/api/webspace-settings/website?area=social&locale=en',
            ['facebook_url' => 'https://facebook.com/sulu'],
        );

        $user = $this->findActivities()[0]->getUser();

        $this->assertNotNull($user, 'The acting user comes from the security context');
        $this->assertSame(static::getTestUserId(), $user->getId());
    }

    public function testLeavesTheUserEmptyOutsideARequest(): void
    {
        static::getSettingsManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $this->assertNull(
            $this->findActivities()[0]->getUser(),
            'A save from the CLI has nobody to attribute it to',
        );
    }

    /**
     * The list endpoint renders `{userFullName}` into the description, so a catalogue without the
     * placeholder stores who changed a setting but never shows it.
     */
    #[DataProvider('provideShippedLocales')]
    public function testNamesTheUserInEveryDescription(string $locale): void
    {
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get('translator');

        foreach (['created', 'modified'] as $type) {
            $this->assertStringContainsString(
                '{userFullName}',
                $translator->trans('sulu_activity.description.webspace_settings.' . $type, [], 'admin', $locale),
                \sprintf('The %s description in %s', $type, $locale),
            );
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideShippedLocales(): iterable
    {
        yield ['en'];
        yield ['nl'];
        yield ['de'];
        yield ['fr'];
    }

    public function testStoresTheTranslatedTitleRatherThanTheTranslationKey(): void
    {
        static::getSettingsManager()->save('shop', 'shop', 'en', ['checkout_notice' => 'Free shipping']);

        $title = $this->findActivities()[0]->getResourceTitle();

        $this->assertNotNull($title);
        $this->assertStringNotContainsString(
            'app.settings.',
            $title,
            '{resourceTitle} is frozen with the event, so a translation key would read back as one',
        );
    }

    /**
     * @return list<ActivityInterface>
     */
    private function findActivities(): array
    {
        static::getEntityManager()->clear();

        /** @var list<ActivityInterface> $activities */
        $activities = static::getEntityManager()->createQueryBuilder()
            ->select('activity')
            ->from(ActivityInterface::class, 'activity')
            ->where('activity.resourceKey = :resourceKey')
            ->setParameter('resourceKey', 'webspace_settings')
            ->orderBy('activity.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $activities;
    }
}
