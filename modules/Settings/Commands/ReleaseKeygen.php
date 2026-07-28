<?php

declare(strict_types=1);

namespace Modules\Settings\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Settings\Libraries\ReleaseSigner;
use Throwable;

/**
 * Generates the Ed25519 keypair used to sign release manifests.
 *
 * Offline publisher tooling. The private key is sealed with a passphrase and
 * written outside the project root; only the public key, its fingerprint and a
 * paste-ready UpdateKeys.php block are printed.
 *
 * Usage: php spark ci4ms:release:keygen --keyfile ~/.ci4ms/release.key --key-id ci4ms-2026-a
 */
class ReleaseKeygen extends BaseCommand
{
    use SecretPromptTrait;

    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:release:keygen';
    protected $description = 'Generate an Ed25519 release signing keypair sealed with a passphrase.';
    protected $usage       = 'ci4ms:release:keygen [--keyfile <path>] [--key-id <id>]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--keyfile' => 'Path of the sealed keyfile to create. Must be outside the project root.',
        '--key-id'  => 'Key identifier written into manifest.json.sig (default: ci4ms-<year>-a).',
    ];

    /**
     * Creates the sealed keyfile and prints the publishable key material.
     *
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $keyfile = (string) (CLI::getOption('keyfile') ?: CLI::prompt('Keyfile path (outside the project root)', null, ['required']));
        $keyId   = (string) (CLI::getOption('key-id') ?: CLI::prompt('Key id', 'ci4ms-' . gmdate('Y') . '-a', ['required']));

        if (!is_dir(dirname($keyfile))) {
            CLI::error('Directory does not exist: ' . dirname($keyfile));
            CLI::write('Create it first, for example: mkdir -p ' . dirname($keyfile) . ' && chmod 700 ' . dirname($keyfile), 'yellow');

            return;
        }

        $password = $this->promptSecret('Keyfile passphrase (min ' . ReleaseSigner::MIN_PASSWORD_LEN . ' chars)');
        $confirm  = $this->promptSecret('Repeat passphrase');

        if (!hash_equals($password, $confirm)) {
            sodium_memzero($password);
            sodium_memzero($confirm);
            CLI::error('Passphrases do not match.');

            return;
        }

        sodium_memzero($confirm);

        try {
            $signer = new ReleaseSigner($keyfile);
            $result = $signer->generate($keyId, $password);
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return;
        } finally {
            sodium_memzero($password);
        }

        $this->report($result);
    }

    /**
     * Prints the public key, fingerprint and the UpdateKeys.php snippet.
     *
     * @param array{key_id: string, public_key: string, fingerprint: string, path: string} $result
     */
    private function report(array $result): void
    {
        CLI::newLine();
        CLI::write('Keyfile written (0600): ' . $result['path'], 'green');
        CLI::write('Key id      : ' . $result['key_id']);
        CLI::write('Public key  : ' . $result['public_key']);
        CLI::write('Fingerprint : ' . $result['fingerprint']);
        CLI::newLine();
        CLI::write('Paste into modules/Settings/Config/UpdateKeys.php ($keys):', 'yellow');
        CLI::newLine();
        CLI::write("    '{$result['key_id']}' => [");
        CLI::write("        'public_key'  => '{$result['public_key']}',");
        CLI::write("        'status'      => 'active',");
        CLI::write("        'added'       => '" . gmdate('Y-m-d') . "',");
        CLI::write("        'fingerprint' => '{$result['fingerprint']}',");
        CLI::write('    ],');
        CLI::newLine();
        CLI::write('Back the keyfile up offline. Losing it means you can no longer sign releases;', 'light_red');
        CLI::write('leaking it means anyone can sign a release your users will trust.', 'light_red');
    }
}
