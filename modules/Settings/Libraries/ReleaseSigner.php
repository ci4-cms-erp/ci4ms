<?php

declare(strict_types=1);

namespace Modules\Settings\Libraries;

use RuntimeException;

/**
 * Offline Ed25519 release signing helper.
 *
 * CLI ONLY. This class must never be referenced from a controller, view, route
 * or filter — it handles the publisher's private key material. The secret key is
 * sealed at rest with a passphrase (argon2id -> secretbox) and every plaintext
 * copy is wiped with sodium_memzero() as soon as it is no longer needed.
 *
 * The keyfile is forced to live outside ROOTPATH so a web server misconfiguration
 * can never expose it.
 */
final class ReleaseSigner
{
    public const KEYFILE_SCHEMA   = 1;
    public const SIG_FILE_SCHEMA  = 1;
    public const SIG_ALG          = 'ed25519';
    public const MIN_PASSWORD_LEN = 12;

    private const KDF = 'argon2id';

    /** Argon2id cost bounds enforced on values read back from a keyfile. */
    private const MIN_OPSLIMIT = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
    private const MAX_OPSLIMIT = SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE;
    private const MIN_MEMLIMIT = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
    private const MAX_MEMLIMIT = SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE;

    private string $keyfilePath;

    /**
     * @param string $keyfilePath Absolute path of the sealed keyfile, outside ROOTPATH
     *
     * @throws RuntimeException When the path is empty, its directory is missing or it resolves inside ROOTPATH
     */
    public function __construct(string $keyfilePath)
    {
        $this->keyfilePath = self::assertOutsideRoot($keyfilePath);
    }

    /**
     * Normalises a keyfile path and refuses anything that resolves inside the project root.
     *
     * The file itself may not exist yet, so only its parent directory is resolved
     * with realpath(); a missing directory is rejected rather than created. The
     * basename is never resolved, so a symlink there is refused outright instead of
     * being followed past this guard. The prefix comparison is case-insensitive
     * because a case-insensitive filesystem would otherwise accept
     * ".../WWW/project/public/release.key" as "outside" the root.
     *
     * @param string $path Keyfile path as supplied by the publisher
     *
     * @return string Normalised absolute path
     *
     * @throws RuntimeException When the path is empty, is a symlink, its directory is missing or it resolves under the project root
     */
    public static function assertOutsideRoot(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Keyfile path is empty.');
        }

        if (is_link($path)) {
            throw new RuntimeException('Keyfile path is a symlink, refusing to follow it: ' . $path);
        }

        $directory = realpath(dirname($path));
        if ($directory === false) {
            throw new RuntimeException('Keyfile directory does not exist: ' . dirname($path));
        }

