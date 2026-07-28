<?php

declare(strict_types=1);

namespace Modules\Settings\Libraries;

/**
 * Ed25519 signed release manifest verifier.
 *
 * Deliberately pure: no network, no database, no env(), no cache(), no lang().
 * Every failure is reported as a stable machine code and the caller maps it to a
 * translation. Verification always runs over the raw HTTP body bytes — the JSON
 * is only decoded after the signature check passes, because re-serialising the
 * payload would silently break (or bypass) the signature.
 */
final class ManifestVerifier
{
    private const SIG_SCHEMA      = 1;
    private const MANIFEST_SCHEMA = 1;
    private const SIG_ALG         = 'ed25519';
    private const HASH_ALGO       = 'sha256';
    private const HASH_HEX_LENGTH = 64;

    /**
     * The manifest version is written straight into .env by the caller, so it is
     * constrained to digits and dots here rather than at the write site alone.
     */
    private const VERSION_PATTERN = '/^\d+(?:\.\d+){1,3}$/';

    /**
     * @var array<array-key, mixed> Keyring indexed by key_id, see Modules\Settings\Config\UpdateKeys
     */
    private array $keyring;

    /**
     * @param array<array-key, mixed> $keyring Trusted key set indexed by key_id
     */
    public function __construct(array $keyring)
    {
        $this->keyring = $keyring;
    }

    /**
     * Verifies a detached Ed25519 signature file against the raw manifest bytes.
     *
     * Rejection order: malformed signature carrier, duplicate key_id, revoked
     * key_id, empty keyring, no signature made by a trusted active key, then
     * manifest JSON/shape. Unknown key_ids are skipped without failing so a
     * publisher can dual-sign while rotating keys.
     *
     * @param string $rawManifest Exact bytes of manifest.json as received
     * @param string $rawSigFile  Exact bytes of manifest.json.sig as received
     *
     * @return array{ok: bool, code: string, key_id: string|null, manifest: array<string, mixed>|null}
     *         code is one of: ok, no_trusted_keys, bad_sig_format, revoked_key,
     *         no_valid_signature, duplicate_key_id, bad_manifest_json, bad_manifest_shape
     */
    public function verify(string $rawManifest, string $rawSigFile): array
    {
        $entries = $this->parseSignatureFile($rawSigFile);
        if ($entries === null) {
            return $this->fail('bad_sig_format');
        }

        $keyIds = array_column($entries, 'key_id');
        if (count($keyIds) !== count(array_unique($keyIds))) {
            return $this->fail('duplicate_key_id');
        }

        // Revocation is a local fact, so it outranks an empty keyring: a carrier
        // that *references* a revoked key id must be diagnosed as such and not as
        // a config gap. Nothing is verified at this point — the reference alone is
        // enough to refuse, which is also why this is not evidence of a real
        // signature made with the burned key.
        foreach ($keyIds as $keyId) {
            $entry = $this->keyring[$keyId] ?? null;

            if (is_array($entry) && ($entry['status'] ?? null) === 'revoked') {
                return $this->fail('revoked_key');
            }
        }

        $activeKeys = $this->activePublicKeys();
        if ($activeKeys === []) {
            return $this->fail('no_trusted_keys');
        }

        $signerKeyId = null;
        foreach ($entries as $entry) {
            $publicKey = $activeKeys[$entry['key_id']] ?? null;
            if ($publicKey === null) {
                continue;
            }

            if (sodium_crypto_sign_verify_detached($entry['sig'], $rawManifest, $publicKey)) {
                $signerKeyId = $entry['key_id'];
                break;
            }
        }

        if ($signerKeyId === null) {
            return $this->fail('no_valid_signature');
        }

        $manifest = json_decode($rawManifest, true);
        if (!is_array($manifest)) {
            return $this->fail('bad_manifest_json');
        }

        if (!$this->hasValidShape($manifest)) {
            return $this->fail('bad_manifest_shape');
        }

        return ['ok' => true, 'code' => 'ok', 'key_id' => $signerKeyId, 'manifest' => $manifest];
    }

    /**
     * Binds a verified manifest to the repository, the requested version and the running version.
     *
     * Downgrades through the updater are refused; the existing backup rollback is
     * the supported way back.
     *
     * @param array<string, mixed> $manifest        Manifest returned by verify()
     * @param string               $repo            Expected "owner/name"
     * @param string               $expectedVersion Version the caller asked to install
     * @param string               $currentVersion  Version currently running
     *
     * @return array{ok: bool, code: string} code is one of: ok, repo_mismatch, version_mismatch, downgrade
     */
    public function checkBinding(array $manifest, string $repo, string $expectedVersion, string $currentVersion): array
    {
        if (($manifest['repo'] ?? null) !== $repo) {
            return ['ok' => false, 'code' => 'repo_mismatch'];
        }

        $version = (string) ($manifest['version'] ?? '');
        if ($version === '' || $version !== $expectedVersion) {
            return ['ok' => false, 'code' => 'version_mismatch'];
        }

        if (!version_compare($version, $currentVersion, '>')) {
            return ['ok' => false, 'code' => 'downgrade'];
        }

        return ['ok' => true, 'code' => 'ok'];
    }

