<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Area;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsIdentifier;
use PHPUnit\Framework\TestCase;

class SettingsIdentifierTest extends TestCase
{
    public function testBuildsAnIdentifierFromTheWebspaceAndTheArea(): void
    {
        $this->assertSame('website::social', SettingsIdentifier::create('website', 'social'));
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
}
