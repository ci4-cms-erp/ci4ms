<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Libraries;

/**
 * Migration/seed run lock.
 *
 * Based on `flock(LOCK_EX|LOCK_NB)`: the lock is tied by the operating
 * system to the **open file description** holding it — the kernel
 * AUTOMATICALLY releases it even if the process dies with a fatal error,
 * `SIGKILL`, or timeout. The flock is also freed the instant `fclose()` is
 * called; that's why the file handle must stay OPEN for as long as the lock
 * is held, until `release()` is called (see `$handle`).
 *
 * History: this class started as an exact copy of the
 * `modules/Settings/Libraries/UpdateService.php:871-893`
 * (`acquireLock()`/`releaseLock()`) pattern — a design that cleans up a
 * "stale" lock via `fopen(..., 'xb')` + a TTL based on the file's mtime.
 * That design had no heartbeat (a long run exceeding the TTL could get its
 * lock deleted by another process assuming it was stale) and had a TOCTOU
 * window between `unlink()` and `fopen('xb')`. This class was moved to
 * `flock()`, which structurally eliminates both problems — the
 * TTL/heartbeat concept becomes unnecessary with `flock()`, because lock
 * ownership is no longer tied to file content/a timestamp but to an open
 * file description tracked by the kernel itself.
 * `UpdateService::acquireLock()`/`releaseLock()` STILL carries the SAME
 * TTL/TOCTOU flaw — this was deliberately left out of this task's scope and
 * requires a separate user decision.
 *
 * It does NOT REPLACE the `$lock` flag in `app/Config/Migrations.php` (that
 * flag is deliberately left off) — this class is only an
 * application-level concurrency lock for `MigrationManager` module's own
 * run endpoints (and the `Modules\Backend\Commands\Ci4msMigrate` CLI
 * command).
 *
 * Platform constraint: `flock()` is NOT RELIABLE on network filesystems
 * such as NFS. The lock file is kept under `WRITEPATH` (`writable/locks/`)
 * on the assumption of a local disk; if that assumption breaks (`WRITEPATH`
 * moved to an NFS mount), this class's exclusion guarantee becomes invalid.
 */
class RunLock
{
    private string $lockFile;

    /**
     * File handle that stays open while the lock is held.
     *
     * `flock()` is tied to an open file description — this handle must
     * stay OPEN until `release()` is called (or the process itself dies).
     * Since the flock is freed the instant `fclose()` is called, this field
     * is only closed inside `release()`; it's never `fclose()`d inside
     * `acquire()` (except for a failed flock attempt).
     *
     * @var resource|null
     */
    private mixed $handle = null;

    /**
     * Tracks whether this instance ACTUALLY holds the lock. Only becomes
     * `true` when `acquire()` truly wins the lock (returns `true`);
     * `release()` only closes `$handle` and resets to `false` while this
     * flag is `true` — so a second `release()` call on the same instance is
     * a no-op instead of calling `fclose()` again on an already-closed
     * handle and triggering a PHP `E_WARNING`.
     */
    private bool $held = false;

    /**
     * @param string|null $lockFile Full path of the lock file. If `null`,
     *                              `WRITEPATH.'locks/migration_manager.lock'`
     *                              is used (never under `public/`).
     *
     * @throws \RuntimeException If the lock directory can't be created, isn't
     *                            writable, or is a symlink (see
     *                            `ensureDirectory()`).
     */
    public function __construct(?string $lockFile = null)
    {
        $this->lockFile = $lockFile ?? WRITEPATH . 'locks/migration_manager.lock';

        $this->ensureDirectory(dirname($this->lockFile));
    }

