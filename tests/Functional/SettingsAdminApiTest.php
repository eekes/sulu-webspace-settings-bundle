<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

class SettingsAdminApiTest extends WebspaceSettingsTestCase
{
    public function testGetReturnsAnEmptyFormPayloadInsteadOfANotFound(): void
    {
        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $payload = $this->payload();

        $this->assertSame('website', $payload['id']);
        $this->assertSame('social', $payload['area']);
        $this->assertSame('social', $payload['template']);
    }

    public function testPutStoresTheValuesAndGetReadsThemBack(): void
    {
        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=social&locale=en', [
            'facebook_url' => 'https://facebook.com/sulu',
            'links' => [['type' => 'link', 'label' => 'Docs', 'url' => 'https://docs.sulu.io']],
        ]);

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSame('https://facebook.com/sulu', $this->payload()['facebook_url']);

        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $payload = $this->payload();

        $this->assertSame('https://facebook.com/sulu', $payload['facebook_url']);
        $this->assertSame('Docs', $payload['links'][0]['label']);
    }

    public function testFallsBackToTheDefaultAreaWithoutAnAreaParameter(): void
    {
        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?locale=en');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSame('social', $this->payload()['area']);
    }

    public function testRejectsAnAreaThatIsNotDeclaredForTheWebspace(): void
    {
        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?area=shop&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    public function testRejectsWritingAnAreaThatIsNotDeclaredForTheWebspace(): void
    {
        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=shop&locale=en', [
            'checkout_notice' => 'nope',
        ]);

        $this->assertHttpStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    public function testRejectsAnUnknownArea(): void
    {
        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?area=does-not-exist&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    public function testRejectsAnUnknownWebspace(): void
    {
        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/nope?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
    }

    public function testAllowsAnAreaInTheWebspaceItIsDeclaredFor(): void
    {
        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/shop?area=shop&locale=en', [
            'checkout_notice' => 'Free shipping',
        ]);

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertSame('Free shipping', $this->payload()['checkout_notice']);
    }

    /**
     * Any string used to become a dimension content row that nothing ever reads back.
     */
    public function testRejectsALocaleTheWebspaceDoesNotHave(): void
    {
        $this->client->jsonRequest(
            'PUT',
            '/admin/api/webspace-settings/website?area=social&locale=zz-ZZ',
            ['facebook_url' => 'https://facebook.com/sulu'],
        );

        $this->assertHttpStatusCode(Response::HTTP_BAD_REQUEST, $this->client->getResponse());

        $rows = static::getEntityManager()->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM ws_settings_dimension_contents');

        $this->assertSame(0, (int) $rows, 'A rejected locale must not leave a row behind');
    }

    public function testNamesTheWebspaceInTheErrorForAnUnknownOne(): void
    {
        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/nope?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_NOT_FOUND, $this->client->getResponse());
        $this->assertStringContainsString('There is no webspace "nope"', $this->payload()['message']);
    }

    public function testFormMetadataServesTheFieldsOfTheRequestedArea(): void
    {
        $this->client->jsonRequest('GET', '/admin/metadata/form/webspace_settings?area=social&webspace=website');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $payload = $this->payload();

        $this->assertArrayHasKey('form', $payload, 'One area resolves to a plain form, not to a template select');
        $this->assertArrayHasKey('facebook_url', $payload['form']);
        $this->assertArrayNotHasKey('phone_number', $payload['form']);
    }

    public function testFormMetadataSwitchesWithTheAreaAttribute(): void
    {
        $this->client->jsonRequest('GET', '/admin/metadata/form/webspace_settings?area=contact&webspace=website');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $payload = $this->payload();

        $this->assertArrayHasKey('phone_number', $payload['form']);
        $this->assertArrayNotHasKey('facebook_url', $payload['form']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = \json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $payload;
    }
}
