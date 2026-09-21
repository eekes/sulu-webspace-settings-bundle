<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Manager;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistry;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsLoader;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsManager;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsCache;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsCreatedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Event\SettingsModifiedEvent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettings;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContent;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference\SettingsReferenceUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Application\ContentResolver\ContentViewResolver\ContentViewResolverInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The admin form sends a PUT even when nothing was touched, so a log full of no-op entries is the
 * failure mode this guards against.
 */
class SettingsManagerTest extends TestCase
{
    private WebspaceSettingsRepositoryInterface&MockObject $repository;

    private ContentAggregatorInterface&MockObject $contentAggregator;

    private ContentPersisterInterface&MockObject $contentPersister;

    private DomainEventCollectorInterface&MockObject $domainEventCollector;

    private SettingsCache $readerCache;

    private SettingsReferenceUpdater $referenceUpdater;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WebspaceSettingsRepositoryInterface::class);
        $this->contentAggregator = $this->createMock(ContentAggregatorInterface::class);
        $this->contentPersister = $this->createMock(ContentPersisterInterface::class);
        $this->domainEventCollector = $this->createMock(DomainEventCollectorInterface::class);
        $this->readerCache = new SettingsCache();
        // the real one over a double: it is final, and with an empty set of content views it
        // does nothing that this test cares about anyway
        $this->referenceUpdater = new SettingsReferenceUpdater(
            $this->createMock(ReferenceRepositoryInterface::class),
            $this->createMock(ContentViewResolverInterface::class),
        );
    }

    public function testDispatchesCreatedOnTheFirstSave(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);
        $this->repository->method('create')->willReturn(new WebspaceSettings('website', 'social'));

        $this->contentPersister->method('persist')->willReturn($this->dimensionContent(['facebook_url' => 'a']));

        $collected = $this->captureCollectedEvents();

        $this->createManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);

        $this->assertCount(1, $collected);
        $this->assertInstanceOf(SettingsCreatedEvent::class, $collected[0]);
        $this->assertSame('created', $collected[0]->getEventType());
        $this->assertSame('website', $collected[0]->getResourceId());
        $this->assertSame('Social', $collected[0]->getResourceTitle());
        $this->assertNull($collected[0]->getResourceLocale(), 'A non-translatable area logs no locale');
        $this->assertSame(['area' => 'social'], $collected[0]->getEventContext());
        $this->assertSame(
            'sulu.webspaces.website.settings_social',
            $collected[0]->getResourceSecurityContext(),
            'An area a user may not open must not show up in their trail either',
        );
    }

    public function testDispatchesModifiedWhenTheDataChanged(): void
    {
        $settings = new WebspaceSettings('website', 'social');
        $this->repository->method('findOneBy')->willReturn($settings);

        $this->contentAggregator->method('aggregate')->willReturn($this->dimensionContent(['facebook_url' => 'a']));
        $this->contentPersister->method('persist')->willReturn($this->dimensionContent(['facebook_url' => 'b']));

        $collected = $this->captureCollectedEvents();

        $this->createManager()->save('website', 'social', 'en', ['facebook_url' => 'b']);

        $this->assertCount(1, $collected);
        $this->assertInstanceOf(SettingsModifiedEvent::class, $collected[0]);
    }

    public function testDispatchesNothingWhenNothingChanged(): void
    {
        $settings = new WebspaceSettings('website', 'social');
        $this->repository->method('findOneBy')->willReturn($settings);

        $this->contentAggregator->method('aggregate')->willReturn($this->dimensionContent(['facebook_url' => 'a']));
        $this->contentPersister->method('persist')->willReturn($this->dimensionContent(['facebook_url' => 'a']));

        $this->domainEventCollector->expects($this->never())->method('collect');

        $this->createManager()->save('website', 'social', 'en', ['facebook_url' => 'a']);
    }

    public function testIgnoresKeyOrderWhenComparing(): void
    {
        $settings = new WebspaceSettings('website', 'social');
        $this->repository->method('findOneBy')->willReturn($settings);

        $this->contentAggregator->method('aggregate')->willReturn(
            $this->dimensionContent(['facebook_url' => 'a', 'links' => [['url' => 'u', 'label' => 'l']]]),
        );
        $this->contentPersister->method('persist')->willReturn(
            $this->dimensionContent(['links' => [['label' => 'l', 'url' => 'u']], 'facebook_url' => 'a']),
        );

        $this->domainEventCollector->expects($this->never())->method('collect');

        $this->createManager()->save('website', 'social', 'en', []);
    }

    public function testLogsTheLocaleOfATranslatableArea(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);
        $this->repository->method('create')->willReturn(new WebspaceSettings('website', 'contact'));
        $this->contentPersister->method('persist')->willReturn($this->dimensionContent([]));

        $collected = $this->captureCollectedEvents();

        $this->createManager()->save('website', 'contact', 'de', []);

        $this->assertInstanceOf(SettingsCreatedEvent::class, $collected[0]);
        $this->assertSame('de', $collected[0]->getResourceLocale());
    }

    /**
     * @return \ArrayObject<int, DomainEvent>
     */
    private function captureCollectedEvents(): \ArrayObject
    {
        /** @var \ArrayObject<int, DomainEvent> $collected */
        $collected = new \ArrayObject();

        $this->domainEventCollector->method('collect')
            ->willReturnCallback(static function (DomainEvent $event) use ($collected): void {
                $collected->append($event);
            });

        return $collected;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function dimensionContent(array $templateData): WebspaceSettingsDimensionContent
    {
        $dimensionContent = new WebspaceSettingsDimensionContent(new WebspaceSettings('website', 'social'));
        $dimensionContent->setTemplateData($templateData);

        return $dimensionContent;
    }

    private function createManager(): SettingsManager
    {
        $registry = new SettingsAreaRegistry([
            'social' => (new SettingsArea('social', 'app.settings.social', 'social', ['*'], 10, null))->toArray(),
            'contact' => (new SettingsArea('contact', 'app.settings.contact', 'contact', ['*'], 20, null))->toArray(),
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => 'app.settings.social' === $id ? 'Social' : 'Contact',
        );

        return new SettingsManager(
            $registry,
            $this->repository,
            new SettingsLoader($this->repository, $this->contentAggregator),
            new SettingsAreaLocalization($this->createFormMetadataLoader(), $translator),
            $this->contentPersister,
            $this->domainEventCollector,
            $translator,
            $this->readerCache,
            new SettingsSecurityChecker($this->createMock(SecurityCheckerInterface::class), $registry),
            $this->referenceUpdater,
        );
    }

    /**
     * "social" has no multilingual field left and is therefore not localized; "contact" keeps one
     * and is. That is the whole rule the manager asks about.
     */
    private function createFormMetadataLoader(): FormMetadataLoaderInterface
    {
        $typedFormMetadata = new TypedFormMetadata();
        $typedFormMetadata->addForm('social', $this->createFormMetadata('social', false));
        $typedFormMetadata->addForm('contact', $this->createFormMetadata('contact', true));

        $loader = $this->createMock(FormMetadataLoaderInterface::class);
        $loader->method('getMetadata')->willReturn($typedFormMetadata);

        return $loader;
    }

    private function createFormMetadata(string $key, bool $multilingual): FormMetadata
    {
        $field = new FieldMetadata('field');
        $field->setMultilingual($multilingual);

        $formMetadata = new FormMetadata();
        $formMetadata->setKey($key);
        $formMetadata->addItem($field);

        return $formMetadata;
    }
}
