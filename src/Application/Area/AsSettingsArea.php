<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Area;

/**
 * Declares a settings area.
 *
 * The annotated class is both the declaration of the area and the typed read model that
 * {@see \Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsInterface::get()} returns. Its
 * constructor parameters are hydrated from the stored values by name, `snake_case` to `camelCase`.
 *
 * The fields themselves are declared in template XML, by default in
 * `config/templates/settings/<template>.xml`. Whether a field is stored once or once per locale
 * is declared there too, with Sulu's `multilingual` attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsSettingsArea
{
    public const TAG = 'sulu_webspace_settings.area';

    /**
     * @param string $key unique key of the area, used in URLs, the API and `settings('<key>')`
     * @param string|null $title translation key shown in the area select; defaults to the key
     *                           made readable - `social_media` reads as "Social media". A
     *                           translation key would be the obvious default, but the bundle can
     *                           never ship a translation for one built from your area key
     * @param string|null $template key of the template XML holding the fields; defaults to $key
     * @param string[] $webspaces webspace keys this area is available for, or `['*']` for all
     * @param int $order sort order within the group, lower comes first
     * @param string|null $group translation key of the dropdown this area is listed under;
     *                           defaults to `sulu_webspace_settings.settings`, which reads
     *                           "Settings". A plain string works too: an unknown translation key
     *                           is shown as it is
     * @param string|null $icon icon of that dropdown, e.g. `su-plug`; defaults to `su-cog`
     */
    public function __construct(
        public string $key,
        public ?string $title = null,
        public ?string $template = null,
        public array $webspaces = ['*'],
        public int $order = 0,
        public ?string $group = null,
        public ?string $icon = null,
    ) {
    }
}
