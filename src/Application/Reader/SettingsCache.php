<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Reader;

use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The memo of {@see Settings}, shared with every reader that `forWebspace()` and `forLocale()`
 * derive from it.
 *
 * Sharing is what makes the documented contract true: an area is loaded and resolved once, not
 * once per call. `forWebspace()` returns a new reader, so without a shared memo
 * `{{ settings('social', 'shop').a }}{{ settings('social', 'shop').b }}` would hit the database
 * and re-run every smart content query in the area twice.
 *
 * Every entry is keyed by webspace, area and locale, so a scoped reader can only ever read back
 * what belongs to its own scope.
 *
 * Sharing a memo across a whole process is only safe if something empties it again. Two things do:
 * {@see SettingsManager::save()} evicts the area it just wrote, and `kernel.reset` empties the
 * whole memo between messenger messages and between requests in a worker runtime. Without either,
 * a long running process would serve the values it read first until it was restarted, and a
 * command that writes and then reads would never see its own write.
 *
 * @internal
 */
final class SettingsCache implements ResetInterface
{
    /**
     * @var array<string, WebspaceSettingsDimensionContentInterface|null>
     */
    private array $dimensionContents = [];

    /**
     * @var array<string, ResolvedSettings>
     */
    private array $resolved = [];

    /**
     * Both halves of the memo are keyed the same way, and {@see evict()} depends on the area
     * prefix being unambiguous - hence a separator that cannot occur in a webspace or area key.
     */
    public static function key(string $webspaceKey, string $areaKey, string $locale): string
    {
        return $webspaceKey . '|' . $areaKey . '|' . $locale;
    }

    public function hasDimensionContent(string $key): bool
    {
        return \array_key_exists($key, $this->dimensionContents);
    }

    public function getDimensionContent(string $key): ?WebspaceSettingsDimensionContentInterface
    {
        return $this->dimensionContents[$key] ?? null;
    }

    public function setDimensionContent(
        string $key,
        ?WebspaceSettingsDimensionContentInterface $dimensionContent,
    ): ?WebspaceSettingsDimensionContentInterface {
        return $this->dimensionContents[$key] = $dimensionContent;
    }

    /**
     * @param \Closure(): ResolvedSettings $resolve
     */
    public function resolved(string $key, \Closure $resolve): ResolvedSettings
    {
        return $this->resolved[$key] ??= $resolve();
    }

    /**
     * Drops one area of one webspace, in every locale.
     *
     * The locale is not part of it on purpose: a non-translatable field is shared across locales,
     * so writing one locale can change what every other locale reads back.
     */
    public function evict(string $webspaceKey, string $areaKey): void
    {
        $prefix = $webspaceKey . '|' . $areaKey . '|';

        foreach (\array_keys($this->dimensionContents) as $key) {
            if (\str_starts_with($key, $prefix)) {
                unset($this->dimensionContents[$key]);
            }
        }

        foreach (\array_keys($this->resolved) as $key) {
            if (\str_starts_with($key, $prefix)) {
                unset($this->resolved[$key]);
            }
        }
    }

    public function reset(): void
    {
        $this->dimensionContents = [];
        $this->resolved = [];
    }
}
