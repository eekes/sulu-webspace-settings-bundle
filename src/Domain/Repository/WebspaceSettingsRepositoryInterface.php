<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Domain\Repository;

use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;

interface WebspaceSettingsRepositoryInterface
{
    /**
     * Records are created lazily on first save, so this returns null for a webspace that has
     * never had this area saved. Callers must cope with that instead of treating it as an error.
     */
    public function findOneBy(string $webspaceKey, string $area): ?WebspaceSettingsInterface;

    /**
     * Every stored record, including the ones whose area or webspace no longer exists.
     *
     * @return list<WebspaceSettingsInterface>
     */
    public function findAll(): array;

    public function create(string $webspaceKey, string $area): WebspaceSettingsInterface;

    public function add(WebspaceSettingsInterface $settings): void;

    public function remove(WebspaceSettingsInterface $settings): void;

    public function flush(): void;
}
