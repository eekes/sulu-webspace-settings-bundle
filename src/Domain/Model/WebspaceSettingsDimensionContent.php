<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Component\Persistence\Model\AuditableTrait;
use Sulu\Content\Domain\Model\AuditableInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\DimensionContentTrait;
use Sulu\Content\Domain\Model\TemplateTrait;

/**
 * Deliberately without `WorkflowTrait`: settings are instant-live and draft/publish is out of
 * scope. See "No draft and publish" in the bundle README for why, and what it costs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ws_settings_dimension_contents')]
class WebspaceSettingsDimensionContent implements WebspaceSettingsDimensionContentInterface, AuditableInterface
{
    use AuditableTrait;
    use DimensionContentTrait;
    use TemplateTrait {
        TemplateTrait::getTemplateKey as private getStoredTemplateKey;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WebspaceSettings::class, inversedBy: 'dimensionContents')]
    #[ORM\JoinColumn(name: 'settingsUuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    protected WebspaceSettings $settings;

    public function __construct(WebspaceSettings $settings)
    {
        $this->settings = $settings;
        $this->created = new \DateTimeImmutable();
        $this->changed = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResource(): WebspaceSettingsInterface
    {
        return $this->settings;
    }

    /**
     * The area doubles as the template key.
     *
     * Falling back to the area matters for non-translatable areas: their values live in the
     * unlocalized dimension content, and `templateKey` is only written on the localized one. A
     * locale that was never saved would otherwise merge to a record without a template.
     */
    public function getTemplateKey(): ?string
    {
        return $this->getStoredTemplateKey() ?? $this->settings->getArea();
    }

    public static function getTemplateType(): string
    {
        return WebspaceSettingsInterface::TEMPLATE_TYPE;
    }

    public static function getResourceKey(): string
    {
        return WebspaceSettingsInterface::RESOURCE_KEY;
    }

    /**
     * Pins the stage: there is only ever one, on both the read and the write path.
     *
     * @return array{locale: string|null, stage: string, version: int}
     */
    public static function getDefaultDimensionAttributes(): array
    {
        return [
            'locale' => null,
            'stage' => self::STAGE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];
    }

    /**
     * Never lets a stage leak in from a caller.
     *
     * @param mixed[] $dimensionAttributes
     *
     * @return array{locale: string|null, stage: string, version: int}
     */
    public static function getEffectiveDimensionAttributes(array $dimensionAttributes): array
    {
        $dimensionAttributes['stage'] = self::STAGE;

        /** @var array{locale: string|null, stage: string, version: int} $attributes */
        $attributes = \array_merge(
            self::getDefaultDimensionAttributes(),
            \array_intersect_key($dimensionAttributes, self::getDefaultDimensionAttributes()),
        );

        return $attributes;
    }
}
