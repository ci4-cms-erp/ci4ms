<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Filters\Filters;
use CodeIgniter\Shield\Filters\SessionAuth;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Auth\Filters\Ci4MsAuthFilter;
use Modules\Notifications\Config\NotificationsConfig;

/**
 * Where the composer's authorization actually lives: the routes, not the controller.
 *
 * ComposerController contains no permission check of its own, and that is by design —
 * this project puts backend authorization in one place, the `backendGuard` filter, and
 * derives the permission string from the route NAME plus its `role` flag. The cost of
 * that design is that a new endpoint is protected only if it is registered correctly:
 * a missing `role`, a name that does not match the permission record, or a path outside
 * the guard's pattern list all produce a controller that is reachable by anyone with a
 * backend session. None of those mistakes is visible from the controller file.
 *
 * So the registration itself is asserted here, through the real Filters pipeline rather
 * than by reading the config array: each composer URI is run through
 * {@see Filters::initialize()} exactly as an incoming request would be, and the filters
 * it collects are the assertion. Anonymous access is fail-closed because the chain
 * begins with Shield's session check, which is asserted as part of the alias.
 *
 * The counterpart — what the endpoints do once a request is let through — is covered by
 * {@see ComposerControllerTest}.
 *
 * @internal
 */
final class ComposerRouteGuardTest extends CIUnitTestCase
{
    /**
     * Every composer endpoint as [uri, verb, route name, permission role].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const ENDPOINTS = [
        ['backend/notifications/compose', 'GET', 'notifCompose', 'read'],
        ['backend/notifications/compose/users', 'GET', 'notifComposeUsers', 'read'],
        ['backend/notifications/compose/preview', 'POST', 'notifComposePreview', 'create'],
        ['backend/notifications/compose', 'POST', 'notifComposeSend', 'create'],
    ];

    /** The filter alias that carries the whole backend authorization chain. */
    private const GUARD = 'backendGuard';

    /**
     * Discovers the module route files the way a real request would.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        service('routes')->loadRoutes();
    }

    /**
     * The before-filter aliases a request to the given URI would collect.
     *
     * @param string $uri Path relative to baseURL, lowercase and without a leading slash.
     *
     * @return list<string> Filter aliases in execution order.
     */
    private function beforeFiltersFor(string $uri): array
    {
        $filters = new Filters(config('Filters'), service('request'), service('response'));

        return $filters->initialize($uri)->getFilters()['before'];
    }

    /**
     * Every composer endpoint is registered with the role its permission is derived from.
     *
     * @return void
     */
    public function testEveryComposeEndpointIsRegisteredWithItsPermissionRole(): void
    {
        $routes = service('routes');

        foreach (self::ENDPOINTS as [$uri, $verb, $name, $role]) {
            $options = $routes->getRoutesOptions($uri, $verb);

            $this->assertSame($name, $options['as'] ?? null, "{$verb} {$uri} must keep its route name: the permission record is derived from it");
            $this->assertSame($role, $options['role'] ?? null, "{$verb} {$uri} must declare its role, or the permission filter has nothing to check");
        }
    }

    /**
     * Everything that answers a question about the audience requires `create`.
     *
     * Registering send() as `read` would let every account that may open the screen
     * publish to the whole site, which is the privilege split this feature rests on.
     * preview() sits on the same side of that split even though it writes nothing: the
     * recipient count it returns is a membership and existence oracle — adding one id to
     * a `groups[]=superadmin` selection and watching the number move reveals whether that
     * account is a superadmin, and whether it exists at all. `read` is handed to every
     * backend user for the notification bell, so an oracle behind it is an oracle behind
     * nothing. Only the form itself, which discloses no account data, stays a read.
     *
     * @return void
     */
    public function testEveryEndpointThatDisclosesTheAudienceRequiresCreate(): void
    {
        $routes = service('routes');

        $this->assertSame('create', $routes->getRoutesOptions('backend/notifications/compose', 'POST')['role'] ?? null);
        $this->assertSame('create', $routes->getRoutesOptions('backend/notifications/compose/preview', 'POST')['role'] ?? null);
        $this->assertSame('read', $routes->getRoutesOptions('backend/notifications/compose', 'GET')['role'] ?? null);
    }

    /**
     * Every composer endpoint sits behind the backend guard.
     *
     * @return void
     */
    public function testEveryComposeEndpointSitsBehindTheBackendGuard(): void
    {
        foreach (self::ENDPOINTS as [$uri]) {
            $this->assertContains(self::GUARD, $this->beforeFiltersFor($uri), "{$uri} is reachable without the backend guard");
        }
    }

    /**
     * The guard chain starts with the session check, so an anonymous request never arrives.
     *
     * @return void
     */
    public function testTheGuardChainAuthenticatesBeforeItAuthorizes(): void
    {
        /** @var \Config\Filters $config */
        $config = config('Filters');
        $chain  = $config->aliases[self::GUARD];

        $this->assertContains(SessionAuth::class, $chain, 'no session, no request: this is what makes anonymous access fail closed');
        $this->assertContains(Ci4MsAuthFilter::class, $chain, 'and this is what turns the route role into a permission check');
        $this->assertSame(SessionAuth::class, $chain[0], 'authentication runs before authorization');
    }

    /**
     * The composer endpoints keep CSRF protection: the module excepts nothing.
     *
     * The two AJAX endpoints are the tempting ones to except, since a token has to be
     * carried in the body by hand. preview() is a read, but send() is a POST that
     * publishes to every account — excepting it would make that reachable by a
     * cross-site form.
     *
     * @return void
     */
    public function testEveryComposeEndpointKeepsCsrfProtection(): void
    {
        $this->assertSame([], (new NotificationsConfig())->csrfExcept, 'the module excepts no path from CSRF');

        foreach (self::ENDPOINTS as [$uri, $verb]) {
            if ($verb !== 'POST') {
                continue;
            }

            $this->assertContains('csrf', $this->beforeFiltersFor($uri), "{$uri} would accept a cross-site POST");
        }
    }

    /**
     * The named routes resolve to the paths the form and its AJAX calls post to.
     *
     * @return void
     */
    public function testTheRouteNamesResolveToTheComposerPaths(): void
    {
        $routes = service('routes');

        $this->assertSame('/backend/notifications/compose', $routes->reverseRoute('notifCompose'));
        $this->assertSame('/backend/notifications/compose/users', $routes->reverseRoute('notifComposeUsers'));
        $this->assertSame('/backend/notifications/compose/preview', $routes->reverseRoute('notifComposePreview'));
        $this->assertSame('/backend/notifications/compose', $routes->reverseRoute('notifComposeSend'));
    }
}
