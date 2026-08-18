<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use BadMethodCallException;
use Closure;
use Modules\Backend\Libraries\RunLock;

/**
 * A changed-file entry that measures the run lock the moment it is destroyed.
 *
 * `UpdateService::applyUpdate()` holds its `RunLock` in a plain local (`$lock`),
 * so the lock frees itself when the frame is torn down even if the
 * `finally { $lock->release(); }` is deleted — which is why asserting "a fresh
 * acquire() succeeds after applyUpdate() returned" cannot tell the two apart.
 * This probe creates the one observation window where they DO differ.
 *
 * Passed inside the `$allChangedFiles` argument, it lives in a PARAMETER of
 * `applyUpdate()`. Zend frees a frame's compiled variables in ascending slot
 * order (`zend_free_compiled_variables()`), and the calling convention writes
 * arguments into slots `0..num_args-1`, so every parameter is destroyed strictly
 * BEFORE any local — including `$lock`. That makes this destructor run while the
 * refcount-driven release still cannot have happened:
 *
 *  - `finally` present  → the lock is already free here  → acquire() true
 *  - `finally` deleted  → `$lock` is still alive and held → acquire() false
 *
 * Two shape requirements come with that, both enforced by the caller rather than
 * here: the probe must not be the LAST element of `$allChangedFiles` (the loop's
 * `$f` is a local and would keep it alive past `$lock`), and the caller must hold
 * no reference of its own to the probe (the array literal has to be built inline
 * in the `applyUpdate()` argument list, or its refcount never reaches zero when
 * the callee's frame goes away).
 *
 * `ArrayAccess` is implemented because the removed-file loop reads `$f['status']`
 * on every entry; `'modified'` keeps the probe out of `$removedFiles`.
 */
final class LockStateProbe implements \ArrayAccess
{
    /**
     * @param string                $lockFile Lock path applyUpdate() hardcodes internally
     * @param Closure(bool): void   $report   Receives whether the lock was free at destruction time
     */
    public function __construct(private string $lockFile, private Closure $report)
    {
    }

    /**
     * Attempts an independent acquire and reports the outcome.
     *
     * @return void
     */
    public function __destruct()
    {
        $probe = new RunLock($this->lockFile);
        $free  = $probe->acquire();

        if ($free) {
            $probe->release();
        }

        ($this->report)($free);
    }

    /**
     * @param mixed $offset Array key applyUpdate() reads
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'status' || $offset === 'filename';
    }

    /**
     * @param mixed $offset Array key applyUpdate() reads
     *
     * @return string
     */
    public function offsetGet(mixed $offset): string
    {
        return $offset === 'status' ? 'modified' : 'qa-lock-state-probe';
    }

    /**
     * @param mixed $offset Array key
     * @param mixed $value  Value
     *
     * @return void
     *
     * @throws BadMethodCallException Always — applyUpdate() only ever reads these entries
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('LockStateProbe is read-only; applyUpdate() must never write to a changed-file entry.');
    }

    /**
     * @param mixed $offset Array key
     *
     * @return void
     *
     * @throws BadMethodCallException Always — applyUpdate() only ever reads these entries
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('LockStateProbe is read-only; applyUpdate() must never unset a changed-file entry.');
    }
}
