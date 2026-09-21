<?php

declare(strict_types=1);

namespace Eekes\SuluWebspaceSettingsBundle\Tests\Functional;

use Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\WebspaceSettingsAdmin;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\Permission;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Bundle\SecurityBundle\Entity\UserRole;
use Sulu\Component\Security\Authorization\MaskConverterInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoints are the only real guard: the area select in the admin is built from the config,
 * and a hand-edited URL goes straight past it. {@see \Eekes\SuluWebspaceSettingsBundle\Infrastructure\Sulu\Admin\SettingsSecurityChecker}
 * is unit tested against a mocked checker, which proves which contexts it asks about but not
 * that a denied request is actually refused - so that is covered here, end to end.
 *
 * Sulu's own test harness registers a voter that grants the user named `test` everything, which
 * is why these tests authenticate as a second user instead: for anyone else that voter abstains
 * and the real permission logic decides.
 */
class SettingsPermissionTest extends WebspaceSettingsTestCase
{
    private const USERNAME = 'editor';

    public function testRefusesReadingAnAreaWithoutTheUmbrellaPermission(): void
    {
        $this->authenticateWith([]);

        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_FORBIDDEN, $this->client->getResponse());
    }

    public function testAllowsReadingAnAreaWithTheViewPermission(): void
    {
        $this->authenticateWith([
            WebspaceSettingsAdmin::getSecurityContext('website') => [PermissionTypes::VIEW],
            WebspaceSettingsAdmin::getAreaSecurityContext('website', 'social') => [PermissionTypes::VIEW],
        ]);

        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/website?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());
    }

    public function testRefusesWritingWithTheViewPermissionOnly(): void
    {
        $this->authenticateWith([
            WebspaceSettingsAdmin::getSecurityContext('website') => [PermissionTypes::VIEW],
            WebspaceSettingsAdmin::getAreaSecurityContext('website', 'social') => [PermissionTypes::VIEW],
        ]);

        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=social&locale=en', [
            'facebook_url' => 'https://facebook.com/sulu',
        ]);

        $this->assertHttpStatusCode(Response::HTTP_FORBIDDEN, $this->client->getResponse());
    }

    /**
     * The point of the per-area contexts: letting a client edit one settings screen but not the
     * one next to it. If this stops holding, the whole split is decoration.
     */
    public function testRefusesAnAreaTheUserHasNoAreaPermissionFor(): void
    {
        $this->authenticateWith([
            WebspaceSettingsAdmin::getSecurityContext('website') => [PermissionTypes::VIEW, PermissionTypes::EDIT],
            WebspaceSettingsAdmin::getAreaSecurityContext('website', 'social') => [
                PermissionTypes::VIEW,
                PermissionTypes::EDIT,
            ],
        ]);

        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=social&locale=en', [
            'facebook_url' => 'https://facebook.com/sulu',
        ]);

        $this->assertHttpStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $this->client->jsonRequest('PUT', '/admin/api/webspace-settings/website?area=contact&locale=en', [
            'phone_number' => '+32 1',
        ]);

        $this->assertHttpStatusCode(Response::HTTP_FORBIDDEN, $this->client->getResponse());
    }

    /**
     * Permissions are per webspace, so the same area in another webspace is a separate grant.
     */
    public function testRefusesTheSameAreaInAWebspaceTheUserHasNoPermissionFor(): void
    {
        $this->authenticateWith([
            WebspaceSettingsAdmin::getSecurityContext('website') => [PermissionTypes::VIEW, PermissionTypes::EDIT],
            WebspaceSettingsAdmin::getAreaSecurityContext('website', 'social') => [
                PermissionTypes::VIEW,
                PermissionTypes::EDIT,
            ],
        ]);

        $this->client->jsonRequest('GET', '/admin/api/webspace-settings/shop?area=social&locale=en');

        $this->assertHttpStatusCode(Response::HTTP_FORBIDDEN, $this->client->getResponse());
    }

    /**
     * Creates a user holding exactly the given permissions and points the client at it.
     *
     * @param array<string, list<string>> $permissions security context to the permission types granted on it
     */
    private function authenticateWith(array $permissions): void
    {
        $entityManager = static::getEntityManager();

        /** @var MaskConverterInterface $maskConverter */
        $maskConverter = static::getContainer()->get('sulu_security.mask_converter');

        $role = new Role();
        $role->setName('settings-' . \uniqid());
        $role->setSystem('Sulu');
        $entityManager->persist($role);

        foreach ($permissions as $context => $permissionTypes) {
            $permission = new Permission();
            $permission->setContext($context);
            $permission->setPermissions($maskConverter->convertPermissionsToNumber(
                \array_fill_keys($permissionTypes, true),
            ));
            $permission->setRole($role);
            $role->addPermission($permission);
            $entityManager->persist($permission);
        }

        $user = $this->createUser();

        $userRole = new UserRole();
        $userRole->setUser($user);
        $userRole->setRole($role);
        $userRole->setLocale('["en"]');
        $user->addUserRole($userRole);
        $entityManager->persist($userRole);

        $entityManager->flush();

        // a second user rather than Sulu's `test` one, whose TestVoter grants everything
        $this->client->setServerParameter('PHP_AUTH_USER', self::USERNAME);
        $this->client->setServerParameter('PHP_AUTH_PW', self::USERNAME);
    }

    private function createUser(): User
    {
        $entityManager = static::getEntityManager();

        $contact = new Contact();
        $contact->setFirstName('Ada');
        $contact->setLastName('Editor');
        $entityManager->persist($contact);

        $user = new User();
        $user->setContact($contact);
        $user->setUsername(self::USERNAME);
        $user->setEmail(self::USERNAME . '@example.localhost');
        $user->setSalt('');
        // the test harness hashes in plaintext, see its security config
        $user->setPassword(self::USERNAME);
        $user->setLocale('en');
        $entityManager->persist($user);

        return $user;
    }
}
