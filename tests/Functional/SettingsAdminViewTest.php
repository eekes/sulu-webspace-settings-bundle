<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\WebspaceSettingsAdmin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers what the admin JavaScript is handed: one settings view under the Webspaces tab, the
 * area list its toolbar select is built from, and whether the activity trail may be shown.
 */
class SettingsAdminViewTest extends WebspaceSettingsTestCase
{
    public function testRegistersOneSettingsViewUnderTheWebspacesTab(): void
    {
        $view = $this->findView(WebspaceSettingsAdmin::FORM_VIEW);

        $this->assertNotNull($view);
        $this->assertSame('sulu_page.webspaces', $view['parent']);
        $this->assertSame('/webspaces/:webspace/settings/:area', $view['path']);
        $this->assertSame(
            WebspaceSettingsAdmin::FORM_VIEW_TYPE,
            $view['type'],
            'The Form view needs a parent ResourceTabs view, so ours wraps it',
        );
        $this->assertSame('webspace_settings', $view['options']['resourceKey']);
        $this->assertSame('webspace_settings', $view['options']['formKey']);
        $this->assertSame('social', $view['attributeDefaults']['area'], 'The first area opens by default');
        $this->assertContains('webspace', $view['rerenderAttributes']);
        $this->assertContains('area', $view['rerenderAttributes']);
    }

    public function testPassesTheRouterAttributesTheMetadataProviderNeeds(): void
    {
        $view = $this->findView(WebspaceSettingsAdmin::FORM_VIEW);

        $this->assertNotNull($view);
        $this->assertSame(['webspace', 'area'], $view['options']['routerAttributesToFormMetadata']);
        $this->assertSame(['webspace', 'area'], $view['options']['routerAttributesToFormRequest']);
    }

    /**
     * The area select and the activity link are rendered by our own view, in a Toolbar below the
     * webspace tabs. Sulu's global toolbar keeps only what belongs to it.
     */
    public function testLeavesOnlyTheSaveButtonToTheGlobalToolbar(): void
    {
        $view = $this->findView(WebspaceSettingsAdmin::FORM_VIEW);

        $this->assertNotNull($view);
        $this->assertSame(
            ['sulu_admin.save'],
            \array_column($view['options']['toolbarActions'], 'type'),
        );
    }

    public function testExposesTheAreasToTheAdminJavaScript(): void
    {
        $config = $this->fetchAdminConfig();

        $this->assertArrayHasKey(WebspaceSettingsAdmin::CONFIG_KEY, $config);

        $areas = $config[WebspaceSettingsAdmin::CONFIG_KEY]['areas'];

        $this->assertSame(['social', 'contact', 'shop'], \array_column($areas, 'key'));
        $this->assertSame(
            ['app.settings.group.marketing', 'sulu_webspace_settings.settings', 'app.settings.group.shop'],
            \array_column($areas, 'group'),
            'One dropdown per unique group; an area without one falls back to "Settings"',
        );
        $this->assertSame(['su-share', 'su-cog', 'su-tag'], \array_column($areas, 'icon'));
        $this->assertSame(['*'], $areas[0]['webspaces']);
        $this->assertSame(['shop'], $areas[2]['webspaces']);
        $this->assertFalse(
            $areas[0]['localized'],
            'Every field of "social" is multilingual="false", so the area shows no locale select',
        );
        $this->assertTrue($areas[1]['localized'], '"contact" still has translated fields');

        $this->assertSame(
            ['shop', 'website'],
            $areas[0]['permittedWebspaces'],
            'The toolbar select filters on this: available for the webspace and allowed for the user',
        );
        $this->assertSame(['shop'], $areas[2]['permittedWebspaces']);

        $this->assertTrue(
            $config[WebspaceSettingsAdmin::CONFIG_KEY]['activity'],
            'The activity trail is rendered inside the settings view, so the admin only needs the flag',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findView(string $name): ?array
    {
        $config = $this->fetchAdminConfig();

        foreach ($config['sulu_admin']['routes'] as $view) {
            if ($name === $view['name']) {
                return $view;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAdminConfig(): array
    {
        $this->client->jsonRequest('GET', '/admin/config');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());

        /** @var array<string, mixed> $config */
        $config = \json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $config;
    }
}
