<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Content\Domain\Model\ContentRichEntityTrait;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ws_settings')]
#[ORM\UniqueConstraint(name: 'idx_ws_settings_identity', fields: ['webspaceKey', 'area'])]
class WebspaceSettings implements WebspaceSettingsInterface
{
    /** @use ContentRichEntityTrait<WebspaceSettingsDimensionContentInterface> */
    use ContentRichEntityTrait;

    #[ORM\Id]
    #[ORM\Column(name: 'uuid', type: 'string', length: 36)]
    protected string $uuid;

    // column names are pinned so the schema does not depend on the naming strategy a consuming
    // project happens to configure
    #[ORM\Column(name: 'webspaceKey', type: 'string', length: 100)]
    protected string $webspaceKey;

    #[ORM\Column(name: 'area', type: 'string', length: 64)]
    protected string $area;

    /**
     * @var \Doctrine\Common\Collections\Collection<int, WebspaceSettingsDimensionContentInterface>
     */
    #[ORM\OneToMany(
        targetEntity: WebspaceSettingsDimensionContent::class,
        mappedBy: 'settings',
        cascade: ['persist'],
    )]
    protected $dimensionContents; // @phpstan-ignore-line property is typed by the trait

    public function __construct(string $webspaceKey, string $area, ?string $uuid = null)
    {
        $this->webspaceKey = $webspaceKey;
        $this->area = $area;
        $this->uuid = $uuid ?: Uuid::v7()->__toString();

        $this->initializeDimensionContents();
    }

    public function getId(): string
    {
        return $this->uuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getWebspaceKey(): string
    {
        return $this->webspaceKey;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    /**
     * @return WebspaceSettingsDimensionContentInterface
     */
    public function createDimensionContent(): DimensionContentInterface
    {
        return new WebspaceSettingsDimensionContent($this);
    }
}
