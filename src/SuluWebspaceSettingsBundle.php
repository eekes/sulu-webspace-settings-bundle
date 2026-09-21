<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\AsSettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistry;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Controller\Admin\WebspaceSettingsController;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsLoader;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsManager;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsManagerInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\ReadModelHydrator;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\Settings;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsCache;
use Eekes\SuluWebspaceSettingsBundle\Application\Reader\SettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Security\AreaPermissionMode;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Repository\WebspaceSettingsRepositoryInterface;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Doctrine\Repository\WebspaceSettingsRepository;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\Metadata\SettingsAreaFormMetadataLoader;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\WebspaceSettingsAdmin;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\HttpCache\SettingsCacheInvalidationSubscriber;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference\SettingsReferenceRefresher;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Reference\SettingsReferenceUpdater;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Symfony\Command\PruneSettingsCommand;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Symfony\CompilerPass\SettingsAreaCompilerPass;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Twig\SettingsTwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @codeCoverageIgnore
 */
final class SuluWebspaceSettingsBundle extends AbstractBundle
{
    public const ALIAS = 'sulu_webspace_settings';

    public const DEFAULT_TEMPLATE_DIRECTORY = '%kernel.project_dir%/config/templates/settings';

    /**
     * @internal this method is not part of the public API and should only be called by Symfony
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        // The bundle works with zero lines of YAML; everything here has a default that fits a
        // stock project. It exists so the two decisions a project cannot work around - where its
        // templates live and how its permissions are cut - are not baked into the bundle.
        $definition->rootNode()
            ->children()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('directory')
                            ->defaultValue(self::DEFAULT_TEMPLATE_DIRECTORY)
                            ->info('Where the settings template XML lives. A bundle shipping its own areas prepends another directory instead of changing this.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('permissions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('per_area')
                            ->values(\array_column(AreaPermissionMode::cases(), 'value'))
                            ->defaultValue(AreaPermissionMode::AUTO->value)
                            ->info('Whether each area gets a security context on top of the webspace one. "auto" starts doing so at the second area, which changes the permission set on the deploy that adds it; "always" keeps the set stable from the start.')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{permissions: array{per_area: string}, templates: array{directory: string}} $config
     *
     * @internal this method is not part of the public API and should only be called by Symfony
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // Areas
        $services->set('sulu_webspace_settings.area_registry', SettingsAreaRegistry::class)
            ->args([param(SettingsAreaCompilerPass::AREAS_PARAMETER)])
            ->public();

        // whether an area stores anything per locale is read from its template, not declared
        $services->set('sulu_webspace_settings.area_localization', SettingsAreaLocalization::class)
            ->args([
                new Reference('sulu_admin.template_form_metadata_loader'),
                new Reference('translator'),
            ]);

        $services->alias(SettingsAreaRegistryInterface::class, 'sulu_webspace_settings.area_registry')
            ->public();

        // Persistence
        $services->set('sulu_webspace_settings.repository', WebspaceSettingsRepository::class)
            ->args([new Reference('doctrine.orm.entity_manager')]);

        $services->alias(WebspaceSettingsRepositoryInterface::class, 'sulu_webspace_settings.repository');

        $services->set('sulu_webspace_settings.loader', SettingsLoader::class)
            ->args([
                new Reference('sulu_webspace_settings.repository'),
                new Reference('sulu_content.content_aggregator'),
            ]);

        // Write path.
        // SuluReferenceBundle is not treated as optional: sulu/sulu itself does not work without
        // it - SuluMediaBundle has a hard reference to sulu_reference.reference_list_view_builder_factory
        // - so a branch for its absence would guard a state a Sulu application cannot be in.
        $services->set('sulu_webspace_settings.reference_updater', SettingsReferenceUpdater::class)
            ->args([
                new Reference('sulu_reference.reference_repository'),
                new Reference('sulu_content.content_view_resolver'),
            ]);

        $services->set('sulu_webspace_settings.manager', SettingsManager::class)
            ->args([
                new Reference('sulu_webspace_settings.area_registry'),
                new Reference('sulu_webspace_settings.repository'),
                new Reference('sulu_webspace_settings.loader'),
                new Reference('sulu_webspace_settings.area_localization'),
                new Reference('sulu_content.content_persister'),
                new Reference('sulu_activity.domain_event_collector'),
                new Reference('translator'),
                new Reference('sulu_webspace_settings.reader_cache'),
                new Reference('sulu_webspace_settings.security_checker'),
                new Reference('sulu_webspace_settings.reference_updater'),
            ])
            ->public();

        $services->alias(SettingsManagerInterface::class, 'sulu_webspace_settings.manager')
            ->public();

        // Read path
        $services->set('sulu_webspace_settings.read_model_hydrator', ReadModelHydrator::class);

        // shared by every reader derived with forWebspace()/forLocale(), and emptied between
        // messenger messages and between requests in a worker runtime
        $services->set('sulu_webspace_settings.reader_cache', SettingsCache::class)
            ->tag('kernel.reset', ['method' => 'reset']);

        $services->set('sulu_webspace_settings.settings', Settings::class)
            ->args([
                new Reference('sulu_webspace_settings.area_registry'),
                new Reference('sulu_webspace_settings.loader'),
                new Reference('sulu_content.content_resolver'),
                new Reference('sulu_webspace_settings.read_model_hydrator'),
                new Reference('sulu_core.webspace.webspace_manager'),
                // always defined - sulu_content itself depends on it
                new Reference('sulu_http_cache.reference_store'),
                new Reference('sulu_webspace_settings.reader_cache'),
                // the request analyzer genuinely is not always there: it belongs to the website
                // context, and settings are read from the admin and from the console as well
                new Reference('sulu_core.webspace.request_analyzer', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public();

        $services->alias(SettingsInterface::class, 'sulu_webspace_settings.settings')
            ->public();

        $services->set('sulu_webspace_settings.twig_extension', SettingsTwigExtension::class)
            ->args([new Reference('sulu_webspace_settings.settings')])
            ->tag('twig.extension');

        // Admin
        $services->set('sulu_webspace_settings.admin', WebspaceSettingsAdmin::class)
            ->args([
                new Reference('sulu_admin.view_builder_factory'),
                new Reference('sulu_activity.activity_list_view_builder_factory'),
                new Reference('sulu_security.security_checker'),
                new Reference('sulu_webspace_settings.security_checker'),
                new Reference('sulu_core.webspace.webspace_manager'),
                new Reference('sulu_webspace_settings.area_registry'),
                new Reference('sulu_webspace_settings.area_localization'),
            ])
            ->tag('sulu.context', ['context' => 'admin'])
            ->tag('sulu.admin');

        $services->set('sulu_webspace_settings.security_checker', SettingsSecurityChecker::class)
            ->args([
                new Reference('sulu_security.security_checker'),
                new Reference('sulu_webspace_settings.area_registry'),
                AreaPermissionMode::from($config['permissions']['per_area']),
            ]);

        // must outrank sulu_admin.template_form_metadata_loader (priority 512), which would
        // otherwise answer for the whole template type before we get to look at the area
        $services->set('sulu_webspace_settings.form_metadata_loader', SettingsAreaFormMetadataLoader::class)
            ->args([
                new Reference('sulu_admin.template_form_metadata_loader'),
                new Reference('sulu_webspace_settings.area_registry'),
            ])
            ->tag('sulu_admin.form_metadata_loader', ['priority' => 1024]);

        $services->set('sulu_webspace_settings.admin_controller', WebspaceSettingsController::class)
            ->public()
            ->args([
                new Reference('sulu_webspace_settings.manager'),
                new Reference('sulu_webspace_settings.area_registry'),
                new Reference('sulu_webspace_settings.area_localization'),
                new Reference('sulu_webspace_settings.security_checker'),
                new Reference('sulu_core.webspace.webspace_manager'),
                new Reference('sulu_content.content_normalizer'),
            ])
            ->tag('sulu.context', ['context' => 'admin']);

        // References and cache invalidation
        $services->set('sulu_webspace_settings.reference_refresher', SettingsReferenceRefresher::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('sulu_webspace_settings.area_registry'),
                new Reference('sulu_webspace_settings.loader'),
                new Reference('sulu_webspace_settings.reference_updater'),
            ])
            ->tag('sulu_reference.refresher');

        $services->set('sulu_webspace_settings.prune_command', PruneSettingsCommand::class)
            ->args([
                new Reference('sulu_webspace_settings.repository'),
                new Reference('sulu_webspace_settings.area_registry'),
                new Reference('sulu_core.webspace.webspace_manager'),
            ])
            ->tag('console.command');

        // The cache manager is the one Sulu service here that really can be missing:
        // SuluHttpCacheBundle only registers it when a proxy client is configured, so a project
        // without Varnish has none and there is nothing to invalidate.
        $services->set('sulu_webspace_settings.cache_invalidation_subscriber', SettingsCacheInvalidationSubscriber::class)
            ->args([
                new Reference('sulu_http_cache.cache_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('kernel.event_subscriber');
    }

    /**
     * @internal this method is not part of the public API and should only be called by Symfony
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('sulu_admin')) {
            $builder->prependExtensionConfig('sulu_admin', [
                'templates' => [
                    WebspaceSettingsInterface::TEMPLATE_TYPE => [
                        'default_type' => null,
                        'directories' => [
                            // works out of the box, no consumer configuration. A bundle shipping
                            // its own areas prepends another entry with its own key.
                            'app' => $this->resolveTemplateDirectory($builder),
                        ],
                    ],
                ],
                'resources' => [
                    WebspaceSettingsInterface::RESOURCE_KEY => [
                        'routes' => [
                            'detail' => 'sulu_webspace_settings.get_webspace_settings',
                        ],
                    ],
                ],
            ]);
        }

        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'SuluWebspaceSettings' => [
                            'type' => 'attribute',
                            'prefix' => 'Eekes\\SuluWebspaceSettingsBundle\\Domain\\Model',
                            'dir' => $this->getPath() . '/src/Domain/Model',
                            'alias' => 'SuluWebspaceSettings',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * @internal this method is not part of the public API and should only be called by Symfony
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // the whole point of the attribute: a consumer never registers a service to add a
        // settings screen
        $container->registerAttributeForAutoconfiguration(
            AsSettingsArea::class,
            static function (ChildDefinition $definition, AsSettingsArea $attribute, \Reflector $reflector): void {
                $definition->addTag(AsSettingsArea::TAG);
            },
        );

        $container->addCompilerPass(new SettingsAreaCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -1024);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Prepending runs before the configuration is processed, so the raw config is read here the
     * way every Symfony bundle that prepends a configurable value does it.
     */
    private function resolveTemplateDirectory(ContainerBuilder $builder): string
    {
        foreach ($builder->getExtensionConfig(self::ALIAS) as $config) {
            $directory = $config['templates']['directory'] ?? null;

            if (\is_string($directory) && '' !== $directory) {
                return $directory;
            }
        }

        return self::DEFAULT_TEMPLATE_DIRECTORY;
    }
}
