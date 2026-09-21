<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Model;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\TemplateInterface;

/**
 * @extends DimensionContentInterface<WebspaceSettingsInterface>
 */
interface WebspaceSettingsDimensionContentInterface extends DimensionContentInterface, TemplateInterface
{
    /**
     * The single stage the bundle uses.
     *
     * There is no workflow, so there is no mechanism to move content between stages. Every read
     * and every write therefore uses this one stage, and a stage is never accepted from a caller.
     */
    public const STAGE = DimensionContentInterface::STAGE_DRAFT;

    public function getResource(): WebspaceSettingsInterface;
}
