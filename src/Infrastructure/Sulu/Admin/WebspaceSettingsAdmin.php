<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsInterface;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;

/**
 * Registers one tab under Webspaces holding every area, with the area select and the activity
 * trail in its own toolbar.
 *
 * Areas are not one tab each. Tabs are static PHP config, but areas are scoped per webspace and
 * there is no clean way to hide a statically registered tab based on the `webspace` route
 * attribute. The area select in the toolbar is evaluated at runtime and always shows the right
 * set, and keeping the area in the URL means deep links and the browser back button keep working.
 *
 * @final
 */
class WebspaceSettingsAdmin extends Admin
{
    public const FORM_VIEW = 'sulu_webspace_settings.form';

    public const FORM_VIEW_TYPE = 'sulu_webspace_settings.settings_form';

    public const CONFIG_KEY = 'sulu_webspace_settings';

    public const TAB_ORDER = 3584;

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly ActivityViewBuilderFactoryInterface $activityViewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly SettingsSecurityChecker $settingsSecurityChecker,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly SettingsAreaLocalization $localization,
    ) {
    }

    /**
     * The umbrella context. It gates the REST endpoints and is therefore always required.
     */
    public static function getSecurityContext(string $webspaceKey): string
    {
        return \sprintf('%s%s.settings', PageAdmin::SECURITY_CONTEXT_PREFIX, $webspaceKey);
    }

    public static function getAreaSecurityContext(string $webspaceKey, string $areaKey): string
    {
        return \sprintf('%s%s.settings_%s', PageAdmin::SECURITY_CONTEXT_PREFIX, $webspaceKey, $areaKey);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $defaultAreaKey = $this->areaRegistry->getDefaultAreaKey();

        if (null === $defaultAreaKey || !$this->hasSomeSettingsPermission()) {
            return;
        }

        $formViewBuilder = $this->viewBuilderFactory
            ->createFormViewBuilder(self::FORM_VIEW, '/settings/:area')
            ->setResourceKey(WebspaceSettingsInterface::RESOURCE_KEY)
            ->setFormKey(WebspaceSettingsInterface::TEMPLATE_TYPE)
            ->setTabTitle('sulu_webspace_settings.settings')
            ->setTabOrder(self::TAB_ORDER)
            // only the save button: the area select and the activity link live in our own
            // toolbar inside the view, below the webspace tabs, where an editor looks for them.
            // The locale select is not configured here either - setLocales() on the view is
            // static config and cannot appear only for translatable areas
            ->addToolbarActions([new ToolbarAction('sulu_admin.save')])
            ->addRouterAttributesToFormRequest(['webspace', 'area'])
            ->addRouterAttributesToFormMetadata(['webspace', 'area']);

        $viewCollection->add(
            $formViewBuilder
                // the Form view of sulu-admin-bundle insists on a parent ResourceTabs view for
                // its resource store; ours builds the store from webspace + area instead
                ->setType(self::FORM_VIEW_TYPE)
                ->setAttributeDefault('area', $defaultAreaKey)
                ->setParent(PageAdmin::WEBSPACE_TABS_VIEW)
                ->addRerenderAttribute('webspace')
                ->addRerenderAttribute('area'),
        );
    }

    /**
     * @return array<string, array<string, array<string, array<int, string>>>>
     */
    public function getSecurityContexts()
    {
        $contexts = [];

        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $webspaceKey = $webspace->getKey();
            $contexts[self::getSecurityContext($webspaceKey)] = self::getSecurityContextPermissions();

            foreach ($this->getAreaSecurityContextKeys($webspaceKey) as $securityContext) {
                $contexts[$securityContext] = self::getSecurityContextPermissions();
            }
        }

        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                PageAdmin::SECURITY_CONTEXT_GROUP => $contexts,
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<string, array<int, string>>>>
     */
    public function getSecurityContextsWithPlaceholder()
    {
        $contexts = [
            self::getSecurityContext('#webspace#') => self::getSecurityContextPermissions(),
        ];

        foreach ($this->getAreaSecurityContextKeys('#webspace#') as $securityContext) {
            $contexts[$securityContext] = self::getSecurityContextPermissions();
        }

        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                PageAdmin::SECURITY_CONTEXT_GROUP => $contexts,
            ],
        ];
    }

    public function getConfigKey(): string
    {
        return self::CONFIG_KEY;
    }

    /**
     * @return array{activity: bool, areas: list<array{key: string, title: string, group: string, icon: string, webspaces: string[], permittedWebspaces: non-empty-list<string>, localized: bool, order: int}>}
     */
    public function getConfig(): array
    {
        return [
            // the activity trail is rendered inside the settings view, not as a view of its own,
            // so all the admin needs to know is whether the user may see it at all
            'activity' => $this->activityViewBuilderFactory->hasActivityListPermission(),
            'areas' => \array_values(\array_filter(\array_map(
                function (SettingsArea $area): ?array {
                    // what the area select actually filters on: an area the user may not open
                    // must not be offered, and the permission is per webspace and per area
                    $permittedWebspaces = $this->getPermittedWebspaceKeys($area);

                    if ([] === $permittedWebspaces) {
                        // an area the user cannot open anywhere is left out entirely - the
                        // admin config is public to every logged-in user, and its key and title
                        // are not theirs to read
                        return null;
                    }

                    return [
                        'key' => $area->key,
                        'title' => $area->title,
                        // one dropdown per unique group, labelled and iconed from here
                        'group' => $area->group,
                        'icon' => $area->icon,
                        'webspaces' => $area->webspaces,
                        'permittedWebspaces' => $permittedWebspaces,
                        // derived from the template: at least one field still multilingual
                        'localized' => $this->localization->isLocalized($area),
                        'order' => $area->order,
                    ];
                },
                $this->areaRegistry->getAreas(),
            ))),
        ];
    }

    /**
     * The webspaces the current user may view this area in.
     *
     * @return list<string>
     */
    private function getPermittedWebspaceKeys(SettingsArea $area): array
    {
        $webspaceKeys = [];

        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            $webspaceKey = $webspace->getKey();

            if (!$area->isAvailableForWebspace($webspaceKey)) {
                continue;
            }

            if (!$this->settingsSecurityChecker->hasPermission($webspaceKey, $area->key, PermissionTypes::VIEW)) {
                continue;
            }

            $webspaceKeys[] = $webspaceKey;
        }

        return $webspaceKeys;
    }

    /**
     * Each area gets a context of its own on top of the umbrella one, which is the only way to let
     * a client edit "Social" but not "Integrations". Whether that happens is the security
     * checker's decision, so the contexts offered here and the contexts checked on a request
     * cannot drift apart.
     *
     * @return list<string>
     */
    private function getAreaSecurityContextKeys(string $webspaceKey): array
    {
        if (!$this->settingsSecurityChecker->usesAreaSecurityContexts()) {
            return [];
        }

        return \array_values(\array_map(
            static fn (SettingsArea $area) => self::getAreaSecurityContext($webspaceKey, $area->key),
            $this->areaRegistry->getAreas(),
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function getSecurityContextPermissions(): array
    {
        return [
            PermissionTypes::VIEW,
            PermissionTypes::EDIT,
        ];
    }

    private function hasSomeSettingsPermission(): bool
    {
        foreach ($this->webspaceManager->getWebspaceCollection()->getWebspaces() as $webspace) {
            if ($this->securityChecker->hasPermission(
                self::getSecurityContext($webspace->getKey()),
                PermissionTypes::VIEW,
            )) {
                return true;
            }
        }

        return false;
    }
}
