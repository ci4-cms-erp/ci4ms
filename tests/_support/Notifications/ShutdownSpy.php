<?php

namespace Tests\Support\Notifications {
    /**
     * Capture point for the shutdown callback RealtimeController::stream() registers.
     *
     * WHY THIS EXISTS: under `ignore_user_abort(false)` PHP notices a dropped client
     * during a write and ends the script with a zend_bailout, so the fast-path release
     * after the poll loop never runs. The slot is therefore ALSO handed back from a
     * `register_shutdown_function` callback, guarded by a `$released` flag so the two
     * paths cannot double-release. Neither half of that contract is observable from a
     * normal test: shutdown callbacks only fire when the process ends.
     *
     * HOW IT IS OBSERVED: the companion function declared at the bottom of this file
     * lives in the controller's own namespace, so PHP's unqualified-call fallback binds
     * `register_shutdown_function()` inside stream() to it instead of the global one.
     * The callback is then merely recorded, and the test decides when to run it —
     * {@see $invokeImmediately} fires it the moment it is registered, which is exactly
     * the abort case (release happens before the loop's fast path), while
     * {@see invokeAll()} fires it afterwards, which is the "shutdown runs after a normal
     * finish" case that must NOT release a second time.
     *
     * LOAD ORDER MATTERS: PHP caches the resolved function per call site on first
     * execution, so this file must be required BEFORE the first stream() call of the
     * process. The test class that needs it requires it at file scope, which PHPUnit
     * executes while it loads the test file — long before any test method runs.
     *
     * Recording only (the real global function is never called), so no test leaves a
     * live callback behind to fire against a torn-down double at process exit.
     */
    final class ShutdownSpy
    {
        /**
         * Callbacks stream() registered since the last reset, in registration order.
         *
         * @var list<callable>
         */
        private static array $callbacks = [];

        /** When true, a recorded callback is executed at once (client-abort simulation). */
        public static bool $invokeImmediately = false;

        /**
         * Records a callback the controller registered for shutdown.
         *
         * @param callable $callback The slot-release closure.
         *
         * @return void
         */
        public static function record(callable $callback): void
        {
            self::$callbacks[] = $callback;

            if (self::$invokeImmediately) {
                $callback();
            }
        }

        /**
         * Clears every recorded callback and disarms immediate invocation.
         *
         * @return void
         */
        public static function reset(): void
        {
            self::$callbacks         = [];
            self::$invokeImmediately = false;
        }

        /**
         * How many shutdown callbacks were registered since the last reset.
         *
         * @return int The registration count.
         */
        public static function count(): int
        {
            return count(self::$callbacks);
        }

        /**
         * Runs every recorded callback, the way PHP would at end of request.
         *
         * @return void
         */
        public static function invokeAll(): void
        {
            foreach (self::$callbacks as $callback) {
                $callback();
            }
        }

        /**
         * Whether the namespaced shim really shadows the global function.
         *
         * @return bool True when the controller's namespace owns the function.
         */
        public static function isInstalled(): bool
        {
            return function_exists('Modules\Notifications\Controllers\register_shutdown_function');
        }
    }
}

namespace Modules\Notifications\Controllers {
    use Tests\Support\Notifications\ShutdownSpy;

    /**
     * Test-only shadow of the global register_shutdown_function() for this namespace.
     *
     * Unqualified calls inside a namespaced function resolve here first, so stream()'s
     * slot-release callback is captured by {@see ShutdownSpy} instead of being deferred
     * to process exit, where no assertion could ever reach it.
     *
     * @param callable $callback The callback stream() wants to run at shutdown.
     *
     * @return void
     */
    function register_shutdown_function(callable $callback): void
    {
        ShutdownSpy::record($callback);
    }
}