    /**
     * Returns the signed SHA-256 hex hash recorded for a path.
     *
     * @param array<string, mixed> $manifest Manifest returned by verify()
     * @param string               $path     Repository relative path
     *
     * @return string|null Null when the path is absent from the manifest
     */
    public function fileHash(array $manifest, string $path): ?string
    {
        $hash = $manifest['files'][$path] ?? null;

        return is_string($hash) ? $hash : null;
    }

    /**
     * Timing-safe check of downloaded content against the signed manifest hash.
     *
     * A path missing from the manifest is a failure, never a fallback.
     *
     * @param array<string, mixed> $manifest Manifest returned by verify()
     * @param string               $path     Repository relative path
     * @param string               $content  Downloaded file bytes
     *
     * @return bool
     */
    public function verifyFile(array $manifest, string $path, string $content): bool
    {
        $expected = $this->fileHash($manifest, $path);
        if ($expected === null) {
            return false;
        }

        return hash_equals($expected, hash(self::HASH_ALGO, $content));
    }

    /**
     * Hex SHA-256 fingerprint of a base64 encoded Ed25519 public key.
     *
     * @param string $base64PublicKey Base64 encoded 32-byte public key
     *
     * @return string Empty string when the input is not a valid 32-byte key
     */
    public static function fingerprint(string $base64PublicKey): string
    {
        $raw = base64_decode($base64PublicKey, true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return '';
        }

        return hash(self::HASH_ALGO, $raw);
    }

    /**
     * Usable public keys, indexed by key_id, in raw binary form.
     *
     * @return array<string, non-empty-string>
     */
    private function activePublicKeys(): array
    {
        $keys = [];

        foreach ($this->keyring as $keyId => $entry) {
            if (!is_string($keyId) || !is_array($entry) || ($entry['status'] ?? null) !== 'active') {
                continue;
            }

            $raw = base64_decode((string) ($entry['public_key'] ?? ''), true);
            if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                continue;
            }

            $keys[$keyId] = $raw;
        }

        return $keys;
    }

    /**
     * Structurally validates the signature carrier and decodes its signatures.
     *
     * @return list<array{key_id: non-empty-string, sig: non-empty-string}>|null Null on any structural problem
     */
    private function parseSignatureFile(string $raw): ?array
    {
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['schema'] ?? null) !== self::SIG_SCHEMA) {
            return null;
        }

        $signatures = $data['signatures'] ?? null;
        if (!is_array($signatures) || $signatures === [] || !array_is_list($signatures)) {
            return null;
        }

        $entries = [];

        foreach ($signatures as $entry) {
            if (!is_array($entry)) {
                return null;
            }

            $keyId = $entry['key_id'] ?? null;
            $sig   = $entry['sig'] ?? null;

            if (!is_string($keyId) || $keyId === '' || ($entry['alg'] ?? null) !== self::SIG_ALG || !is_string($sig)) {
                return null;
            }

            $decoded = base64_decode($sig, true);
            if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_BYTES) {
                return null;
            }

            $entries[] = ['key_id' => $keyId, 'sig' => $decoded];
        }

        return $entries;
    }

    /**
     * Validates the decoded manifest against the schema contract.
     *
     * @param array<array-key, mixed> $manifest
     */
    private function hasValidShape(array $manifest): bool
    {
        if (($manifest['schema'] ?? null) !== self::MANIFEST_SCHEMA || ($manifest['algo'] ?? null) !== self::HASH_ALGO) {
            return false;
        }

        foreach (['repo', 'version', 'generated_at'] as $field) {
            $value = $manifest[$field] ?? null;
            if (!is_string($value) || $value === '') {
                return false;
            }
        }

        if (preg_match(self::VERSION_PATTERN, (string) $manifest['version']) !== 1) {
            return false;
        }

        $files = $manifest['files'] ?? null;
        if (!is_array($files) || $files === []) {
            return false;
        }

        foreach ($files as $path => $hash) {
            if ((string) $path === '' || !is_string($hash)) {
                return false;
            }

            if (strlen($hash) !== self::HASH_HEX_LENGTH || !ctype_xdigit($hash)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{ok: bool, code: string, key_id: string|null, manifest: array<string, mixed>|null}
     */
    private function fail(string $code): array
    {
        return ['ok' => false, 'code' => $code, 'key_id' => null, 'manifest' => null];
    }
}
