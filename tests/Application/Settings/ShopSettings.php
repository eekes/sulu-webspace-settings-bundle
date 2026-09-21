<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\AsSettingsArea;

#[AsSettingsArea(
    key: 'shop',
    title: 'app.settings.shop',
    webspaces: ['shop'],
    order: 30,
    group: 'app.settings.group.shop',
    icon: 'su-tag',
)]
final class ShopSettings
{
    public function __construct(
        public readonly ?string $checkoutNotice = null,
    ) {
    }
}