        $resolved = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);

        foreach ([realpath(ROOTPATH), realpath(FCPATH)] as $forbidden) {
            if ($forbidden === false) {
                continue;
            }

            $prefix = rtrim($forbidden, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (str_starts_with(mb_strtolower($resolved), mb_strtolower($prefix))) {
                throw new RuntimeException('Keyfile must live outside the project root: ' . $resolved);
            }
        }

        return $resolved;
    }

    /**
     * Returns the normalised keyfile path this signer operates on.
     */
    public function path(): string
    {
        return $this->keyfilePath;
    }

    /**
     * Generates a new Ed25519 keypair and writes it to a passphrase sealed keyfile.
     *
     * Refuses to overwrite an existing keyfile. The private key never leaves this
     * method and is never returned or printed.
     *
     * @param string $keyId    Key identifier used inside manifest.json.sig
     * @param string $password Passphrase used to seal the private key
     *
     * @return array{key_id: string, public_key: string, fingerprint: string, path: string}
     *
     * @throws RuntimeException When the keyfile exists, the key id is malformed or the passphrase is too weak
     */
    public function generate(string $keyId, string $password): array
    {
        if (file_exists($this->keyfilePath)) {
            throw new RuntimeException('Keyfile already exists, refusing to overwrite: ' . $this->keyfilePath);
        }

        if (preg_match('/^[a-z0-9][a-z0-9\-]{2,63}$/', $keyId) !== 1) {
            throw new RuntimeException('Key id must be 3-64 chars of lowercase letters, digits and dashes.');
        }

        self::assertPassword($password);

        $pair      = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($pair);
        $publicKey = sodium_crypto_sign_publickey($pair);
        sodium_memzero($pair);

        $encodedPublicKey = base64_encode($publicKey);

        try {
            $this->writeKeyfile($keyId, $secretKey, $encodedPublicKey, $password);
        } finally {
            sodium_memzero($secretKey);
        }

        return [
            'key_id'      => $keyId,
            'public_key'  => $encodedPublicKey,
            'fingerprint' => ManifestVerifier::fingerprint($encodedPublicKey),
            'path'        => $this->keyfilePath,
        ];
    }

    /**
     * Signs raw payload bytes with the sealed private key.
     *
     * @param string $password Passphrase that seals the keyfile
     * @param string $payload  Exact bytes to sign (the manifest.json body)
     *
     * @return array{key_id: string, public_key: string, fingerprint: string, signature: string}
     *
     * @throws RuntimeException When the keyfile is missing, malformed or the passphrase is wrong
     */
    public function sign(string $password, string $payload): array
    {
        $meta      = $this->readKeyfile();
        $secretKey = $this->unseal($meta, $password);

        try {
            $signature = sodium_crypto_sign_detached($payload, $secretKey);
        } finally {
            sodium_memzero($secretKey);
        }

        return [
            'key_id'      => $meta['key_id'],
            'public_key'  => $meta['public_key'],
            'fingerprint' => ManifestVerifier::fingerprint($meta['public_key']),
            'signature'   => base64_encode($signature),
        ];
    }

    /**
     * Reads the public half of the keyfile without needing the passphrase.
     *
     * @return array{key_id: string, public_key: string, fingerprint: string, created: string}
     *
     * @throws RuntimeException When the keyfile is missing or malformed
     */
    public function publicInfo(): array
    {
        $meta = $this->readKeyfile();

        return [
            'key_id'      => $meta['key_id'],
            'public_key'  => $meta['public_key'],
            'fingerprint' => ManifestVerifier::fingerprint($meta['public_key']),
            'created'     => $meta['created'],
        ];
    }

    /**
     * Builds the manifest.json.sig carrier document for a single signature.
     *
     * @param string $keyId           Signing key id
     * @param string $base64Signature Base64 detached signature
     *
     * @return string Raw bytes to write next to manifest.json
     */
    public static function buildSignatureFile(string $keyId, string $base64Signature): string
    {
        return (string) json_encode([
            'schema'     => self::SIG_FILE_SCHEMA,
            'signatures' => [
                ['key_id' => $keyId, 'alg' => self::SIG_ALG, 'sig' => $base64Signature],
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Rejects passphrases that are too short to protect an offline signing key.
     *
     * @throws RuntimeException
     */
    public static function assertPassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            throw new RuntimeException('Passphrase must be at least ' . self::MIN_PASSWORD_LEN . ' characters.');
        }
    }

    /**
     * Seals the private key and writes the keyfile with 0600 permissions.
     *
     * The file is created with fopen('xb') and narrowed with fchmod() before a
     * single secret byte is written: touch()+chmod() left the keyfile world
     * readable for the length of the umask window, and a failed write after a
     * successful touch() left a zero byte keyfile that generate() then refused to
     * overwrite.
     *
     * @throws RuntimeException
     */
    private function writeKeyfile(string $keyId, string $secretKey, string $encodedPublicKey, string $password): void
    {
        $salt     = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce    = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $opslimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
        $memlimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;

        $derived = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            $opslimit,
            $memlimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        try {
            $box = sodium_crypto_secretbox($secretKey, $nonce, $derived);
        } finally {
            sodium_memzero($derived);
        }

        $payload = json_encode([
            'schema'     => self::KEYFILE_SCHEMA,
            'key_id'     => $keyId,
            'public_key' => $encodedPublicKey,
            'created'    => gmdate('c'),
            'kdf'        => self::KDF,
            'opslimit'   => $opslimit,
            'memlimit'   => $memlimit,
            'salt'       => base64_encode($salt),
            'nonce'      => base64_encode($nonce),
            'box'        => base64_encode($box),
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        // The umask window makes the exclusive create itself 0600: chmod() after
        // fopen() would leave a readable, already open-able file behind.
        $previousUmask = umask(0077);

        try {
            $handle = @fopen($this->keyfilePath, 'xb');
        } finally {
            umask($previousUmask);
        }

        if ($handle === false) {
            throw new RuntimeException('Could not create the keyfile exclusively: ' . $this->keyfilePath);
        }

        if ((fileperms($this->keyfilePath) & 0777) !== 0600 && !chmod($this->keyfilePath, 0600)) {
            fclose($handle);
            @unlink($this->keyfilePath);

            throw new RuntimeException('Could not create the keyfile with 0600 permissions: ' . $this->keyfilePath);
        }

        $bytes = (string) $payload;

        if (fwrite($handle, $bytes) !== strlen($bytes)) {
            fclose($handle);
            @unlink($this->keyfilePath);

            throw new RuntimeException('Could not write the keyfile: ' . $this->keyfilePath);
        }

        fclose($handle);
    }

    /**
     * Loads and structurally validates the keyfile envelope.
     *
     * The argon2id cost parameters are attacker-reachable through a corrupted or
     * swapped keyfile, and sodium_crypto_pwhash() happily tries to allocate
     * whatever memlimit says, so both are type checked and clamped to the range
     * the library itself defines.
     *
     * @return array{key_id: string, public_key: string, created: string, opslimit: int, memlimit: int, salt: string, nonce: string, box: string}
     *
     * @throws RuntimeException
     */
    private function readKeyfile(): array
    {
        if (!is_file($this->keyfilePath) || !is_readable($this->keyfilePath)) {
            throw new RuntimeException('Keyfile not found or not readable: ' . $this->keyfilePath);
        }

        $meta = json_decode((string) file_get_contents($this->keyfilePath), true);

        if (!is_array($meta) || ($meta['schema'] ?? null) !== self::KEYFILE_SCHEMA || ($meta['kdf'] ?? null) !== self::KDF) {
            throw new RuntimeException('Keyfile envelope is malformed or uses an unsupported schema.');
        }

        foreach (['key_id', 'public_key', 'created', 'salt', 'nonce', 'box'] as $field) {
            if (!isset($meta[$field]) || !is_string($meta[$field]) || $meta[$field] === '') {
                throw new RuntimeException('Keyfile envelope is missing the "' . $field . '" field.');
            }
        }

        foreach (['opslimit', 'memlimit'] as $field) {
            if (array_key_exists($field, $meta) && !is_int($meta[$field])) {
                throw new RuntimeException('Keyfile envelope field "' . $field . '" must be an integer.');
            }
        }

        return [
            'key_id'     => $meta['key_id'],
            'public_key' => $meta['public_key'],
            'created'    => $meta['created'],
            'opslimit'   => self::clamp($meta['opslimit'] ?? SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, self::MIN_OPSLIMIT, self::MAX_OPSLIMIT),
            'memlimit'   => self::clamp($meta['memlimit'] ?? SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE, self::MIN_MEMLIMIT, self::MAX_MEMLIMIT),
            'salt'       => $meta['salt'],
            'nonce'      => $meta['nonce'],
            'box'        => $meta['box'],
        ];
    }

    /**
     * Constrains a cost parameter to an inclusive range.
     *
     * @param int $value Value read from the keyfile
     * @param int $min   Lowest accepted value
     * @param int $max   Highest accepted value
     *
     * @return int
     */
    private static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /**
     * Derives the sealing key from the passphrase and opens the secretbox.
     *
     * @param array{opslimit: int, memlimit: int, salt: string, nonce: string, box: string} $meta
     *
     * @return string Raw Ed25519 secret key — caller must sodium_memzero() it
     *
     * @throws RuntimeException When the envelope is corrupt or the passphrase is wrong
     */
    private function unseal(array $meta, string $password): string
    {
        $salt  = base64_decode($meta['salt'], true);
        $nonce = base64_decode($meta['nonce'], true);
        $box   = base64_decode($meta['box'], true);

        if ($salt === false || $nonce === false || $box === false
            || strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES
            || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Keyfile envelope is corrupt.');
        }

        $derived = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            $meta['opslimit'],
            $meta['memlimit'],
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        try {
            $secretKey = sodium_crypto_secretbox_open($box, $nonce, $derived);
        } finally {
            sodium_memzero($derived);
        }

        if ($secretKey === false) {
            throw new RuntimeException('Keyfile could not be unsealed: wrong passphrase or corrupted file.');
        }

        return $secretKey;
    }
}
