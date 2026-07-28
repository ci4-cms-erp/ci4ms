<?php

declare(strict_types=1);

namespace Modules\Settings\Commands;

use CodeIgniter\CLI\CLI;

/**
 * Terminal passphrase prompt that does not echo what the publisher types.
 *
 * CodeIgniter's CLI::prompt() has no no-echo mode, so the signing passphrase used
 * to land in the terminal scrollback (and in whatever records it). Echo is
 * disabled through stty and restored in a finally block, so an interrupted or
 * failed prompt can never leave the terminal silent.
 */
trait SecretPromptTrait
{
    /**
     * Reads a secret from STDIN with terminal echo disabled.
     *
     * Warns and keeps going with a visible prompt when echo cannot be switched off
     * (Windows, a pipe instead of a terminal, or a hardened PHP without exec()).
     *
     * @param string $label Prompt label shown before the cursor
     *
     * @return string The raw secret without its trailing newline
     */
    private function promptSecret(string $label): string
    {
        $echoDisabled = $this->disableEcho();

        if (!$echoDisabled) {
            CLI::write('Terminal echo could not be disabled — the passphrase will be visible while you type it.', 'yellow');
        }

        CLI::print($label . ': ');

        try {
            $secret = (string) fgets(STDIN);
        } finally {
            if ($echoDisabled) {
                exec('stty echo 2>/dev/null');
            }

            CLI::newLine();
        }

        return rtrim($secret, "\r\n");
    }

    /**
     * Switches terminal echo off.
     *
     * @return bool False when echo is still on and the caller must warn
     */
    private function disableEcho(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('exec')) {
            return false;
        }

        $output = [];
        $status = 1;
        exec('stty -echo 2>/dev/null', $output, $status);

        return $status === 0;
    }
}
