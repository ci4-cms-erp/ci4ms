<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Libraries\Notifier;

/**
 * Realtime topic-authorization tests — the SSE stream-side IDOR gate.
 *
 * topicsFor() is the single mirror of applyRelevance(): the exact set of Redis
 * signal channels the SSE stream() endpoint is allowed to poll for a user.
 * These tests pin that a user only ever receives 'broadcast', their own
 * 'user/{id}' and the 'group/{name}' of groups they actually belong to — never
 * another user's channel and never a non-member group's channel. Both the bump
 * side (RealtimeChannel) and the poll side (topicsFor via stream()) derive names
 * from the same Notifier::topicFor, so this suite also proves that single-source
 * consistency.
 *
 * Strictly NON-DESTRUCTIVE: it creates one throwaway user and one throwaway
 * group membership, each tagged with a per-run suffix, and tearDown removes
 * exactly those rows by user id. groupsFor reads auth_groups_users through
 * CommonModel's hardcoded 'default' connection, so the fixtures are seeded and
 * cleaned on that same live connection. No pre-existing row is ever touched.
 *
 * @internal
 */
final class RealtimeTopicAuthTest extends CIUnitTestCase
{
    /** A user id that is never the test user (foreign 'user' target). */
    private const FOREIGN_USER = '987654';

    private BaseConnection $connection;

    private Notifier $notifier;

    /** Throwaway user id created for this run (satisfies the group FK). */
    private int $userId = 0;

    /** The one group the throwaway user is a member of. */
    private string $group = '';

    /** A group the throwaway user is never a member of. */
    private string $foreignGroup = '';

    /**
     * Creates an isolated user with a single group membership on the live DB.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Canary: never allowed to run against anything but the throwaway
        // test schema. Matches tests/Modules/Users/PermgroupPrivilegeEscalationTest.php
        // :76-80 and tests/Modules/Methods/ModuleInstallerNamespaceTest.php:84-88.
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        // Notifier -> CommonModel hardcodes the 'default' group, so the
        // membership lookup runs against the live MariaDB even under testing.
        // Seed on that exact connection, never the empty in-memory SQLite.
        $this->connection = db_connect('default');

        $suffix             = bin2hex(random_bytes(6));
        $this->group        = 'phpunit_grp_' . $suffix;
        $this->foreignGroup = 'phpunit_notmygroup_' . $suffix;

        $this->connection->table('users')->insert([
            'username'   => 'phpunit_rt_' . $suffix,
            'active'     => 1,
            'firstname'  => 'PHPUnit',
            'surname'    => 'Realtime',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->userId = (int) $this->connection->insertID();

        $this->connection->table('auth_groups_users')->insert([
            'user_id'    => $this->userId,
            'group'      => $this->group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notifier = new Notifier();
    }

    /**
     * Removes only this run's group membership and user; nothing else is touched.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            $this->connection->table('auth_groups_users')->where('user_id', $this->userId)->delete();
            $this->connection->table('users')->where('id', $this->userId)->delete();
        }

        parent::tearDown();
    }

    /**
     * AC-2: topicsFor(A) carries A's own topic but never another user's topic.
     *
     * @return void
     */
    public function testTopicsForExcludesForeignUserTopic(): void
    {
        $topics = $this->notifier->topicsFor($this->userId);

        $this->assertContains('user/' . $this->userId, $topics, 'a user is authorized for their own user topic');
        $this->assertNotContains('user/' . self::FOREIGN_USER, $topics, "another user's topic must never be authorized (IDOR gate)");
    }

    /**
     * AC-3: topicsFor(A) carries A's group topic but never a non-member group's.
     *
     * @return void
     */
    public function testTopicsForExcludesNonMemberGroupTopic(): void
    {
        $topics = $this->notifier->topicsFor($this->userId);

        $this->assertContains('group/' . $this->group, $topics, 'a member is authorized for their group topic');
        $this->assertNotContains('group/' . $this->foreignGroup, $topics, 'a non-member group topic must never be authorized');
    }

    /**
     * AC-4: every authorized user always carries the broadcast topic.
     *
     * @return void
     */
    public function testTopicsForAlwaysIncludesBroadcast(): void
    {
        $topics = $this->notifier->topicsFor($this->userId);

        $this->assertContains('broadcast', $topics, 'broadcast is authorized for everyone');
    }

    /**
     * AC-5: topicFor derives canonical names and is the single source both sides use.
     *
     * The static derivation is asserted directly, then the live topicsFor() list
     * is shown to equal a list rebuilt purely from Notifier::topicFor — proving
     * poll-side authorization and bump-side naming share one function.
     *
     * @return void
     */
    public function testTopicForIsTheSingleCanonicalSource(): void
    {
        $this->assertSame('user/5', Notifier::topicFor('user', '5'));
        $this->assertSame('group/editors', Notifier::topicFor('group', 'editors'));
        $this->assertSame('broadcast', Notifier::topicFor('broadcast', null));
        $this->assertSame('broadcast', Notifier::topicFor('anything-else', 'x'), 'unknown target types collapse to broadcast');

        $expected = [
            Notifier::topicFor('broadcast', null),
            Notifier::topicFor('user', (string) $this->userId),
            Notifier::topicFor('group', $this->group),
        ];

        $this->assertSame($expected, $this->notifier->topicsFor($this->userId), 'topicsFor is built entirely from topicFor');
    }
}
