<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Application\Controller\Admin;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistryInterface;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsManagerInterface;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotAvailableException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\SettingsAreaNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\UnsupportedLocaleException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Exception\WebspaceNotFoundException;
use Eekes\SuluWebspaceSettingsBundle\Domain\Model\WebspaceSettingsDimensionContentInterface;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin API of the settings form.
 *
 * The resource id is the webspace key; the area travels as a query parameter, so the activity
 * trail and this endpoint agree on what identifies a record.
 *
 * A record that has never been saved is not an error: the GET returns an empty form payload
 * rather than a 404, otherwise a fresh webspace shows an error instead of an empty form.
 *
 * @internal this class should not be instantiated by a project; use a request or response
 *           listener to extend the endpoint's behaviour
 */
final class WebspaceSettingsController
{
    public function __construct(
        private readonly SettingsManagerInterface $settingsManager,
        private readonly SettingsAreaRegistryInterface $areaRegistry,
        private readonly SettingsAreaLocalization $localization,
        private readonly SettingsSecurityChecker $securityChecker,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ContentNormalizerInterface $contentNormalizer,
    ) {
    }

    /**
     * @param string $id the webspace key - the REST id of a settings record is its webspace
     */
    public function getAction(Request $request, string $id): Response
    {
        try {
            $webspace = $this->resolveWebspace($id);
        } catch (WebspaceNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }

        // before the area is resolved: a "no such area" message names every declared area, and the
        // area switcher already hides the ones this user may not open
        $this->securityChecker->checkWebspacePermission($webspace->getKey(), PermissionTypes::VIEW);

        try {
            $area = $this->resolveArea($request, $webspace);
            $locale = $this->resolveLocale($request, $webspace);
        } catch (SettingsAreaNotFoundException|SettingsAreaNotAvailableException $exception) {
            return $this->notFound($exception->getMessage());
        } catch (UnsupportedLocaleException $exception) {
            return $this->badRequest($exception->getMessage());
        }

        $this->securityChecker->checkPermission($webspace->getKey(), $area->key, PermissionTypes::VIEW);

        $dimensionContent = $this->settingsManager->load($webspace->getKey(), $area->key, $locale);

        if (null === $dimensionContent) {
            return new JsonResponse($this->emptyPayload($webspace->getKey(), $area, $locale));
        }

        return new JsonResponse($this->normalize($webspace->getKey(), $area, $dimensionContent));
    }

    /**
     * @param string $id the webspace key - the REST id of a settings record is its webspace
     */
    public function putAction(Request $request, string $id): Response
    {
        try {
            $webspace = $this->resolveWebspace($id);
        } catch (WebspaceNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }

        // before the area is resolved: a "no such area" message names every declared area, and the
        // area switcher already hides the ones this user may not open
        $this->securityChecker->checkWebspacePermission($webspace->getKey(), PermissionTypes::EDIT);

        try {
            $area = $this->resolveArea($request, $webspace);
            $locale = $this->resolveLocale($request, $webspace);
        } catch (SettingsAreaNotFoundException|SettingsAreaNotAvailableException $exception) {
            return $this->notFound($exception->getMessage());
        } catch (UnsupportedLocaleException $exception) {
            return $this->badRequest($exception->getMessage());
        }

        $this->securityChecker->checkPermission($webspace->getKey(), $area->key, PermissionTypes::EDIT);

        // getPayload() rather than the request bag: the admin sends JSON, and the bag only carries
        // it because Sulu happens to enable the FOSRestBundle body listener
        /** @var array<string, mixed> $data */
        $data = $request->getPayload()->all();

        $dimensionContent = $this->settingsManager->save($webspace->getKey(), $area->key, $locale, $data);

        return new JsonResponse($this->normalize($webspace->getKey(), $area, $dimensionContent));
    }

    /**
     * @throws WebspaceNotFoundException
     */
    private function resolveWebspace(string $webspaceKey): Webspace
    {
        $webspace = $this->webspaceManager->findWebspaceByKey($webspaceKey);

        if (null === $webspace) {
            throw new WebspaceNotFoundException($webspaceKey, \array_keys(
                $this->webspaceManager->getWebspaceCollection()->getWebspaces(),
            ));
        }

        return $webspace;
    }

    /**
     * @throws SettingsAreaNotFoundException
     * @throws SettingsAreaNotAvailableException
     */
    private function resolveArea(Request $request, Webspace $webspace): SettingsArea
    {
        $areaKey = $request->query->getString('area')
            ?: $this->areaRegistry->getDefaultAreaKeyForWebspace($webspace->getKey()) ?? '';

        // enforced server side, the area select in the admin is not the only guard
        return $this->areaRegistry->getAreaForWebspace($areaKey, $webspace->getKey());
    }

    /**
     * Any string in the query would otherwise become a dimension content row that nothing ever
     * reads back.
     *
     * @throws UnsupportedLocaleException
     */
    private function resolveLocale(Request $request, Webspace $webspace): string
    {
        $locale = $request->query->getString('locale') ?: $request->getLocale();

        $available = \array_map(
            static fn ($localization) => $localization->getLocale(),
            $webspace->getAllLocalizations(),
        );

        if (!\in_array($locale, $available, true)) {
            throw new UnsupportedLocaleException($locale, $webspace->getKey(), $available);
        }

        return $locale;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(
        string $webspaceKey,
        SettingsArea $area,
        WebspaceSettingsDimensionContentInterface $dimensionContent,
    ): array {
        return \array_merge(
            $this->contentNormalizer->normalize($dimensionContent),
            [
                'id' => $webspaceKey,
                'webspace' => $webspaceKey,
                'area' => $area->key,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $webspaceKey, SettingsArea $area, string $locale): array
    {
        return [
            'id' => $webspaceKey,
            'webspace' => $webspaceKey,
            'area' => $area->key,
            'template' => $area->template,
            'locale' => $this->localization->isLocalized($area) ? $locale : null,
        ];
    }

    private function notFound(string $message): JsonResponse
    {
        return new JsonResponse(['code' => 0, 'message' => $message], Response::HTTP_NOT_FOUND);
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['code' => 0, 'message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
