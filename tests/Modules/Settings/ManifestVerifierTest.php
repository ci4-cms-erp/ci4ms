<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Settings\Libraries\ManifestVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Settings\ReleaseFixture;

/**
 * Ed25519 release manifest verification core tests.
 *
 * Pure and offline: no database, no network, no cache. Both keypairs are generated
 * at runtime in setUpBeforeClass() so no private key material exists in the
 * repository. Every assertion here pins one rejection code, because the caller
 * maps those codes to distinct user messages and log levels.
 *
 * @internal
 */
final class ManifestVerifierTest extends CIUnitTestCase
{
    /** Version the app pretends to be running while the fixtures target 0.35.0.0. */
    private const CURRENT_VERSION = '0.34.0.0';

    /** A key id that is deliberately absent from every keyring under test. */
    private const UNKNOWN_KEY_ID = 'ci4ms-9999-z';

    /** Repository slug no fixture manifest is ever bound to. */
    private const FOREIGN_REPO = 'attacker/ci4ms';

    /** Publisher keypair that signs the happy path fixtures. */
    private static ReleaseFixture $publisher;

    /** Second keypair used for rotation, dual-signing and revocation scenarios. */
    private static ReleaseFixture $rotated;

    /**
     * Generates the throwaway keypairs shared by every test in this class.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$publisher = ReleaseFixture::newKeypair();
        self::$rotated   = ReleaseFixture::newKeypair();
    }

    /**
     * A manifest signed by an active trusted key is accepted and decoded.
     *
     * @return void
     */
    public function testValidSignatureFromActiveKeyIsAccepted(): void
    {
        $manifest = ReleaseFixture::manifest();
        $result   = $this->verifier()->verify($manifest, self::$publisher->signatureFile($manifest));

        $this->assertTrue($result['ok']);
        $this->assertSame('ok', $result['code']);
        $this->assertSame(ReleaseFixture::KEY_ID, $result['key_id']);
        $this->assertIsArray($result['manifest']);
        $this->assertSame(ReleaseFixture::VERSION, $result['manifest']['version']);
        $this->assertNotSame([], $result['manifest']['files']);
    }

