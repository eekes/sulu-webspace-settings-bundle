<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Security\AreaPermissionMode;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;

/**
 * Checks the umbrella context and, depending on {@see AreaPermissionMode}, the per-area context
 * too.
 *
 * The default follows Sulu's own article template-group pattern: with a single area the umbrella
 * context is the only one, so a project that never splits its settings does not have to hand out
 * a second permission. The price is that the permission set changes shape on the deploy that adds
 * a second area - `permissions.per_area: always` trades the convenience for a set that never
 * moves.
 *
 * @internal
 */
final class SettingsSecurityChecker
{
    public function __construct(
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly AreaPermissionMode $mode = AreaPermissionMode::AUTO,
    ) {
    }

    /**
     * Whether areas carry a security context of their own at all, which every caller that builds
     * or checks one has to agree on.
     */
    public function usesAreaSecurityContexts(): bool
    {
        return $this->mode->appliesTo(\count($this->areaRegistry->getAreas()));
    }

    /**
     * The umbrella context on its own, for callers that have to answer before they know which area
     * is meant - resolving one reports which areas exist, which is already more than a user
     * without this permission may see.
     */
    public function checkWebspacePermission(string $webspaceKey, string $permission): void
    {
        $this->securityChecker->checkPermission(
            new SecurityCondition(WebspaceSettingsAdmin::getSecurityContext($webspaceKey)),
            $permission,
        );
    }

    public function checkPermission(string $webspaceKey, string $areaKey, string $permission): void
    {
        $this->checkWebspacePermission($webspaceKey, $permission);

        $areaSecurityContext = $this->resolveAreaSecurityContext($webspaceKey, $areaKey);

        if (null === $areaSecurityContext) {
            return;
        }

        $this->securityChecker->checkPermission(new SecurityCondition($areaSecurityContext), $permission);
    }

    public function hasPermission(string $webspaceKey, string $areaKey, string $permission): bool
    {
        if (!$this->securityChecker->hasPermission(
            WebspaceSettingsAdmin::getSecurityContext($webspaceKey),
            $permission,
        )) {
            return false;
        }

        $areaSecurityContext = $this->resolveAreaSecurityContext($webspaceKey, $areaKey);

        if (null === $areaSecurityContext) {
            return true;
        }

        return $this->securityChecker->hasPermission($areaSecurityContext, $permission);
    }

    /**
     * Null when the umbrella context is the only one.
     */
    public function resolveAreaSecurityContext(string $webspaceKey, string $areaKey): ?string
    {
        if (!$this->usesAreaSecurityContexts()) {
            return null;
        }

        return WebspaceSettingsAdmin::getAreaSecurityContext($webspaceKey, $areaKey);
    }
}
