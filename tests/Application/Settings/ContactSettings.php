<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\AsSettingsArea;

#[AsSettingsArea(
    key: 'contact',
    title: 'app.settings.contact',
    webspaces: ['*'],
    order: 20,
)]
final class ContactSettings
{
    public function __construct(
        public readonly ?string $openingHours = null,
        public readonly ?string $phoneNumber = null,
    ) {
    }
}
