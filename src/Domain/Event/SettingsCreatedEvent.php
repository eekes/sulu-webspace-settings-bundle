<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Event;

/**
 * Dispatched on the first save of an area for a webspace - records are created lazily.
 */
class SettingsCreatedEvent extends AbstractSettingsEvent
{
    public function getEventType(): string
    {
        return 'created';
    }
}