    /**
     * Corrupting a single manifest byte invalidates the signature.
     *
     * @return void
     */
    public function testTamperedManifestByteFailsSignature(): void
    {
        $manifest = ReleaseFixture::manifest();
        $sigFile  = self::$publisher->signatureFile($manifest);

        $result = $this->verifier()->verify(ReleaseFixture::flipByte($manifest, 30), $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('no_valid_signature', $result['code']);
        $this->assertNull($result['manifest']);
    }

    /**
     * Re-serialising the manifest breaks the signature, proving the raw-byte rule is load bearing.
     *
     * json_encode() without JSON_UNESCAPED_SLASHES rewrites every "/" in the file
     * paths, so a verifier that decoded first and re-encoded later would either
     * fail every release or, worse, verify bytes nobody signed.
     *
     * @return void
     */
    public function testReserialisedManifestFailsSignature(): void
    {
        $manifest = ReleaseFixture::manifest();
        $sigFile  = self::$publisher->signatureFile($manifest);

        $reserialised = (string) json_encode(json_decode($manifest, true));

        $this->assertNotSame($manifest, $reserialised);
        $this->assertSame(json_decode($manifest, true), json_decode($reserialised, true));

        $result = $this->verifier()->verify($reserialised, $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('no_valid_signature', $result['code']);
    }

    /**
     * Corrupting a single signature byte invalidates the signature.
     *
     * @return void
     */
    public function testTamperedSignatureByteFailsSignature(): void
    {
        $manifest  = ReleaseFixture::manifest();
        $signature = (string) base64_decode(self::$publisher->sign($manifest), true);
        $signature[10] = $signature[10] === "\x00" ? "\x01" : "\x00";

        $sigFile = ReleaseFixture::sigFile([
            ['key_id' => ReleaseFixture::KEY_ID, 'alg' => 'ed25519', 'sig' => base64_encode($signature)],
        ]);

        $result = $this->verifier()->verify($manifest, $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('no_valid_signature', $result['code']);
    }

    /**
     * A revoked key poisons the whole manifest even when a valid active signature is also present.
     *
     * @return void
     */
    public function testRevokedKeyPoisonsManifestDespiteValidActiveSignature(): void
    {
        $manifest = ReleaseFixture::manifest();
        $sigFile  = ReleaseFixture::sigFile([
            self::$rotated->entry(ReleaseFixture::SECOND_KEY_ID, $manifest),
            self::$publisher->entry(ReleaseFixture::KEY_ID, $manifest),
        ]);

        $verifier = new ManifestVerifier([
            ReleaseFixture::KEY_ID        => self::$publisher->keyringEntry('active'),
            ReleaseFixture::SECOND_KEY_ID => self::$rotated->keyringEntry('revoked'),
        ]);

        $result = $verifier->verify($manifest, $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('revoked_key', $result['code']);
        $this->assertNull($result['key_id']);
    }

    /**
     * An empty keyring fails closed with no_trusted_keys.
     *
     * @return void
     */
    public function testEmptyKeyringYieldsNoTrustedKeys(): void
    {
        $manifest = ReleaseFixture::manifest();

        $result = (new ManifestVerifier([]))->verify($manifest, self::$publisher->signatureFile($manifest));

        $this->assertFalse($result['ok']);
        $this->assertSame('no_trusted_keys', $result['code']);
    }

    /**
     * A keyring holding only the revoked signer reports revocation, not a missing keyring.
     *
     * Revocation is a local fact and outranks the empty-active-set diagnosis on
     * purpose (ManifestVerifier::verify(), the loop above activePublicKeys()).
     *
     * @return void
     */
    public function testKeyringHoldingOnlyRevokedKeyYieldsRevokedKey(): void
    {
        $manifest = ReleaseFixture::manifest();
        $verifier = new ManifestVerifier([
            ReleaseFixture::KEY_ID => self::$publisher->keyringEntry('revoked'),
        ]);

        $result = $verifier->verify($manifest, self::$publisher->signatureFile($manifest));

        $this->assertFalse($result['ok']);
        $this->assertSame('revoked_key', $result['code']);
    }

    /**
     * The same key_id listed twice is rejected before any signature is checked.
     *
     * @return void
     */
    public function testDuplicateKeyIdIsRejected(): void
    {
        $manifest  = ReleaseFixture::manifest();
        $signature = self::$publisher->sign($manifest);

        $sigFile = ReleaseFixture::sigFile([
            ['key_id' => ReleaseFixture::KEY_ID, 'alg' => 'ed25519', 'sig' => $signature],
            ['key_id' => ReleaseFixture::KEY_ID, 'alg' => 'ed25519', 'sig' => $signature],
        ]);

        $result = $this->verifier()->verify($manifest, $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('duplicate_key_id', $result['code']);
    }

    /**
     * An unknown key_id is skipped silently so a publisher can dual-sign while rotating.
     *
     * @return void
     */
    public function testUnknownKeyIdIsSkippedWhenActiveSignaturePresent(): void
    {
        $manifest = ReleaseFixture::manifest();
        $sigFile  = ReleaseFixture::sigFile([
            self::$rotated->entry(self::UNKNOWN_KEY_ID, $manifest),
            self::$publisher->entry(ReleaseFixture::KEY_ID, $manifest),
        ]);

        $result = $this->verifier()->verify($manifest, $sigFile);

        $this->assertTrue($result['ok']);
        $this->assertSame(ReleaseFixture::KEY_ID, $result['key_id']);
    }

    /**
     * A carrier holding only unknown key_ids leaves the manifest unsigned.
     *
     * @return void
     */
    public function testOnlyUnknownKeyIdYieldsNoValidSignature(): void
    {
        $manifest = ReleaseFixture::manifest();
        $sigFile  = ReleaseFixture::sigFile([self::$rotated->entry(self::UNKNOWN_KEY_ID, $manifest)]);

        $result = $this->verifier()->verify($manifest, $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('no_valid_signature', $result['code']);
    }

    /**
     * An empty signatures list is a malformed carrier, never an implicit pass.
     *
     * @return void
     */
    public function testEmptySignatureListIsBadSigFormat(): void
    {
        $result = $this->verifier()->verify(ReleaseFixture::manifest(), ReleaseFixture::sigFile([]));

        $this->assertFalse($result['ok']);
        $this->assertSame('bad_sig_format', $result['code']);
    }

    /**
     * Structurally broken signature carriers are rejected before any key lookup.
     *
     * @param string $sigFile Raw manifest.json.sig bytes
     *
     * @return void
     */
    #[DataProvider('malformedSignatureCarriers')]
    public function testMalformedSignatureCarrierIsBadSigFormat(string $sigFile): void
    {
        $result = $this->verifier()->verify(ReleaseFixture::manifest(), $sigFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('bad_sig_format', $result['code']);
    }

    /**
     * Malformed signature carriers, keyed by what is wrong with them.
     *
     * The placeholder signature is a correctly sized zero buffer: carrier parsing
     * runs entirely before verification, so no real key is needed here.
     *
     * @return array<string, array{0: string}>
     */
    public static function malformedSignatureCarriers(): array
    {
        $placeholder = base64_encode(str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES));

        return [
            'not json'            => ['{ this is not json'],
            'unsupported schema'  => [ReleaseFixture::sigFile([['key_id' => 'k', 'alg' => 'ed25519', 'sig' => $placeholder]], ['schema' => 2])],
            'rsa algorithm'       => [ReleaseFixture::sigFile([['key_id' => 'k', 'alg' => 'rsa', 'sig' => $placeholder]])],
            'missing algorithm'   => [ReleaseFixture::sigFile([['key_id' => 'k', 'sig' => $placeholder]])],
            'empty key id'        => [ReleaseFixture::sigFile([['key_id' => '', 'alg' => 'ed25519', 'sig' => $placeholder]])],
            'signature not base64' => [ReleaseFixture::sigFile([['key_id' => 'k', 'alg' => 'ed25519', 'sig' => '!!! not base64 !!!']])],
            'signature too short' => [ReleaseFixture::sigFile([['key_id' => 'k', 'alg' => 'ed25519', 'sig' => base64_encode('short')]])],
            'signatures not a list' => ['{"schema":1,"signatures":{"a":{"key_id":"k","alg":"ed25519","sig":"' . $placeholder . '"}}}'],
        ];
    }

    /**
     * A correctly signed payload that is not decodable JSON is reported as bad_manifest_json.
     *
     * @return void
     */
    public function testUndecodableManifestWithValidSignatureIsBadManifestJson(): void
    {
        $truncated = '{"schema":1,"repo":"ci4-cms-erp/ci4ms",';

        $result = $this->verifier()->verify($truncated, self::$publisher->signatureFile($truncated));

        $this->assertFalse($result['ok']);
        $this->assertSame('bad_manifest_json', $result['code']);
    }

    /**
     * Schema contract violations are rejected even when the signature is genuine.
     *
     * @param array<string, mixed> $overrides Manifest keys to replace or remove
     *
     * @return void
     */
    #[DataProvider('shapeViolations')]
    public function testShapeViolationIsBadManifestShape(array $overrides): void
    {
        $manifest = ReleaseFixture::manifest($overrides);

        $result = $this->verifier()->verify($manifest, self::$publisher->signatureFile($manifest));

        $this->assertFalse($result['ok']);
        $this->assertSame('bad_manifest_shape', $result['code']);
    }

    /**
     * Manifest documents that violate the schema contract, keyed by the violated rule.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function shapeViolations(): array
    {
        $validHash = str_repeat('a', 64);

        return [
            'future schema'    => [['schema' => 2]],
            'weaker algo'      => [['algo' => 'sha1']],
            'missing repo'     => [['repo' => ReleaseFixture::REMOVE]],
            'empty version'    => [['version' => '']],
            'missing date'     => [['generated_at' => ReleaseFixture::REMOVE]],
            'empty files'      => [['files' => []]],
            'hash one short'   => [['files' => ['app/Config/App.php' => substr($validHash, 0, 63)]]],
            'hash not hex'     => [['files' => ['app/Config/App.php' => str_repeat('z', 64)]]],
            'hash not string'  => [['files' => ['app/Config/App.php' => 12345]]],
            // The version lands in .env as a preg_replace replacement, so anything
            // beyond digits and dots is refused before it can be written.
            'version with html'          => [['version' => '1.2.3<script>']],
            'version as backreference'   => [['version' => '$1']],
            'version with escaped group' => [['version' => '\\1']],
            'version with newline'       => [['version' => "1.2.3\napp.key=x"]],
            'version with quote'         => [['version' => "1.2.3' app.key='x"]],
            'version too short'          => [['version' => '1']],
            'version not numeric'        => [['version' => 'v1.2.3']],
        ];
    }

    /**
     * A malformed keyring entry is ignored instead of being read as an array.
     *
     * The keyring is publisher-edited PHP, so a scalar left in place must not turn
     * into a string offset read while the revocation loop scans it.
     *
     * @return void
     */
    public function testMalformedKeyringEntriesAreIgnoredSafely(): void
    {
        $manifest = ReleaseFixture::manifest();
        $sigFile  = self::$publisher->signatureFile($manifest);

        $scalarOnly = (new ManifestVerifier([ReleaseFixture::KEY_ID => 'status']))->verify($manifest, $sigFile);

        $this->assertFalse($scalarOnly['ok']);
        $this->assertSame('no_trusted_keys', $scalarOnly['code']);

        $mixed = (new ManifestVerifier([
            ReleaseFixture::KEY_ID        => 12345,
            ReleaseFixture::SECOND_KEY_ID => self::$rotated->keyringEntry('active'),
        ]))->verify($manifest, $sigFile);

        $this->assertFalse($mixed['ok']);
        $this->assertSame('no_valid_signature', $mixed['code']);

        $listEntry = (new ManifestVerifier([
            ReleaseFixture::KEY_ID => ['revoked'],
        ]))->verify($manifest, $sigFile);

        $this->assertFalse($listEntry['ok']);
        $this->assertSame('no_trusted_keys', $listEntry['code']);
    }

    /**
     * A well-formed version passes the same shape check the hostile ones fail.
     *
     * Negative control for the version rules above: without this, tightening the
     * pattern until nothing verifies would look like a passing test suite.
     *
     * @return void
     */
    public function testAcceptedVersionShapesStillVerify(): void
    {
        foreach (['0.35.0.0', '1.2.3', '10.0'] as $version) {
            $manifest = ReleaseFixture::manifest(['version' => $version]);
            $result   = $this->verifier()->verify($manifest, self::$publisher->signatureFile($manifest));

            $this->assertTrue($result['ok'], $version . ' must be an acceptable manifest version');
            $this->assertSame($version, $result['manifest']['version']);
        }
    }

    /**
     * A manifest bound to another repository is refused.
     *
     * @return void
     */
    public function testCheckBindingRejectsRepoMismatch(): void
    {
        $binding = $this->verifier()->checkBinding(
            ReleaseFixture::document(),
            self::FOREIGN_REPO,
            ReleaseFixture::VERSION,
            self::CURRENT_VERSION
        );

        $this->assertFalse($binding['ok']);
        $this->assertSame('repo_mismatch', $binding['code']);
    }

    /**
     * A manifest whose version is not the requested tag is refused.
     *
     * @return void
     */
    public function testCheckBindingRejectsVersionMismatch(): void
    {
        $binding = $this->verifier()->checkBinding(
            ReleaseFixture::document(),
            ReleaseFixture::REPO,
            '0.36.0.0',
            self::CURRENT_VERSION
        );

        $this->assertFalse($binding['ok']);
        $this->assertSame('version_mismatch', $binding['code']);
    }

    /**
     * Installing an older or identical version through the updater is refused as a downgrade.
     *
     * @return void
     */
    public function testCheckBindingRejectsDowngradeAndEqualVersion(): void
    {
        $verifier = $this->verifier();

        $older = $verifier->checkBinding(
            ReleaseFixture::document(['version' => '0.33.0.0']),
            ReleaseFixture::REPO,
            '0.33.0.0',
            self::CURRENT_VERSION
        );

        $same = $verifier->checkBinding(
            ReleaseFixture::document(['version' => self::CURRENT_VERSION]),
            ReleaseFixture::REPO,
            self::CURRENT_VERSION,
            self::CURRENT_VERSION
        );

        $this->assertSame('downgrade', $older['code']);
        $this->assertSame('downgrade', $same['code']);
        $this->assertFalse($older['ok']);
        $this->assertFalse($same['ok']);
    }

    /**
     * A genuinely signed but stale manifest is stopped by the binding, not by the signature.
     *
     * Replaying an old release is the attack the binding exists for: the signature
     * stays valid forever, so freshness has to be decided outside the crypto.
     *
     * @return void
     */
    public function testReplayedOlderManifestIsRejectedByBinding(): void
    {
        $stale    = ReleaseFixture::manifest(['version' => '0.30.0.0']);
        $verifier = $this->verifier();

        $verified = $verifier->verify($stale, self::$publisher->signatureFile($stale));
        $this->assertTrue($verified['ok']);

        $whileRequestingNewTag = $verifier->checkBinding((array) $verified['manifest'], ReleaseFixture::REPO, ReleaseFixture::VERSION, self::CURRENT_VERSION);
        $whileAlreadyNewer     = $verifier->checkBinding((array) $verified['manifest'], ReleaseFixture::REPO, '0.30.0.0', self::CURRENT_VERSION);

        $this->assertSame('version_mismatch', $whileRequestingNewTag['code']);
        $this->assertSame('downgrade', $whileAlreadyNewer['code']);
    }

    /**
     * A forward upgrade binds cleanly.
     *
     * @return void
     */
    public function testCheckBindingAcceptsForwardUpgrade(): void
    {
        $binding = $this->verifier()->checkBinding(
            ReleaseFixture::document(),
            ReleaseFixture::REPO,
            ReleaseFixture::VERSION,
            self::CURRENT_VERSION
        );

        $this->assertTrue($binding['ok']);
        $this->assertSame('ok', $binding['code']);
    }

    /**
     * verifyFile() accepts only byte-exact signed content and never falls back on unknown paths.
     *
     * @return void
     */
    public function testVerifyFileAcceptsOnlySignedContent(): void
    {
        $content  = "<?php\n// fixture\n";
        $document = ReleaseFixture::document(['files' => ReleaseFixture::hashes(['app/Config/App.php' => $content])]);
        $verifier = $this->verifier();

        $this->assertTrue($verifier->verifyFile($document, 'app/Config/App.php', $content));
        $this->assertFalse($verifier->verifyFile($document, 'app/Config/App.php', ReleaseFixture::flipByte($content, 2)));
        $this->assertFalse($verifier->verifyFile($document, 'app/Config/Absent.php', $content));
    }

    /**
     * fileHash() returns null for a path the manifest does not cover.
     *
     * @return void
     */
    public function testFileHashReturnsNullForUnknownPath(): void
    {
        $document = ReleaseFixture::document();

        $this->assertNull($this->verifier()->fileHash($document, 'modules/Settings/Not/Signed.php'));
        $this->assertSame(64, strlen((string) $this->verifier()->fileHash($document, 'app/Config/App.php')));
    }

    /**
     * fingerprint() is a deterministic hex sha256 of the raw key and empty for malformed input.
     *
     * @return void
     */
    public function testFingerprintIsDeterministicAndRejectsMalformedKeys(): void
    {
        $publicKey = self::$publisher->publicKey();
        $expected  = hash('sha256', (string) base64_decode($publicKey, true));

        $this->assertSame($expected, ManifestVerifier::fingerprint($publicKey));
        $this->assertSame($expected, ManifestVerifier::fingerprint($publicKey));
        $this->assertSame(64, strlen(ManifestVerifier::fingerprint($publicKey)));
        $this->assertSame('', ManifestVerifier::fingerprint('not base64 at all'));
        $this->assertSame('', ManifestVerifier::fingerprint(base64_encode('too short')));
    }

    /**
     * Verifier trusting only the active publisher key.
     *
     * @return ManifestVerifier
     */
    private function verifier(): ManifestVerifier
    {
        return new ManifestVerifier([ReleaseFixture::KEY_ID => self::$publisher->keyringEntry('active')]);
    }
}
