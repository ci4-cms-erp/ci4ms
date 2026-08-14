<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use RuntimeException;

/**
 * Builds signed release manifests for the updater tests.
 *
 * Every keypair is generated at runtime with sodium_crypto_sign_keypair(); no
 * private key material is ever written to the repository or to disk. The manifest
 * bytes produced here are byte-identical to what
 * Modules\Settings\Commands\ReleaseManifest emits: JSON_UNESCAPED_SLASHES |
 * JSON_UNESCAPED_UNICODE, files ksort()ed with SORT_STRING, no trailing newline.
 * That exactness is the point — the signature covers the raw bytes.
 */
final class ReleaseFixture
{
    /** Sentinel passed as an override value to delete a manifest key entirely. */
    public const REMOVE = "\0__remove__\0";

    /** Repository slug the updater binds every manifest to. */
    public const REPO = 'ci4-cms-erp/ci4ms';

    /** Release version the fixtures describe. */
    public const VERSION = '0.35.0.0';

    /** Frozen timestamp so two fixture manifests can be compared byte for byte. */
    public const GENERATED_AT = '2026-07-27T11:36:12+00:00';

    /** Primary signing key id. */
    public const KEY_ID = 'ci4ms-2026-a';

    /** Secondary signing key id, used for rotation and revocation scenarios. */
    public const SECOND_KEY_ID = 'ci4ms-2026-b';

    /** Base64 encoded 32-byte Ed25519 public key. */
    private string $publicKey;

    /** Raw 64-byte Ed25519 secret key. */
    private string $secretKey;

    /**
     * @param string $publicKey Base64 encoded public key
     * @param string $secretKey Raw secret key
     */
    private function __construct(string $publicKey, string $secretKey)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    /**
     * Generates a throwaway Ed25519 keypair for the current test run.
     *
     * @return self
     */
    public static function newKeypair(): self
    {
        $pair = sodium_crypto_sign_keypair();

        return new self(
            base64_encode(sodium_crypto_sign_publickey($pair)),
            sodium_crypto_sign_secretkey($pair)
        );
    }

    /**
     * Base64 encoded public key of this fixture keypair.
     *
     * @return string
     */
    public function publicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Keyring entry in the exact shape Modules\Settings\Config\UpdateKeys documents.
     *
     * @param string $status 'active' or 'revoked'
     *
     * @return array{public_key: string, status: string, added: string, fingerprint: string}
     */
    public function keyringEntry(string $status = 'active'): array
    {
        return [
            'public_key'  => $this->publicKey,
            'status'      => $status,
            'added'       => '2026-07-27',
            'fingerprint' => hash('sha256', (string) base64_decode($this->publicKey, true)),
        ];
    }

    /**
     * Detached Ed25519 signature over the exact payload bytes.
     *
     * @param string $payload Raw bytes to sign
     *
     * @return string Base64 encoded 64-byte signature
     *
     * @throws RuntimeException When the fixture was built without a secret key
     */
    public function sign(string $payload): string
    {
        $secretKey = $this->secretKey;

        if ($secretKey === '') {
            throw new RuntimeException('Fixture keypair holds no secret key.');
        }

        return base64_encode(sodium_crypto_sign_detached($payload, $secretKey));
    }

    /**
     * One signature carrier entry signed by this keypair.
     *
     * @param string $keyId   Key id written into the entry
     * @param string $payload Raw manifest bytes the signature covers
     * @param string $alg     Signature algorithm label
     *
     * @return array{key_id: string, alg: string, sig: string}
     */
    public function entry(string $keyId, string $payload, string $alg = 'ed25519'): array
    {
        return ['key_id' => $keyId, 'alg' => $alg, 'sig' => $this->sign($payload)];
    }

    /**
     * Complete manifest.json.sig carrier holding a single signature from this keypair.
     *
     * @param string $payload Raw manifest bytes the signature covers
     * @param string $keyId   Key id written into the entry
     *
     * @return string Raw signature file bytes
     */
    public function signatureFile(string $payload, string $keyId = self::KEY_ID): string
    {
        return self::sigFile([$this->entry($keyId, $payload)]);
    }

    /**
     * Canonical manifest document with the given overrides applied.
     *
     * Passing self::REMOVE as a value deletes that key, which is how the shape
     * tests build manifests that are missing a required field.
     *
     * @param array<string, mixed> $overrides Keys to replace or remove
     *
     * @return array<string, mixed>
     */
    public static function document(array $overrides = []): array
    {
        $document = [
            'schema'       => 1,
            'repo'         => self::REPO,
            'version'      => self::VERSION,
            'generated_at' => self::GENERATED_AT,
            'algo'         => 'sha256',
            'files'        => self::hashes(['app/Config/App.php' => "<?php\n// fixture\n"]),
        ];

        foreach ($overrides as $key => $value) {
            if ($value === self::REMOVE) {
                unset($document[$key]);
                continue;
            }

            $document[$key] = $value;
        }

        return $document;
    }

    /**
     * Serialises a manifest document exactly the way the release command does.
     *
     * @param array<string, mixed> $document Manifest document
     *
     * @return string Raw manifest bytes
     */
    public static function encode(array $document): string
    {
        if (isset($document['files']) && is_array($document['files'])) {
            ksort($document['files'], SORT_STRING);
        }

        return (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Raw bytes of a canonical manifest with the given overrides applied.
     *
     * @param array<string, mixed> $overrides Keys to replace or remove
     *
     * @return string
     */
    public static function manifest(array $overrides = []): string
    {
        return self::encode(self::document($overrides));
    }

    /**
     * Signature carrier document bytes.
     *
     * @param list<mixed>          $entries   Signature entries
     * @param array<string, mixed> $overrides Carrier level keys to replace
     *
     * @return string
     */
    public static function sigFile(array $entries, array $overrides = []): string
    {
        $document = ['schema' => 1, 'signatures' => $entries];

        foreach ($overrides as $key => $value) {
            $document[$key] = $value;
        }

        return (string) json_encode($document, JSON_UNESCAPED_SLASHES);
    }

    /**
     * SHA-256 hex hash map for a path => content list.
     *
     * @param array<string, string> $files Repository relative path => file bytes
     *
     * @return array<string, string> Path => sha256 hex
     */
    public static function hashes(array $files): array
    {
        $hashes = [];

        foreach ($files as $path => $content) {
            $hashes[$path] = hash('sha256', $content);
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    /**
     * Flips one byte of a string so the result stays the same length but differs.
     *
     * @param string $value    Original bytes
     * @param int    $position Byte offset to corrupt
     *
     * @return string
     */
    public static function flipByte(string $value, int $position): string
    {
        $value[$position] = $value[$position] === 'a' ? 'b' : 'a';

        return $value;
    }

    /**
     * Git blob SHA-1 of the given content, matching UpdateService::gitBlobSha().
     *
     * @param string $content File bytes
     *
     * @return string
     */
    public static function gitBlobSha(string $content): string
    {
        return sha1('blob ' . strlen($content) . "\0" . $content);
    }
}
