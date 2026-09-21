<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettings;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;

/**
 * @internal no backwards compatibility promise is given for this class
 */
class WebspaceSettingsRepository implements WebspaceSettingsRepositoryInterface
{
    /**
     * @var EntityRepository<WebspaceSettings>
     */
    private EntityRepository $entityRepository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->entityRepository = $this->entityManager->getRepository(WebspaceSettings::class);
    }

    public function findOneBy(string $webspaceKey, string $area): ?WebspaceSettingsInterface
    {
        return $this->entityRepository->createQueryBuilder('settings')
            // eager load, otherwise ContentAggregator refuses the lazy collection in debug mode
            ->leftJoin('settings.dimensionContents', 'dimensionContent')
            ->addSelect('dimensionContent')
            ->where('settings.webspaceKey = :webspaceKey')
            ->andWhere('settings.area = :area')
            ->setParameter('webspaceKey', $webspaceKey)
            ->setParameter('area', $area)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(): array
    {
        /** @var list<WebspaceSettingsInterface> $settings */
        $settings = $this->entityRepository->createQueryBuilder('settings')
            ->orderBy('settings.webspaceKey', 'ASC')
            ->addOrderBy('settings.area', 'ASC')
            ->getQuery()
            ->getResult();

        return $settings;
    }

    public function create(string $webspaceKey, string $area): WebspaceSettingsInterface
    {
        return new WebspaceSettings($webspaceKey, $area);
    }

    public function add(WebspaceSettingsInterface $settings): void
    {
        $this->entityManager->persist($settings);
    }

    public function remove(WebspaceSettingsInterface $settings): void
    {
        $this->entityManager->remove($settings);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
