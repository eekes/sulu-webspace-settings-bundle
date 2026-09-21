<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Area;

/**
 * The identifier a settings record is referenced and cache-tagged by.
 *
 * Both sides - the reference store that tags a rendered page and the cache manager that
 * invalidates it - must build it the same way, otherwise invalidation silently never reaches
 * anything.
 */
final class SettingsIdentifier
{
    private function __construct()
    {
    }

    /**
     * The separator cannot occur in a webspace key or an area key, which a single hyphen can:
     * webspace `foo` with area `bar-baz` and webspace `foo-bar` with area `baz` would otherwise
     * produce the same identifier and invalidate each other's pages.
     *
     * It ends up inside a cache tag, so it also has to survive a Varnish ban regex - which rules
     * out a pipe and anything else with a meaning there.
     */
    public const SEPARATOR = '::';

    public static function create(string $webspaceKey, string $areaKey): string
    {
        return $webspaceKey . self::SEPARATOR . $areaKey;
    }
}
