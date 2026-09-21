<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Event;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\WebspaceSettingsAdmin;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

/**
 * There is no version UI, so the activity trail is the answer to "who changed this setting".
 *
 * The resource id is the webspace key, matching the activity view that is registered at webspace
 * level. The area travels in the event context and in the title.
 */
abstract class AbstractSettingsEvent extends DomainEvent
{
    /**
     * @param string $areaTitle the translated area title, frozen at dispatch time - `{resourceTitle}`
     *                          is stored with the event, so storing the translation key would make
     *                          the log read `app.settings.social`
     * @param string|null $securityContext the context the trail is scoped to; null falls back to
     *                                     the webspace one, which is right when areas have no
     *                                     context of their own
     */
    public function __construct(
        private readonly string $webspaceKey,
        private readonly SettingsArea $area,
        private readonly ?string $locale,
        private readonly string $areaTitle,
        private readonly ?string $securityContext = null,
    ) {
        parent::__construct();
    }

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }

    public function getArea(): SettingsArea
    {
        return $this->area;
    }

    public function getResourceKey(): string
    {
        return WebspaceSettingsInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->webspaceKey;
    }

    public function getResourceTitle(): ?string
    {
        return $this->areaTitle;
    }

    /**
     * Null for non-translatable areas; for translatable ones this shows which language was edited.
     */
    public function getResourceLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceWebspaceKey(): ?string
    {
        return $this->webspaceKey;
    }

    /**
     * Without this an editor of webspace A sees what changed in webspace B.
     *
     * It is the same context the endpoints are gated by, per-area one included: a trail scoped
     * more loosely than the form would tell an editor that "Integrations" changed, and what it is
     * called, in a webspace where they may not open it.
     */
    public function getResourceSecurityContext(): ?string
    {
        return $this->securityContext ?? WebspaceSettingsAdmin::getSecurityContext($this->webspaceKey);
    }

    /**
     * @return array{area: string}
     */
    public function getEventContext(): array
    {
        return ['area' => $this->area->key];
    }
}
