<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Auth\Filters\Ci4MsAuthFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use stdClass;

/**
 * Regression suite for GHSA-5j8q (typeOfPermissions read-side type contract).
 *
 * `Ci4MsAuthFilter::neededAction()` (`modules/Auth/Filters/Ci4MsAuthFilter.php:69-97`)
 * decodes `auth_permissions_pages.typeOfPermissions` and reads it with
 * `array_key_exists()`. `json_decode(...) ?? []` only substitutes the `[]`
 * fallback when the decoded value is exactly `null` -- it does NOT catch a
 * value that decodes to a *valid* JSON scalar (`5`, `true`), because neither
 * is `null`. Before the `is_array($pagePermissions)` guard was added right
 * after that line, a corrupt `auth_permissions_pages` row whose
 * `typeOfPermissions` column held `'5'` or `'true'` made `array_key_exists()`
 * throw a `TypeError` for every single request routed through that row.
 *
 * This suite does NOT test or touch the separate, deliberately-unfixed
 * fail-open behavior: when `typeOfPermissions` cannot be trusted (malformed
 * JSON, or a scalar caught by the guard), `$neededAction` stays at its
 * `'read'` default -- see the guard's own comment. That is a known, distinct,
 * user-owned decision (see context.md, "Madde A"), out of scope here.
 *
 * `neededAction()` is `protected` and takes a plain object with a
 * `typeOfPermissions` property -- no DB/HTTP is needed to exercise it, so
 * this suite uses `CIUnitTestCase` directly (no `DatabaseTestTrait`) and
 * calls the method via `ReflectionMethod`.
 *
 * @internal
 */
final class Ci4MsAuthFilterNeededActionGuardTest extends CIUnitTestCase
{
    /**
     * Malformed JSON was already handled by the pre-existing `?? []` --
     * `json_decode('{oops', ...)` returns `null` on a parse failure, which
     * `??` does substitute. This is a sanity/regression check that the new
     * guard did not disturb that pre-existing path: no TypeError, and the
     * fail-open default ('read') is unchanged.
     */
    public function testNeededActionDoesNotThrowOnMalformedJson(): void
    {
        $page = $this->pageWith('{oops');

        $result = $this->invokeNeededAction($page);

        $this->assertSame('read', $result);
    }

    /**
     * `json_decode('5', true)` returns the int `5`, not `null` -- `?? []`
     * does not fire. Before the guard, `array_key_exists('read_r', 5)`
     * raised a TypeError. Same for `json_decode('true', true)` returning
     * bool `true`. The mutation control experiment in this task's evidence
     * log removes the guard and re-runs exactly this test to show a real
     * TypeError here.
     *
     * @param string $jsonScalar A JSON document that decodes to a scalar.
     */
    #[DataProvider('validJsonScalars')]
    public function testNeededActionDoesNotThrowOnValidJsonScalar(string $jsonScalar): void
    {
        $page = $this->pageWith($jsonScalar);

        $result = $this->invokeNeededAction($page);

        $this->assertSame('read', $result);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validJsonScalars(): array
    {
        return [
            'numeric JSON scalar' => ['5'],
            'boolean JSON scalar' => ['true'],
        ];
    }

    /**
     * `json_decode('null', true)` returns PHP `null`, which `?? []` already
     * substituted for `[]` before the guard existed. Sanity check that the
     * guard does not change this pre-existing, already-safe path.
     */
    public function testNeededActionDoesNotThrowOnJsonNullLiteral(): void
    {
        $page = $this->pageWith('null');

        $result = $this->invokeNeededAction($page);

        $this->assertSame('read', $result);
    }

    /**
     * Ordinary, well-formed typeOfPermissions JSON must still resolve to
     * the correct action -- the guard must not disturb the normal flow.
     */
    public function testNeededActionResolvesDeleteFromOrdinaryValidJson(): void
    {
        $page = $this->pageWith((string) json_encode(['delete_r' => true], JSON_UNESCAPED_UNICODE));

        $result = $this->invokeNeededAction($page);

        $this->assertSame('delete', $result);
    }

    private function pageWith(string $typeOfPermissions): stdClass
    {
        $page                     = new stdClass();
        $page->typeOfPermissions = $typeOfPermissions;

        return $page;
    }

    private function invokeNeededAction(object $page): string
    {
        $method = new ReflectionMethod(Ci4MsAuthFilter::class, 'neededAction');
        $method->setAccessible(true);

        return $method->invoke(new Ci4MsAuthFilter(), $page);
    }
}
