<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Reader;

use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\MissingWebspaceContextException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotAvailableException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotFoundException;

/**
 * Reads settings. This is the part consumers touch daily.
 *
 * ```php
 * $settings->raw('social');                 // stored values: ids, smart content configuration
 * $settings->resolved('social');            // media objects, executed queries, resolved per property
 * $settings->get(SocialSettings::class);    // typed read model
 * ```
 *
 * Reach for the typed read model. `raw()` exists for tooling - migration, validation, diffing -
 * and `resolved()` for templates that predate a typed model.
 */
interface SettingsInterface
{
    /**
     * Binds the reader to one webspace.
     *
     * Required outside a request; inside one the webspace is taken from the request.
     */
    public function forWebspace(string $webspaceKey): self;

    /**
     * Binds the reader to one locale.
     *
     * Without it the locale comes from the request, falling back to the webspace's default
     * localization.
     */
    public function forLocale(string $locale): self;

    /**
     * The stored values, exactly as they are persisted.
     *
     * Returns an empty array for an area that has never been saved - records are created lazily.
     *
     * @throws SettingsAreaNotFoundException
     * @throws SettingsAreaNotAvailableException
     * @throws MissingWebspaceContextException
     *
     * @return array<string, mixed>
     */
    public function raw(string $areaKey): array;

    /**
     * The resolved values, resolved lazily per property on first access.
     *
     * @throws SettingsAreaNotFoundException
     * @throws SettingsAreaNotAvailableException
     * @throws MissingWebspaceContextException
     */
    public function resolved(string $areaKey): ResolvedSettings;

    /**
     * The typed read model declared with #[AsSettingsArea].
     *
     * @template T of object
     *
     * @param class-string<T> $modelClass
     *
     * @throws MissingWebspaceContextException
     *
     * @return T
     */
    public function get(string $modelClass): object;
}
