<?php

declare(strict_types=1);

namespace Modules\Settings\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Trusted Ed25519 release signing keys for the auto updater.
 *
 * Ships empty on purpose: an empty keyring means no release manifest can ever be
 * verified, so the updater fails closed until the publisher pastes the public key
 * emitted by `php spark ci4ms:release:keygen` into $keys below.
 *
 * Never store a private key here — only the base64 public key.
 *
 * Entry contract (array key is the key_id used inside manifest.json.sig):
 *   'public_key'  base64 encoded 32-byte Ed25519 public key
 *   'status'      'active' (usable) or 'revoked' (poisons the whole manifest)
 *   'added'       ISO-8601 date the key entered the keyring
 *   'fingerprint' hex sha256 of the raw public key, for out-of-band comparison
 *
 * A signature entry pointing at a 'revoked' key rejects the entire manifest even
 * when another valid signature is present. Unknown key_ids are ignored so a
 * publisher can dual-sign during a key rotation window.
 */
class UpdateKeys extends BaseConfig
{
    /**
     * @var array<string, array{public_key: string, status: string, added: string, fingerprint: string}>
     */
    public array $keys = [];
}
