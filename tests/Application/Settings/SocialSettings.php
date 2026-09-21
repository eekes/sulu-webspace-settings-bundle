<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Application\Settings;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\AsSettingsArea;

#[AsSettingsArea(
    key: 'social',
    title: 'app.settings.social',
    webspaces: ['*'],
    order: 10,
    group: 'app.settings.group.marketing',
    icon: 'su-share',
)]
final class SocialSettings
{
    /**
     * @param array<int, array<string, mixed>> $links
     * @param array<int, mixed> $teasers
     * @param array<int, string> $tags
     */
    public function __construct(
        public readonly ?string $facebookUrl = null,
        public readonly array $links = [],
        public readonly array $teasers = [],
        // a `link` property resolves to the url it points at, whatever provider it uses
        public readonly ?string $link = null,
        // a `date` property resolves to a `Y-m-d` string
        public readonly ?string $campaignStart = null,
        public readonly array $tags = [],
    ) {
    }
}
