<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Security;

/**
 * Whether every area gets a security context of its own on top of the webspace one.
 *
 * Configured as `sulu_webspace_settings.permissions.per_area`.
 */
enum AreaPermissionMode: string
{
    /**
     * Per-area contexts as soon as a second area exists.
     *
     * Convenient, and the shape of the permission set then changes on the deploy that adds the
     * second area: roles that only have the webspace context lose access until an administrator
     * grants the new ones. Projects that expect to grow past one area are better off with
     * {@see self::ALWAYS} from the start.
     */
    case AUTO = 'auto';

    /**
     * Always, including with a single area. The permission set never changes shape.
     */
    case ALWAYS = 'always';

    /**
     * Never. One permission per webspace governs every area in it.
     */
    case NEVER = 'never';

    public function appliesTo(int $areaCount): bool
    {
        return match ($this) {
            self::ALWAYS => true,
            self::NEVER => false,
            self::AUTO => $areaCount > 1,
        };
    }
}
