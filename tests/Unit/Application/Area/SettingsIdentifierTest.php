<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsIdentifier;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;

class SettingsIdentifierTest extends TestCase
{
    public function testBuildsAnIdentifierFromTheWebspaceAndTheArea(): void
    {
        $this->assertSame('website~social', SettingsIdentifier::create('website', 'social'));
    }

    /**
     * Both halves may contain hyphens, so a single hyphen would make two different records share
     * one cache tag and invalidate each other's pages.
     */
    public function testKeepsHyphenatedKeysApart(): void
    {
        $this->assertNotSame(
            SettingsIdentifier::create('foo', 'bar-baz'),
            SettingsIdentifier::create('foo-bar', 'baz'),
        );
    }

    /**
     * Sulu's default HTTP cache stores pages through Symfony Cache, which refuses a tag with a
     * reserved character. The store swallows that in prod, so a page that read a setting was never
     * cached at all.
     */
    public function testCanBeUsedAsACacheTag(): void
    {
        foreach ([['website', 'social'], ['my-site', 'opening_hours'], ['foo_bar', 'bar-baz']] as [$webspaceKey, $areaKey]) {
            $this->assertFalse(
                \strpbrk(SettingsIdentifier::create($webspaceKey, $areaKey), ItemInterface::RESERVED_CHARACTERS),
                \sprintf('The identifier of %s/%s contains a character Symfony Cache reserves.', $webspaceKey, $areaKey),
            );
        }
    }

    /**
     * FOSHttpCache quotes a tag with preg_quote() before a Varnish ban; a separator it leaves alone
     * keeps the ban readable and does not depend on that escaping.
     */
    public function testSurvivesAVarnishBanUnchanged(): void
    {
        $identifier = SettingsIdentifier::create('website', 'social');

        $this->assertSame($identifier, \preg_quote($identifier));
    }
}