    /**
     * Atomically acquires the run lock with `flock(LOCK_EX|LOCK_NB)` and
     * marks this instance as the lock's owner.
     *
     * The lock file is opened in `'c'` mode (create-or-open, does NOT
     * TRUNCATE) — NOT `'w'` or `'xb'`: `'w'` would reset the file on every
     * open, which would let even a process that FAILS to get the flock
     * (`fopen()` succeeds, only the subsequent `flock()` fails) erase the
     * diagnostic content written by the lock's owner. That's why the
     * diagnostic content (pid + timestamp) is only written with
     * `ftruncate()` + `fwrite()` AFTER the lock is SUCCESSFULLY acquired,
     * not at open time. This content is entirely optional and meant to be
     * readable by a human operator via `cat writable/locks/...` — no logic
     * branch in this class ever READS it back.
     *
     * Symlink protection (`'c'` mode, unlike the old `'xb'`, DOES FOLLOW
     * symlinks — no `O_CREAT|O_EXCL`): a fail-fast `is_link()` check
     * happens BEFORE `fopen()` — this is only to avoid opening an
     * unnecessary file handle, it's NOT SUFFICIENT on its own against
     * TOCTOU (the path can change between the check and `fopen()`). The
     * real guarantee comes AFTER `fopen()`: `fstat($handle)` (the inode
     * that `fopen()` ACTUALLY opened, following any symlink) is compared
     * against `lstat($this->lockFile)` (the inode currently at the path,
     * NOT following the final path component). On a plain file the two
     * ALWAYS give the same `dev`+`ino` pair; they diverge if the path IS a
     * symlink (or TURNED INTO one between the check and `fopen()`). If they
     * don't match, the handle is closed immediately and `false` is
     * returned WITHOUT EVER REACHING `flock()`/`ftruncate()`/`fwrite()` —
     * so another file's content can't be truncated and overwritten.
     * `clearstatcache()` is mandatory before every check (PHP's stat cache
     * can hide a symlink swap). On a legitimate (non-symlink) path this
     * comparison does NOT CHANGE flock semantics — fstat/lstat always
     * match, the flow proceeds unchanged. This check only protects the
     * lock FILE itself; the lock DIRECTORY being a symlink is separately
     * blocked in `ensureDirectory()` (the two complement each other, see
     * that method's docblock).
     *
     * If `fopen()` itself fails (permission/directory issue), `false` is
     * returned — consistent with the previous mtime/TTL design. If
     * `flock()` returns `false` (the lock is still held by another open
     * file description), the handle is closed immediately and `false` is
     * returned, no leak.
     *
     * @return bool `true` if the lock was acquired by this call; `false` if
     *              the lock is held by another process (or is this
     *              instance's own lock it already successfully acquired
     *              and hasn't `release()`d yet), or because the path is or
     *              became a symlink.
     */
    public function acquire(): bool
    {
        $lockDir = dirname($this->lockFile);

        clearstatcache(true, $lockDir);
        clearstatcache(true, $this->lockFile);

        if (is_link($lockDir) || is_link($this->lockFile)) {
            return false;
        }

        $handle = @fopen($this->lockFile, 'c');

        if ($handle === false) {
            return false;
        }

        $openedStat = fstat($handle);

        clearstatcache(true, $this->lockFile);
        $pathStat = @lstat($this->lockFile);

        if ($openedStat === false || $pathStat === false
            || $openedStat['dev'] !== $pathStat['dev']
            || $openedStat['ino'] !== $pathStat['ino']) {
            fclose($handle);

            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->handle = $handle;
        $this->held   = true;

        // Diagnostic, optional content — never read back anywhere in the logic.
        @ftruncate($handle, 0);
        @fwrite($handle, sprintf("pid=%d acquired_at=%s\n", getmypid(), date(DATE_ATOM)));
        @fflush($handle);

        return true;
    }

    /**
     * Releases the lock — only if this instance ACTUALLY holds it.
     *
     * Closed via `flock($this->handle, LOCK_UN)` + `fclose($this->handle)`.
     * **NO `unlink()`**: deleting a flock'd file produces a classic race —
     * the deleted path can be recreated on disk as a different inode, and
     * two processes could each think they hold the lock on two different
     * inodes without knowing about each other (both think "I got the
     * lock"). The lock file PERMANENTLY remaining on disk in its
     * empty/diagnostic-content state is NORMAL and EXPECTED in this design
     * — it is NOT a leak. (The "won't leak" acceptance criterion refers to
     * a `flock()` being left HANGING, i.e. a process never releasing the
     * lock; an empty lock file remaining on disk is a separate, deliberate
     * behavior.)
     *
     * The `$held` guard is still needed — but no longer to avoid "deleting
     * another process's lock" (that risk is already GONE since `unlink()`
     * is never done). The guard's new justification: if `release()` is
     * called a SECOND time on the same instance (`Ci4msMigrate::run()`
     * calls `release()` on the same `$lock` instance from two separate
     * `finally` blocks), without the guard `fclose()` would be called again
     * on an already-CLOSED handle and PHP would emit a "supplied resource
     * is not a valid stream resource" `E_WARNING`. The guard prevents that;
     * it's also a no-op for the same reason if the lock was never held
     * (`acquire()` was never called or returned `false`).
     *
     * @return void
     */
    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);

        $this->handle = null;
        $this->held   = false;
    }

    /**
     * Creates the directory the lock file lives in if needed, and verifies
     * it's writable.
     *
     * The directory is checked for being a symlink BEFORE the `is_dir()`
     * check: the `writable/` directory has `0777` permissions, so any local
     * user could delete `writable/locks` and replace it with a symlink
     * pointing to another target (e.g. a directory containing a sensitive
     * file) — `is_dir()` would SILENTLY accept that, because `is_dir()`
     * also returns `true` if a symlink's target is a directory. This check
     * rejects that scenario early and explicitly. The fstat/lstat
     * comparison in `acquire()` only protects the lock FILE itself, it does
     * NOT COVER this DIRECTORY-level attack — the two complement each
     * other.
     *
     * @param string $path Directory path to create/verify.
     *
     * @return void
     *
     * @throws \RuntimeException If the directory is a symlink, can't be
     *                            created, or isn't writable.
     */
    private function ensureDirectory(string $path): void
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            throw new \RuntimeException("Kilit dizini bir sembolik bağ: {$path}");
        }

        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Kilit dizini oluşturulamadı: {$path}");
        }

        if (!is_writable($path)) {
            throw new \RuntimeException("Kilit dizini yazılabilir değil: {$path}");
        }
    }
}
