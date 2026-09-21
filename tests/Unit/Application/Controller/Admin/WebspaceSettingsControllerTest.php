<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Unit\Application\Controller\Admin;

use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsArea;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaLocalization;
use Eekes\SuluWebspaceSettingsBundle\Application\Area\SettingsAreaRegistry;
use Eekes\SuluWebspaceSettingsBundle\Application\Controller\Admin\WebspaceSettingsController;
use Eekes\SuluWebspaceSettingsBundle\Application\Manager\SettingsManagerInterface;
use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The endpoint is the only guard that always runs: the area select and the locale select in the
 * admin are conveniences, and a hand-edited URL reaches this class directly.
 */
class WebspaceSettingsControllerTest extends TestCase
{
    private SettingsManagerInterface&MockObject $settingsManager;

    protected function setUp(): void
    {
        $this->settingsManager = $this->createMock(SettingsManagerInterface::class);
    }

    public function testAnswers404ForAWebspaceThatDoesNotExist(): void
    {
        $response = $this->controller()->getAction(new Request(), 'nope');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('There is no webspace "nope"', $this->message($response));
        $this->assertStringContainsString('website, shop', $this->message($response));
    }

    public function testAnswers404ForAnAreaThatIsNotDeclaredForTheWebspace(): void
    {
        $response = $this->controller()->getAction(new Request(['area' => 'shop']), 'website');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('is not available for webspace "website"', $this->message($response));
    }

    /**
     * The first area overall is declared for "shop" only, so the fallback has to look at the
     * webspace - otherwise opening "website" without an area in the URL is a 404.
     */
    public function testFallsBackToTheFirstAreaOfThatWebspace(): void
    {
        $this->settingsManager->expects($this->once())
            ->method('load')
            ->with('website', 'social', 'en')
            ->willReturn(null);

        $response = $this->controller()->getAction(new Request(), 'website');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('social', $this->payload($response)['area']);
    }

    public function testFallsBackToTheFirstAreaOfTheOtherWebspace(): void
    {
        $this->settingsManager->expects($this->once())
            ->method('load')
            ->with('shop', 'shop', 'en')
            ->willReturn(null);

        $response = $this->controller()->getAction(new Request(), 'shop');

        $this->assertSame('shop', $this->payload($response)['area']);
    }

    /**
     * Any string in the query would otherwise become a dimension content row that nothing reads.
     */
    public function testAnswers400ForALocaleTheWebspaceDoesNotHave(): void
    {
        $this->settingsManager->expects($this->never())->method('load');

        $response = $this->controller()->getAction(new Request(['locale' => 'zz-ZZ']), 'website');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('has no locale "zz-ZZ"', $this->message($response));
        $this->assertStringContainsString('en, de', $this->message($response));
    }

    public function testRejectsAnUnsupportedLocaleOnWriteToo(): void
    {
        $this->settingsManager->expects($this->never())->method('save');

        $response = $this->controller()->putAction(new Request(['locale' => 'zz-ZZ']), 'website');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * The admin sends a JSON body. Reading it from the request bag only works because Sulu
     * happens to enable the FOSRestBundle body listener, which a project may switch off.
     */
    public function testReadsTheWrittenDataFromTheJsonBody(): void
    {
        $this->settingsManager->expects($this->once())
            ->method('save')
            ->with('website', 'social', 'en', ['facebook_url' => 'https://facebook.com/sulu'])
            ->willThrowException(new \RuntimeException('reached'));

        $request = new Request(
            query: ['area' => 'social'],
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) \json_encode(['facebook_url' => 'https://facebook.com/sulu']),
        );

        $this->expectExceptionMessage('reached');

        $this->controller()->putAction($request, 'website');
    }

    private function controller(): WebspaceSettingsController
    {
        $registry = new SettingsAreaRegistry([
            // declared for "shop" only, and first overall
            'shop' => (new SettingsArea('shop', 'app.settings.shop', 'shop', ['shop'], 5, null))->toArray(),
            'social' => (new SettingsArea('social', 'app.settings.social', 'social', ['*'], 10, null))->toArray(),
        ]);

        $securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('en');

        return new WebspaceSettingsController(
            $this->settingsManager,
            $registry,
            new SettingsAreaLocalization($this->createMock(FormMetadataLoaderInterface::class), $translator),
            new SettingsSecurityChecker($securityChecker, $registry),
            $this->webspaceManager(),
            $this->createMock(ContentNormalizerInterface::class),
        );
    }

    private function webspaceManager(): WebspaceManagerInterface
    {
        $webspaces = [];

        foreach (['website', 'shop'] as $key) {
            $webspace = new Webspace();
            $webspace->setKey($key);
            $webspace->setName($key);
            $webspace->addLocalization(new Localization('en'));
            $webspace->addLocalization(new Localization('de'));

            $webspaces[$key] = $webspace;
        }

        $collection = new WebspaceCollection($webspaces);

        $manager = $this->createMock(WebspaceManagerInterface::class);
        $manager->method('findWebspaceByKey')->willReturnCallback(
            static fn (?string $key) => $webspaces[$key] ?? null,
        );
        $manager->method('getWebspaceCollection')->willReturn($collection);

        return $manager;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function message(Response $response): string
    {
        return (string) $this->payload($response)['message'];
    }
}
