<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Area;

/**
 * Immutable description of one settings area, built from an
 * {@see AsSettingsArea} attribute.
 *
 * @phpstan-type SettingsAreaConfig array{
 *     key: string,
 *     title: string,
 *     template: string,
 *     webspaces: string[],
 *     order: int,
 *     group: string,
 *     icon: string,
 *     model: class-string|null,
 * }
 */
final class SettingsArea
{
    public const ALL_WEBSPACES = '*';

    /**
     * Areas that do not name a group end up in one dropdown labelled "Settings", which is what a
     * project with a handful of areas wants and never has to think about.
     */
    public const DEFAULT_GROUP = 'sulu_webspace_settings.settings';

    public const DEFAULT_ICON = 'su-cog';

    /**
     * @param string[] $webspaces
     * @param class-string|null $model
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $template,
        public readonly array $webspaces,
        public readonly int $order,
        public readonly ?string $model,
        public readonly string $group = self::DEFAULT_GROUP,
        public readonly string $icon = self::DEFAULT_ICON,
    ) {
    }

    /**
     * @param SettingsAreaConfig $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['key'],
            $config['title'],
            $config['template'],
            $config['webspaces'],
            $config['order'],
            $config['model'],
            $config['group'],
            $config['icon'],
        );
    }

    /**
     * @return SettingsAreaConfig
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'template' => $this->template,
            'webspaces' => $this->webspaces,
            'order' => $this->order,
            'group' => $this->group,
            'icon' => $this->icon,
            'model' => $this->model,
        ];
    }

    public function isAvailableForWebspace(string $webspaceKey): bool
    {
        if (\in_array(self::ALL_WEBSPACES, $this->webspaces, true)) {
            return true;
        }

        return \in_array($webspaceKey, $this->webspaces, true);
    }
}
