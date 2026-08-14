<?php

namespace Tests\Support\Notifications;

/**
 * Minimal session double recording whether the write lock was released.
 *
 * RealtimeController::stream() only ever calls session()->close() (to drop the
 * FileHandler write lock before the long poll), so this double exposes just that
 * method. A test injects it as the `session` service and asserts $closed became
 * true, proving the lock-release path ran without standing up a real session.
 */
final class FakeSession
{
    /** True once close() was called (the write lock was released). */
    public bool $closed = false;

    /**
     * Marks the session write lock as released.
     *
     * @return void
     */
    public function close(): void
    {
        $this->closed = true;
    }
}
