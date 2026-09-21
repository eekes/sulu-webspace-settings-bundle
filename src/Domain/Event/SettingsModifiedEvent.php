<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Event;

/**
 * Dispatched on every save after the first that actually changes something.
 *
 * The admin form sends a PUT even when nothing was touched, and a log full of no-op entries is
 * worse than no log, so the manager compares stored and incoming data first.
 */
class SettingsModifiedEvent extends AbstractSettingsEvent
{
    public function getEventType(): string
    {
        return 'modified';
    }
}
