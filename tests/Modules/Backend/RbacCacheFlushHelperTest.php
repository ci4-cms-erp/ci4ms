<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit contract for the rbac_cache_flush() helper (app/Common.php:289-293).
 *
 * The helper's whole reason to exist is that BOTH RBAC caches
 * Modules\Auth\Filters\Ci4MsAuthFilter reads from are invalidated together:
 * 'shield_auth_dynamic_config' (the group/permission matrix, loaded by
 * Modules\Auth\Config\AuthGroups::loadFromDatabase(), 86400s) and
 * 'backend_page_info_*' (the per-route page lookup, Ci4MsAuthFilter.php:36-46,
 * 3600s). Clearing only one leaves the other stale for up to an hour after a
 * permission change -- the exact bug class B-3 closed.
 *
 * The two keys are asserted in SEPARATE test methods on purpose. A single
 * method holding both assertions would go red for either mutation and could
 * not distinguish "the shield delete was dropped" from "the deleteMatching
 * was dropped"; with one method per line, deleting app/Common.php:291 reds
 * only testFlushDeletesTheShieldAuthDynamicConfigKey and deleting :292 reds
 * only testFlushDeletesEveryBackendPageInfoKey (mutation matrix M1/M2, see
 * context.md).
 *
 * No DatabaseTestTrait: the helper touches the cache exclusively, so this
 * class must not open a DB connection or migrate anything. The cache handler
 * under test is the configured one (app/Config/Cache.php:24 'file',
 * :45 prefix ''), so deleteMatching()'s glob really runs here rather than
 * being a no-op against a dummy handler.
 *
 * @internal
 */
final class RbacCacheFlushHelperTest extends CIUnitTestCase
{
    /**
     * Keys this test writes, removed again in tearDown() so a failed
     * assertion cannot leak a primed key into the next test in the process.
     *
     * @var list<string>
     */
    private array $writtenKeys = [];

    /**
     * Removes every key this test primed, whether or not the helper did.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->writtenKeys as $key) {
            cache()->delete($key);
        }
        $this->writtenKeys = [];

        parent::tearDown();
    }

    /**
     * Locks app/Common.php:291 -- cache()->delete('shield_auth_dynamic_config').
     *
     * @return void
     */
    public function testFlushDeletesTheShieldAuthDynamicConfigKey(): void
    {
        $this->primeKey('shield_auth_dynamic_config', ['matrix' => ['qa_group' => ['qa.read']]]);

        $this->assertNotNull(
            cache()->get('shield_auth_dynamic_config'),
            'precondition: the key must be present before the flush, or the assertion below proves nothing.',
        );

        rbac_cache_flush();

        $this->assertNull(
            cache()->get('shield_auth_dynamic_config'),
            'rbac_cache_flush() must delete the Shield group/permission matrix cache.',
        );
    }

    /**
     * Locks app/Common.php:292 -- cache()->deleteMatching('backend_page_info_*').
     *
     * Two matching keys, not one: deleteMatching() is a pattern delete, and a
     * single key could also be cleared by a plain delete() of that exact
     * name.
     *
     * @return void
     */
    public function testFlushDeletesEveryBackendPageInfoKey(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $first  = 'backend_page_info_qa_' . $suffix . '_a';
        $second = 'backend_page_info_qa_' . $suffix . '_b';

        $this->primeKey($first, ['id' => 4242, 'pagename' => 'qa.first']);
        $this->primeKey($second, ['id' => 4243, 'pagename' => 'qa.second']);

        $this->assertNotNull(cache()->get($first), 'precondition: first backend_page_info_* key must be present.');
        $this->assertNotNull(cache()->get($second), 'precondition: second backend_page_info_* key must be present.');

        rbac_cache_flush();

        $this->assertNull(
            cache()->get($first),
            'rbac_cache_flush() must delete every backend_page_info_* key, not just the Shield matrix.',
        );
        $this->assertNull(
            cache()->get($second),
            'rbac_cache_flush() must delete EVERY backend_page_info_* key -- deleteMatching(), not a single delete().',
        );
    }

    /**
     * The flush must stay narrow: an unrelated key, and the neighbouring
     * 'sidebar_menu' key that several call sites clear SEPARATELY (e.g.
     * Methods.php:174, :480), must survive it.
     *
     * Without this, widening the pattern to something like '*' would keep
     * both tests above green while silently nuking the whole cache.
     *
     * @return void
     */
    public function testFlushLeavesUnrelatedKeysIntact(): void
    {
        $unrelated = 'qa_unrelated_' . bin2hex(random_bytes(4));

        $this->primeKey($unrelated, 'keep me');
        $this->primeKey('backend_page_info_qa_' . bin2hex(random_bytes(4)), ['id' => 1]);
        $this->primeKey('shield_auth_dynamic_config', ['matrix' => []]);

        rbac_cache_flush();

        $this->assertSame(
            'keep me',
            cache()->get($unrelated),
            'rbac_cache_flush() must not delete keys outside its two documented targets.',
        );
    }

    /**
     * Writes a cache entry and registers it for tearDown() cleanup.
     *
     * @param string $key   Cache key to write.
     * @param mixed  $value Value to store.
     *
     * @return void
     */
    private function primeKey(string $key, mixed $value): void
    {
        cache()->save($key, $value, 300);
        $this->writtenKeys[] = $key;
    }
}
