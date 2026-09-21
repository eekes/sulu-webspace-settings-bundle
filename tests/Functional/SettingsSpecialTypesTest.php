<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Sulu\Bundle\TagBundle\Entity\Tag;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Every content type a settings area can reasonably hold, resolved from a console command.
 *
 * Settings are not pages: nothing here goes through a request, a webspace analyzer or a structure.
 * A content type that only resolved inside a page request would break that promise, so the whole
 * catalogue is exercised, not the two or three types the rest of the suite happens to use.
 *
 * Selections point at ids that do not exist on purpose. An editor deletes a media item or a page
 * that a setting still references, and the answer has to be null or an empty list on every
 * website request - not an exception.
 */
class SettingsSpecialTypesTest extends WebspaceSettingsTestCase
{
    public function testResolvesEveryContentTypeFromAConsoleCommand(): void
    {
        $tagId = $this->createTag('Sulu');

        static::getSettingsManager()->save('website', 'social', 'en', [
            'facebook_url' => 'https://facebook.com/sulu',
            'link' => [
                'provider' => 'external',
                'href' => 'https://sulu.io',
                'title' => 'Sulu',
                'target' => '_blank',
            ],
            'logo' => ['id' => 404, 'displayOption' => 'left'],
            'media' => ['ids' => [404]],
            'landing_page' => '11111111-1111-1111-1111-111111111111',
            'related_pages' => ['22222222-2222-2222-2222-222222222222'],
            'categories' => [404],
            'tags' => [$tagId],
            'highlights' => [
                'presentAs' => null,
                'items' => [
                    ['type' => 'pages', 'id' => '44444444-4444-4444-4444-444444444444'],
                ],
            ],
            'campaign_start' => '2026-09-18',
            'teasers' => [
                'dataSource' => null,
                'includeSubFolders' => true,
                'limitResult' => 3,
                'sortBy' => 'title',
                'sortMethod' => 'asc',
                'tags' => [],
                'categories' => [],
            ],
            'links' => [
                ['type' => 'link', 'label' => 'Docs', 'url' => 'https://docs.sulu.io'],
            ],
        ]);

        $resolved = $this->runDumpCommand('website', 'social', 'en');

        // resolved to a real value
        $this->assertSame('https://facebook.com/sulu', $resolved['facebook_url']);
        $this->assertSame('https://sulu.io', $resolved['link'], 'A link resolves to its url');
        $this->assertSame('2026-09-18', $resolved['campaign_start']);
        $this->assertSame([['type' => 'link', 'label' => 'Docs', 'url' => 'https://docs.sulu.io']], $resolved['links']);
        $this->assertSame(
            ['Sulu'],
            $resolved['tags'],
            'A tag selection resolves to its names through the resource loader, without a request',
        );

        // resolved, but pointing at something that is gone
        $this->assertNull($resolved['logo']);
        $this->assertNull($resolved['landing_page']);
        $this->assertSame([], $resolved['media']);
        $this->assertSame([], $resolved['related_pages']);
        $this->assertSame([], $resolved['categories']);
        $this->assertSame([], $resolved['highlights']);

        // a live query, executed without a request
        $this->assertIsArray($resolved['teasers']);
    }

    private function createTag(string $name): int
    {
        $entityManager = static::getEntityManager();

        $tag = new Tag();
        $tag->setName($name);

        $entityManager->persist($tag);
        $entityManager->flush();

        return $tag->getId();
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
