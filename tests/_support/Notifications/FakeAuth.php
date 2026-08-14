<?php

namespace Tests\Support\Notifications;

use CodeIgniter\Shield\Auth;

/**
 * Shield Auth double reporting a fixed session owner.
 *
 * PreferenceController derives the row owner from `auth()->id()` and from nowhere
 * else; the IDOR test therefore has to control that single value while leaving the
 * request free to carry a hostile `user_id`. Injected as the `auth` service, which
 * is what the `auth()` helper resolves.
 *
 * The parent constructor is deliberately NOT called: it requires the Shield
 * AuthConfig and would pull in the authenticator/user provider stack, none of which
 * is reachable once id() is overridden.
 */
final class FakeAuth extends Auth
{
    /**
     * @param int $userId The id every id() call reports.
     */
    public function __construct(private int $userId)
    {
    }

    /**
     * Reports the test-supplied session owner id.
     *
     * @return int The id handed to the constructor.
     */
    public function id()
    {
        return $this->userId;
    }
}
