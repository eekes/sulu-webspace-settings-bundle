<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Model;

use Sulu\Content\Domain\Model\ContentRichEntityInterface;

/**
 * One settings record: the values of one area for one webspace.
 *
 * The webspace is part of the identity, not a dimension - there is exactly one record per
 * webspace per area. Locale and stage remain dimensions and live on the dimension content.
 *
 * @extends ContentRichEntityInterface<WebspaceSettingsDimensionContentInterface>
 */
interface WebspaceSettingsInterface extends ContentRichEntityInterface
{
    public const RESOURCE_KEY = 'webspace_settings';

    public const TEMPLATE_TYPE = 'webspace_settings';

    public function getUuid(): string;

    public function getWebspaceKey(): string;

    /**
     * The area key, which doubles as the template key of the dimension content.
     */
    public function getArea(): string;
}
