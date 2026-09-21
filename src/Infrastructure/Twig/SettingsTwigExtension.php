<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Twig;

use Eekes\SuluWebspaceSettingsBundle\Application\Reader\ResolvedSettings;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ```twig
 * {{ settings('social').facebook_url }}
 * {{ settings('social', 'other-webspace').facebook_url }}
 * ```.
 *
 * A lazy function, not an eager global: a global would load settings on every render, including
 * the renders that never touch them.
 */
final class SettingsTwigExtension extends AbstractExtension
{
    public function __construct(private readonly SettingsInterface $settings)
    {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('settings', $this->resolved(...)),
            new TwigFunction('settings_raw', $this->raw(...)),
        ];
    }

    public function resolved(string $areaKey, ?string $webspaceKey = null, ?string $locale = null): ResolvedSettings
    {
        return $this->scoped($webspaceKey, $locale)->resolved($areaKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(string $areaKey, ?string $webspaceKey = null, ?string $locale = null): array
    {
        return $this->scoped($webspaceKey, $locale)->raw($areaKey);
    }

    private function scoped(?string $webspaceKey, ?string $locale): SettingsInterface
    {
        $settings = $this->settings;

        if (null !== $webspaceKey) {
            $settings = $settings->forWebspace($webspaceKey);
        }

        if (null !== $locale) {
            $settings = $settings->forLocale($locale);
        }

        return $settings;
    }
}
