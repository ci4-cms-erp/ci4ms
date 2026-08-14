<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Modules\Settings\Commands\ReleaseManifest;
use Modules\Settings\Libraries\ManifestVerifier;
use Modules\Settings\Libraries\ReleaseSigner;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\Settings\ReleaseFixture;

/**
 * Offline signing keyfile and manifest generation tests.
 *
 * The keypair is generated at runtime inside a unique temporary directory outside
 * ROOTPATH and removed in tearDownAfterClass(); no key material, sealed or not,
 * ever lands in the repository. The manifest determinism test runs against a small
 * synthetic file tree instead of the real `git ls-files` output, so it stays fast
 * and independent of the working tree state.
 *
 * @internal
 */
final class ReleaseManifestTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    /** Passphrase sealing the throwaway keyfile. */
    private const PASSPHRASE = 'fixture-passphrase-2026';

    /** A passphrase that is long enough to pass validation but does not open the keyfile. */
    private const WRONG_PASSPHRASE = 'not-the-passphrase-2026';

    /** Key id written into the throwaway keyfile. */
    private const KEY_ID = 'ci4ms-qa-key';

    /** Absolute path of the per-class temporary directory. */
    private static string $tempDir = '';

    /** Absolute path of the sealed keyfile shared by the signing tests. */
    private static string $keyfile = '';

    /**
     * Creates the temporary directory and seals one throwaway keypair for the class.
     *
     * The keypair is generated once because argon2id sealing is deliberately slow;
     * every test that needs a signer reuses this file read-only.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $base = is_dir('/private/tmp') && is_writable('/private/tmp') ? '/private/tmp' : sys_get_temp_dir();

        self::$tempDir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ci4ms_release_qa_' . bin2hex(random_bytes(6));
        mkdir(self::$tempDir, 0700, true);

        self::$keyfile = self::$tempDir . DIRECTORY_SEPARATOR . 'release.key';

        (new ReleaseSigner(self::$keyfile))->generate(self::KEY_ID, self::PASSPHRASE);
    }

    /**
     * Removes every file this class created outside the project root.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$tempDir !== '' && is_dir(self::$tempDir)) {
            self::removeTree(self::$tempDir);
        }

        self::$tempDir = '';
        self::$keyfile = '';

        parent::tearDownAfterClass();
    }

    /**
     * A sealed keyfile round-trips: the signature it produces verifies against its own public key.
     *
     * @return void
     */
    public function testKeyfileRoundTripProducesVerifiableSignature(): void
    {
        $signer   = new ReleaseSigner(self::$keyfile);
        $manifest = ReleaseFixture::manifest();

        $public    = $signer->publicInfo();
        $signature = $signer->sign(self::PASSPHRASE, $manifest);

        $this->assertSame(self::KEY_ID, $public['key_id']);
        $this->assertSame($public['public_key'], $signature['public_key']);
        $this->assertSame(ManifestVerifier::fingerprint($public['public_key']), $signature['fingerprint']);
        $this->assertSame(SODIUM_CRYPTO_SIGN_BYTES, strlen((string) base64_decode($signature['signature'], true)));

        $verifier = new ManifestVerifier([
            self::KEY_ID => [
                'public_key'  => $public['public_key'],
                'status'      => 'active',
                'added'       => '2026-07-27',
                'fingerprint' => $public['fingerprint'],
            ],
        ]);

        $result = $verifier->verify(
            $manifest,
            ReleaseSigner::buildSignatureFile($signature['key_id'], $signature['signature'])
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(self::KEY_ID, $result['key_id']);
    }

    /**
     * A wrong passphrase fails and the error text carries no passphrase or key material.
     *
     * @return void
     */
    public function testWrongPassphraseFailsWithoutLeakingSecrets(): void
    {
        $envelope = json_decode((string) file_get_contents(self::$keyfile), true);
        $this->assertIsArray($envelope);

        try {
            (new ReleaseSigner(self::$keyfile))->sign(self::WRONG_PASSPHRASE, 'payload');
            $this->fail('Signing with the wrong passphrase must throw.');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            $this->assertSame('Keyfile could not be unsealed: wrong passphrase or corrupted file.', $message);
            $this->assertStringNotContainsString(self::PASSPHRASE, $message);
            $this->assertStringNotContainsString(self::WRONG_PASSPHRASE, $message);
            $this->assertStringNotContainsString((string) $envelope['box'], $message);
            $this->assertStringNotContainsString((string) $envelope['salt'], $message);
            $this->assertStringNotContainsString((string) $envelope['nonce'], $message);
        }
    }

    /**
     * A keyfile path resolving inside the project root is refused, a temporary path is accepted.
     *
     * @return void
     */
    public function testKeyfileInsideProjectRootIsRejected(): void
    {
        try {
            new ReleaseSigner(WRITEPATH . 'release.key');
            $this->fail('A keyfile under ROOTPATH must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('outside the project root', $e->getMessage());
        }

        $outside = new ReleaseSigner(self::$tempDir . DIRECTORY_SEPARATOR . 'elsewhere.key');

        $this->assertSame(self::$tempDir . DIRECTORY_SEPARATOR . 'elsewhere.key', $outside->path());
        $this->assertFileDoesNotExist($outside->path());
    }

    /**
     * The sealed keyfile is written with 0600 permissions.
     *
     * @return void
     */
    public function testKeyfileIsWrittenWithOwnerOnlyPermissions(): void
    {
        $this->assertFileExists(self::$keyfile);
        $this->assertSame(0600, fileperms(self::$keyfile) & 0777);
    }

    /**
     * Generating over an existing keyfile is refused instead of destroying the key.
     *
     * @return void
     */
    public function testGenerateRefusesToOverwriteAnExistingKeyfile(): void
    {
        $before = (string) file_get_contents(self::$keyfile);

        try {
            (new ReleaseSigner(self::$keyfile))->generate('another-key', self::PASSPHRASE);
            $this->fail('Overwriting an existing keyfile must throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('refusing to overwrite', $e->getMessage());
        }

        $this->assertSame($before, (string) file_get_contents(self::$keyfile));
    }

    /**
     * A symlinked keyfile path is refused instead of being followed past the root guard.
     *
     * realpath() only resolves the parent directory, so a dangling symlink sitting
     * outside the project could still land the private key inside public/ once
     * fopen() followed it.
     *
     * @return void
     */
    public function testSymlinkedKeyfilePathIsRejected(): void
    {
        $link = self::$tempDir . DIRECTORY_SEPARATOR . 'dangling.key';

        $this->assertTrue(symlink(FCPATH . 'qa-should-never-exist.key', $link), 'test needs a symlink');
        $this->assertFileDoesNotExist(FCPATH . 'qa-should-never-exist.key');

        try {
            new ReleaseSigner($link);
            $this->fail('A symlinked keyfile path must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('symlink', $e->getMessage());
        }

        $this->assertFileDoesNotExist(FCPATH . 'qa-should-never-exist.key', 'nothing may be created through the link');
    }

    /**
     * A case-flipped project root prefix does not smuggle a keyfile back inside the root.
     *
     * On a case-insensitive filesystem ".../WWW/project/public/x.key" is the same
     * directory as ".../www/project/public", but a case-sensitive str_starts_with()
     * comparison called it "outside".
     *
     * @return void
     */
    public function testCaseFlippedProjectRootPrefixIsRejected(): void
    {
        $root    = rtrim((string) realpath(ROOTPATH), DIRECTORY_SEPARATOR);
        $flipped = dirname($root) . DIRECTORY_SEPARATOR . strtoupper(basename($root));

        if ($flipped === $root || !is_dir($flipped)) {
            $this->markTestSkipped('Filesystem is case-sensitive, the bypass cannot be reproduced here.');
        }

        $path = $flipped . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'qa-case-flip.key';

        try {
            new ReleaseSigner($path);
            $this->fail('A case-flipped project root path must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('outside the project root', $e->getMessage());
        }

        $this->assertFileDoesNotExist($path);
    }

    /**
     * The keyfile write is exclusive: an existing file is never truncated or reused.
     *
     * touch()+chmod() left both a umask window and, on a failed write, a zero byte
     * keyfile that generate() then refused to overwrite forever.
     *
     * @return void
     */
    public function testKeyfileWriteIsExclusiveAndNeverTruncates(): void
    {
        $occupied = self::$tempDir . DIRECTORY_SEPARATOR . 'occupied.key';
        file_put_contents($occupied, 'pre-existing bytes');

        $signer = new ReleaseSigner($occupied);
        $method = new ReflectionMethod(ReleaseSigner::class, 'writeKeyfile');

        try {
            $method->invoke(
                $signer,
                'ci4ms-qa-exclusive',
                str_repeat("\x01", SODIUM_CRYPTO_SIGN_SECRETKEYBYTES),
                base64_encode(str_repeat("\x02", SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)),
                self::PASSPHRASE
            );
            $this->fail('Writing over an existing keyfile must throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('exclusively', $e->getMessage());
        }

        $this->assertSame('pre-existing bytes', (string) file_get_contents($occupied));
    }

    /**
     * Argon2id cost parameters read back from a keyfile are type checked and clamped.
     *
     * An absurd memlimit is an out-of-memory trigger, not a valid keyfile.
     *
     * @return void
     */
    public function testKeyfileCostParametersAreClampedAndTypeChecked(): void
    {
        $envelope = json_decode((string) file_get_contents(self::$keyfile), true);
        $this->assertIsArray($envelope);

        $absurd = $this->readEnvelope($envelope, ['opslimit' => 1000000, 'memlimit' => PHP_INT_MAX]);

        $this->assertSame(SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE, $absurd['opslimit']);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE, $absurd['memlimit']);

        $tiny = $this->readEnvelope($envelope, ['opslimit' => 0, 'memlimit' => 1]);

        $this->assertSame(SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, $tiny['opslimit']);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, $tiny['memlimit']);

        $untouched = $this->readEnvelope($envelope, []);

        $this->assertSame(SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, $untouched['opslimit']);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE, $untouched['memlimit']);

        try {
            $this->readEnvelope($envelope, ['memlimit' => '268435456']);
            $this->fail('A non-integer memlimit must throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('must be an integer', $e->getMessage());
        }
    }

    /**
     * The release command re-verifies its own output and rejects a signature that does not check out.
     *
     * @return void
     */
    public function testSelfCheckRejectsAPairThatDoesNotVerify(): void
    {
        $manifest  = ReleaseFixture::manifest();
        $signature = (new ReleaseSigner(self::$keyfile))->sign(self::PASSPHRASE, $manifest);
        $sigFile   = ReleaseSigner::buildSignatureFile($signature['key_id'], $signature['signature']);

        $corruptedSignature = base64_encode(ReleaseFixture::flipByte(
            (string) base64_decode($signature['signature'], true),
            5
        ));
        $corruptedSigFile = ReleaseSigner::buildSignatureFile($signature['key_id'], $corruptedSignature);

        $this->setUpStreamFilterTrait();

        try {
            $this->assertTrue($this->selfCheck($manifest, $sigFile, $signature), 'a genuine pair must self-check');
            $this->assertFalse($this->selfCheck($manifest, $corruptedSigFile, $signature), 'a corrupted signature must be caught');
            $this->assertFalse($this->selfCheck(ReleaseFixture::flipByte($manifest, 30), $sigFile, $signature), 'a manifest edited after signing must be caught');
            $this->assertFalse($this->selfCheck($manifest, '{ not json', $signature), 'a broken carrier must be caught');
            $this->assertStringContainsString('no_valid_signature', $this->getStreamFilterBuffer());
        } finally {
            $this->tearDownStreamFilterTrait();
        }
    }

    /**
     * Manifest generation is deterministic: input order never changes the signed bytes.
     *
     * The signature covers the serialised manifest, so any ordering instability
     * would make a release unverifiable on a different machine.
     *
     * @return void
     */
    public function testManifestGenerationIsDeterministic(): void
    {
        $root  = self::$tempDir . DIRECTORY_SEPARATOR . 'tree';
        $files = [
            'modules/Settings/Libraries/UpdateService.php' => "<?php\n// service\n",
            'app/Config/App.php'                           => "<?php\n// app\n",
            'README.md'                                    => "# ci4ms\n",
            'public/be-assets/js/app.js'                   => "console.log(1);\n",
        ];

        foreach ($files as $path => $content) {
            $absolute = $root . DIRECTORY_SEPARATOR . $path;
            if (!is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0700, true);
            }
            file_put_contents($absolute, $content);
        }

        $tracked = array_keys($files);
        $missing = 'modules/Settings/Deleted.php';

        $first  = $this->hashTree($root, [...$tracked, $missing]);
        $second = $this->hashTree($root, [...array_reverse($tracked), $missing]);

        $this->assertSame([$missing], $first[1], 'unreadable tracked paths must be skipped, not hashed');
        $this->assertSame($first[1], $second[1]);

        $firstManifest  = $this->encodeManifest($first[0]);
        $secondManifest = $this->encodeManifest($second[0]);

        $this->assertSame($firstManifest, $secondManifest, 'manifest bytes must not depend on git ls-files ordering');

        $signed = $this->decodedFiles($firstManifest);

        $this->assertSame(
            ['README.md', 'app/Config/App.php', 'modules/Settings/Libraries/UpdateService.php', 'public/be-assets/js/app.js'],
            array_keys($signed)
        );
        $this->assertStringContainsString('"app/Config/App.php"', $firstManifest, 'slashes must stay unescaped');
        $this->assertSame(hash('sha256', $files['README.md']), $signed['README.md'] ?? null);
    }

    /**
     * Decodes the files map out of serialised manifest bytes.
     *
     * @param string $manifest Raw manifest bytes
     *
     * @return array<array-key, mixed> Empty array when the manifest holds no files map
     */
    private function decodedFiles(string $manifest): array
    {
        $decoded = json_decode($manifest, true);
        $files   = is_array($decoded) ? ($decoded['files'] ?? null) : null;

        return is_array($files) ? $files : [];
    }

    /**
     * Runs the command's private hashing step against a synthetic tree.
     *
     * @param string       $root    Absolute tree root
     * @param list<string> $tracked Repository relative paths
     *
     * @return array{0: array<string, string>, 1: list<string>} Hash map plus skipped paths
     */
    private function hashTree(string $root, array $tracked): array
    {
        $command = (new ReflectionClass(ReleaseManifest::class))->newInstanceWithoutConstructor();
        $method  = new ReflectionMethod(ReleaseManifest::class, 'hashTrackedFiles');

        /** @var array{0: array<string, string>, 1: list<string>} $result */
        $result = $method->invoke($command, $root, $tracked);

        return $result;
    }

    /**
     * Runs the command's own serialisation step.
     *
     * Reimplementing the encoding here would make the determinism test agree with
     * itself instead of with the shipped command, so it is invoked directly.
     *
     * @param array<string, string> $hashes Path => sha256 hex
     *
     * @return string Raw manifest bytes
     */
    private function encodeManifest(array $hashes): string
    {
        $command = (new ReflectionClass(ReleaseManifest::class))->newInstanceWithoutConstructor();
        $method  = new ReflectionMethod(ReleaseManifest::class, 'encodeManifest');

        return (string) $method->invoke(
            $command,
            ReleaseFixture::REPO,
            ReleaseFixture::VERSION,
            ReleaseFixture::GENERATED_AT,
            $hashes
        );
    }

    /**
     * Runs the command's post-signing self-check.
     *
     * @param string                                                                            $manifest  Raw manifest bytes
     * @param string                                                                            $sigFile   Raw signature carrier bytes
     * @param array{key_id: string, public_key: string, fingerprint: string, signature: string} $signature Signing result
     *
     * @return bool
     */
    private function selfCheck(string $manifest, string $sigFile, array $signature): bool
    {
        $command = (new ReflectionClass(ReleaseManifest::class))->newInstanceWithoutConstructor();
        $method  = new ReflectionMethod(ReleaseManifest::class, 'selfCheck');

        return (bool) $method->invoke($command, $manifest, $sigFile, $signature);
    }

    /**
     * Reads a keyfile envelope back through the signer with the given fields overridden.
     *
     * @param array<string, mixed> $envelope  Envelope of the class keyfile
     * @param array<string, mixed> $overrides Fields to replace before reading
     *
     * @return array{key_id: string, public_key: string, created: string, opslimit: int, memlimit: int, salt: string, nonce: string, box: string}
     *
     * @throws RuntimeException When the envelope is refused
     */
    private function readEnvelope(array $envelope, array $overrides): array
    {
        $path = self::$tempDir . DIRECTORY_SEPARATOR . 'envelope_' . bin2hex(random_bytes(4)) . '.key';

        file_put_contents($path, (string) json_encode(array_merge($envelope, $overrides)));

        /** @var array{key_id: string, public_key: string, created: string, opslimit: int, memlimit: int, salt: string, nonce: string, box: string} $meta */
        $meta = (new ReflectionMethod(ReleaseSigner::class, 'readKeyfile'))->invoke(new ReleaseSigner($path));

        return $meta;
    }

    /**
     * Recursively deletes a directory this test class created.
     *
     * @param string $directory Absolute path inside the system temp directory
     *
     * @return void
     */
    private static function removeTree(string $directory): void
    {
        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? self::removeTree($path) : unlink($path);
        }

        rmdir($directory);
    }
}
