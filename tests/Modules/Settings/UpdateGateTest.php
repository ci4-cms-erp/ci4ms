<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\TestLogger;
use Modules\Settings\Config\UpdateKeys;
use Modules\Settings\Libraries\ManifestVerifier;
use Modules\Settings\Libraries\UpdateService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\Settings\ReleaseFixture;
use Tests\Support\Settings\StubCurlRequest;

/**
 * Signed manifest gate tests for the auto updater.
 *
 * Fully offline: every HTTP call goes through StubCurlRequest, which records the
 * URL and the options array instead of opening a socket. The trusted keyring is
 * swapped in with Factories::injectMock() exactly like the production code reads
 * it, so nothing here depends on what ships in Modules\Settings\Config\UpdateKeys.
 *
 * Nothing in this class is allowed to write under ROOTPATH: the applyUpdate() test
 * only exercises rejection paths and asserts the filesystem stayed untouched.
 *
 * @internal
 */
final class UpdateGateTest extends CIUnitTestCase
{
    /** Version the fake .env reports while the fixture release is 0.35.0.0. */
    private const CURRENT_VERSION = '0.34.0.0';

    /** Version the fixture release publishes. */
    private const NEW_VERSION = '0.35.0.0';

    /** GitHub release lookup endpoint UpdateService calls first. */
    private const RELEASE_URL = 'https://api.github.com/repos/ci4-cms-erp/ci4ms/releases/latest';

    /** GitHub compare endpoint used to build the changed-file list. */
    private const COMPARE_URL = 'https://api.github.com/repos/ci4-cms-erp/ci4ms/compare/0.34.0.0...0.35.0.0';

    /** browser_download_url of the manifest asset. */
    private const MANIFEST_URL = 'https://objects.example.test/releases/manifest.json';

    /** browser_download_url of the signature asset. */
    private const SIGNATURE_URL = 'https://objects.example.test/releases/manifest.json.sig';

    /** Host that must never be contacted while the gate is closed. */
    private const RAW_HOST = 'raw.githubusercontent.com';

    /** Repository relative path of the single changed file in the fixture release. */
    private const CHANGED_PATH = 'modules/Settings/Libraries/UpdateService.php';

    /** Content the fixture release publishes for CHANGED_PATH. */
    private const CHANGED_BODY = "<?php\n// signed release body\n";

    /** Harmless throwaway path used to prove applyUpdate() never writes on rejection. */
    private const PROBE_PATH = 'writable/tmp/qa_signed_content_probe.txt';

    /** Second signed path used by the selective suppression scenario. */
    private const SECOND_PATH = 'app/Config/Security.php';

    /** Content the fixture release publishes for SECOND_PATH. */
    private const SECOND_BODY = "<?php\n// signed security config\n";

    /** Publisher keypair generated once per class run. */
    private static ReleaseFixture $publisher;

    /** Value of $_ENV['app.version'] before this test overwrote it, kept verbatim for restoration. */
    private mixed $versionBackup = null;

    /** Whether $_ENV['app.version'] existed before this test overwrote it. */
    private bool $versionExisted = false;

    /**
     * Generates the throwaway publisher keypair shared by every test in this class.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$publisher = ReleaseFixture::newKeypair();
    }

    /**
     * Pins the reported app version and empties the process-wide log buffer.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->versionExisted = array_key_exists('app.version', $_ENV);
        $this->versionBackup  = $_ENV['app.version'] ?? null;
        $_ENV['app.version']  = self::CURRENT_VERSION;

        $this->clearLoggedMessages();
    }

    /**
     * Restores the environment and drops the injected keyring.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->versionExisted) {
            $_ENV['app.version'] = $this->versionBackup;
        } else {
            unset($_ENV['app.version']);
        }

        Factories::reset('config');

        parent::tearDown();
    }

    /**
     * A release that publishes no manifest asset aborts before a single file is fetched.
     *
     * @return void
     */
    public function testMissingManifestAssetAbortsBeforeAnyDownload(): void
    {
        $stub = $this->stubWithRelease($this->releasePayload([]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('manifest_unreachable', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('warning'));
        $this->assertSame([], $this->loggedAt('critical'));
    }

    /**
     * An HTTP error on the manifest asset is a transport failure, not a signature failure.
     *
     * @return void
     */
    public function testManifestAssetHttpErrorIsUnreachable(): void
    {
        $manifest = $this->signedManifest();
        $stub     = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 500, 'gateway exploded');
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('manifest_unreachable', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('warning'));
    }

    /**
     * A 200 response with an empty body is treated as an unreachable manifest.
     *
     * A CDN redirect that is not followed returns exactly this, which is why the
     * empty body must never be handed to the verifier as "valid but unsigned".
     *
     * @return void
     */
    public function testEmptyManifestAssetBodyIsUnreachable(): void
    {
        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, '');
        $stub->on(self::SIGNATURE_URL, 200, '');
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('manifest_unreachable', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
    }

    /**
     * A transport exception during the release lookup is reported as unreachable, not invalid.
     *
     * @return void
     */
    public function testReleaseLookupTransportFailureIsUnreachable(): void
    {
        $stub = new StubCurlRequest();
        $stub->throwOn('api.github.com', 'cURL error 6: could not resolve host');
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('manifest_unreachable', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('warning'));
        $this->assertSame([], $this->loggedAt('critical'));
    }

    /**
     * A tampered manifest is a critical signature failure, distinct in code and log level from a network failure.
     *
     * @return void
     */
    public function testInvalidSignatureIsCriticalAndDistinctFromNetworkFailure(): void
    {
        $manifest = $this->signedManifest();
        $stub     = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, ReleaseFixture::flipByte($manifest, 40));
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('no_valid_signature', $result['code']);
        $this->assertSame(lang('Settings.updateSignatureInvalid'), $result['message']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('critical'));
        $this->assertSame([], $this->loggedAt('warning'));

        $this->assertNotSame('manifest_unreachable', $result['code']);
        $this->assertNotSame(lang('Settings.updateManifestUnreachable'), $result['message']);
    }

    /**
     * A latest release whose tag is not the requested version aborts with zero downloads.
     *
     * @return void
     */
    public function testTagMismatchAbortsWithZeroDownloads(): void
    {
        $stub = $this->stubWithRelease($this->releasePayload($this->assetList(), 'v0.99.0.0'));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('tag_mismatch', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertSame([], $stub->callsMatching('objects.example.test'));
        $this->assertNotSame([], $this->loggedAt('critical'));
    }

    /**
     * The shipped empty keyring fails closed: nothing verifies, nothing downloads.
     *
     * @return void
     */
    public function testShippedEmptyKeyringAbortsWithNoTrustedKeys(): void
    {
        $manifest = $this->signedManifest();
        $stub     = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $this->injectKeyring([]);

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('no_trusted_keys', $result['code']);
        $this->assertSame(lang('Settings.updateNoTrustedKeys'), $result['message']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('critical'));
    }

    /**
     * Both asset requests follow redirects over https only and decode content.
     *
     * Without allow_redirects the CDN 302 comes back as an empty body and without
     * decode_content curl hands over raw gzip bytes; either one silently destroys
     * the signature. Leaving allow_redirects at `true` accepted CI4's default
     * ['http','https'] protocol set, which let a hostile 302 downgrade the asset
     * fetch to plaintext, so the policy shape is pinned here as well.
     *
     * @return void
     */
    public function testAssetRequestsFollowHttpsRedirectsAndDecodeContent(): void
    {
        $manifest = $this->signedManifest();
        $stub     = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $this->trustPublisher();

        (new UpdateService($stub))->fetchManifest(self::NEW_VERSION);

        foreach ([self::MANIFEST_URL, self::SIGNATURE_URL] as $url) {
            $options  = $stub->optionsFor($url);
            $redirect = $options['allow_redirects'] ?? null;

            $this->assertIsArray($redirect, $url . ' must follow redirects under an explicit policy');
            $this->assertSame(['https'], $redirect['protocols'], $url . ' must refuse a plaintext redirect hop');
            $this->assertLessThanOrEqual(3, $redirect['max'], $url . ' must bound the redirect chain');
            $this->assertTrue($options['decode_content'] ?? null, $url . ' must decode content');
            $this->assertArrayNotHasKey('Authorization', (array) ($options['headers'] ?? []));
        }

        // Negative control: the API call carries neither flag, so the assertions
        // above are reading the recorded options and not a stub default.
        $apiOptions = $stub->optionsFor(self::RELEASE_URL);
        $this->assertArrayNotHasKey('allow_redirects', $apiOptions);
        $this->assertArrayNotHasKey('decode_content', $apiOptions);
    }

    /**
     * A correctly signed release opens the gate and carries the signed hash map forward.
     *
     * @return void
     */
    public function testValidManifestOpensTheGate(): void
    {
        $stub = $this->happyPathStub(self::CHANGED_BODY);
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertTrue($result['result']);
        $this->assertSame([], $result['failed']);
        $this->assertSame([self::CHANGED_PATH], array_keys($result['files']));
        $this->assertSame(self::CHANGED_BODY, $result['files'][self::CHANGED_PATH]);
        $this->assertNotSame([], $result['signed_hashes']);
        $this->assertSame(hash('sha256', self::CHANGED_BODY), $result['signed_hashes'][self::CHANGED_PATH]);
        $this->assertSame(ReleaseFixture::KEY_ID, $result['key_id']);
        $this->assertSame(
            ManifestVerifier::fingerprint(self::$publisher->publicKey()),
            $result['fingerprint']
        );
    }

    /**
     * A changed file absent from the signed manifest aborts the whole update.
     *
     * Dropping a file from the manifest while keeping it in the diff is the
     * manifest-exclusion attack; there is no partial apply and no per-file skip.
     *
     * @return void
     */
    public function testFileMissingFromManifestAbortsWholeUpdate(): void
    {
        $unsignedPath = 'modules/Settings/Libraries/Injected.php';
        $manifest     = $this->signedManifest();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => $unsignedPath, 'status' => 'added', 'sha' => ReleaseFixture::gitBlobSha('anything')],
        ]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('file_not_in_manifest', $result['code']);
        $this->assertArrayNotHasKey('files', $result);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('critical'));
    }

    /**
     * Content tampered consistently with the git blob SHA-1 prefilter is still caught by the signed SHA-256.
     *
     * The compare endpoint's sha is attacker-influenced metadata, so it can only
     * ever be an unauthenticated prefilter; the manifest hash is the real gate.
     *
     * @return void
     */
    public function testTamperedContentWithMatchingBlobShaIsCaughtBySha256(): void
    {
        $tampered = "<?php\n// backdoor\n";

        $stub = $this->happyPathStub($tampered, ReleaseFixture::gitBlobSha($tampered));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('file_hash_mismatch', $result['code']);
        $this->assertArrayNotHasKey('files', $result);
        $this->assertNotSame([], $this->loggedAt('critical'));
        $this->assertSame(1, count($stub->callsMatching(self::RAW_HOST)));
    }

    /**
     * applyUpdate() refuses every unsigned hash map and leaves the filesystem untouched.
     *
     * @return void
     */
    public function testApplyUpdateRejectsUnsignedContentWithoutTouchingDisk(): void
    {
        $this->trustPublisher();

        $content    = "probe content\n";
        $lockFile   = WRITEPATH . 'ci4ms_update.lock';
        $probeFile  = ROOTPATH . self::PROBE_PATH;
        $backupsBefore = $this->backupDirectories();

        $service = new UpdateService(new StubCurlRequest());

        $rejections = [
            'empty hash map'   => $service->applyUpdate(self::NEW_VERSION, [self::PROBE_PATH => $content], [], []),
            'wrong hash'       => $service->applyUpdate(self::NEW_VERSION, [self::PROBE_PATH => $content], [], [self::PROBE_PATH => str_repeat('0', 64)]),
            'unlisted path'    => $service->applyUpdate(self::NEW_VERSION, [self::PROBE_PATH => $content], [], ['some/other/file.php' => hash('sha256', $content)]),
        ];

        foreach ($rejections as $case => $result) {
            $this->assertFalse($result['result'], $case . ' must be rejected');
            $this->assertSame(lang('Settings.updateSignatureInvalid'), $result['message'], $case . ' must report a signature failure');
        }

        $this->assertSame($backupsBefore, $this->backupDirectories(), 'applyUpdate() must not create a backup directory when it rejects');
        $this->assertFileDoesNotExist($lockFile, 'applyUpdate() must not leave an update lock behind');
        $this->assertFileDoesNotExist($probeFile, 'applyUpdate() must not write the probe file');
        $this->assertFileDoesNotExist($probeFile . '.update_tmp', 'applyUpdate() must not leave a temp file behind');
        $this->assertNotSame([], $this->loggedAt('critical'));
    }

    /**
     * checkVersion() keeps every legacy response key and adds the signed flag.
     *
     * @return void
     */
    public function testCheckVersionKeepsLegacyResponseKeys(): void
    {
        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => self::CHANGED_PATH, 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha(self::CHANGED_BODY)],
        ]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->checkVersion();

        foreach ([
            'result', 'update_available', 'latest_version', 'new_version', 'current_version',
            'release_notes', 'changed_files', 'changed_count', 'compare_url', 'download_url',
        ] as $key) {
            $this->assertArrayHasKey($key, $result, $key . ' is part of the legacy contract');
        }

        $this->assertTrue($result['result']);
        $this->assertTrue($result['update_available']);
        $this->assertSame(self::NEW_VERSION, $result['latest_version']);
        $this->assertSame(self::NEW_VERSION, $result['new_version']);
        $this->assertSame(self::CURRENT_VERSION, $result['current_version']);
        $this->assertSame(1, $result['changed_count']);
        $this->assertTrue($result['signed']);
    }

    /**
     * checkVersion() reports an unsigned release when the manifest assets are missing.
     *
     * @return void
     */
    public function testCheckVersionReportsUnsignedReleaseWithoutAssets(): void
    {
        $stub = $this->stubWithRelease($this->releasePayload([]));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->checkVersion();

        $this->assertTrue($result['result']);
        $this->assertFalse($result['signed']);
    }

    /**
     * A release tag that is not a plain version string is refused before it can reach the admin UI.
     *
     * checkVersion() runs before any signature is verified and its result is
     * rendered into a SweetAlert2 html: block, so the tag name is an XSS carrier
     * for anyone who controls (or MITMs) the releases endpoint.
     *
     * @param string $tag Attacker controlled tag_name
     *
     * @return void
     */
    #[DataProvider('hostileReleaseTags')]
    public function testHostileReleaseTagIsRejectedBeforeTheCompareCall(string $tag): void
    {
        $stub = $this->stubWithRelease($this->releasePayload($this->assetList(), $tag));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->checkVersion();

        $this->assertFalse($result['result']);
        $this->assertSame(lang('Settings.invalidVersionFormat'), $result['message']);
        $this->assertArrayNotHasKey('new_version', $result);
        $this->assertSame([], $stub->callsMatching('/compare/'), 'a rejected tag must not reach the compare endpoint');
        $this->assertNotSame([], $this->loggedAt('warning'));
    }

    /**
     * Tag names that must never be echoed back into the backend.
     *
     * @return array<string, array{0: string}>
     */
    public static function hostileReleaseTags(): array
    {
        return [
            'img onerror payload' => ['v99.0.0.0<img src=x onerror=alert(document.domain)>'],
            'script payload'      => ['<script>alert(1)</script>'],
            'trailing space'      => ['v99.0.0.0 '],
            'quote break out'     => ["v99.0.0.0'"],
            'not a version'       => ['latest'],
            'too many segments'   => ['v1.2.3.4.5'],
        ];
    }

    /**
     * Changed file paths that are not plain repository paths are dropped from the response.
     *
     * @return void
     */
    public function testChangedFilePathsOutsideTheAllowlistAreDropped(): void
    {
        $hostile = 'app/Config/</script><script>alert(2)</script>.php';

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => $hostile, 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha('x')],
            ['filename' => self::CHANGED_PATH, 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha(self::CHANGED_BODY)],
            ['filename' => 'app/Config/App<b>.php', 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha('x')],
            ['filename' => 12345, 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha('x')],
        ]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->checkVersion();

        $this->assertTrue($result['result']);
        $this->assertSame([self::CHANGED_PATH], array_column($result['changed_files'], 'filename'));
        $this->assertSame(1, $result['changed_count']);
        $this->assertStringNotContainsString('<', (string) json_encode($result['changed_files']));
        $this->assertNotSame([], $this->loggedAt('warning'));
    }

    /**
     * Commit detail urls are only requested when they belong to this repository on api.github.com.
     *
     * The commit fan-out carries the Authorization header, so an attacker supplied
     * url is both an SSRF primitive and a GitHub token exfiltration channel.
     *
     * @return void
     */
    public function testCommitDetailUrlsOutsideTheGithubApiAreNeverRequested(): void
    {
        $files = [];
        for ($i = 0; $i < 300; $i++) {
            $files[] = [
                'filename' => 'modules/Settings/Generated/File' . $i . '.php',
                'status'   => 'modified',
                'sha'      => ReleaseFixture::gitBlobSha('file' . $i),
            ];
        }

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::COMPARE_URL, 200, (string) json_encode([
            'files'   => $files,
            'commits' => [
                ['url' => 'https://attacker.example/steal'],
                ['url' => 'http://169.254.169.254/latest/meta-data/'],
                ['url' => 'https://api.github.com.attacker.example/repos/ci4-cms-erp/ci4ms/commits/abc'],
                ['url' => 'http://api.github.com/repos/ci4-cms-erp/ci4ms/commits/abc'],
                ['url' => 'https://api.github.com/repos/attacker/evil/commits/abc'],
                ['url' => ['not', 'a', 'string']],
                ['no_url' => true],
            ],
        ]));
        $this->trustPublisher();

        $tokenExisted = array_key_exists('github.token', $_ENV);
        $tokenBackup  = $_ENV['github.token'] ?? null;
        $_ENV['github.token'] = 'ghp_fixture_token_value';

        try {
            $result = (new UpdateService($stub))->checkVersion();
        } finally {
            if ($tokenExisted) {
                $_ENV['github.token'] = $tokenBackup;
            } else {
                unset($_ENV['github.token']);
            }
        }

        $this->assertTrue($result['result']);
        $this->assertSame(300, $result['changed_count']);

        foreach (['attacker.example', '169.254.169.254', 'repos/attacker/evil'] as $needle) {
            $this->assertSame([], $stub->callsMatching($needle), $needle . ' must never be requested');
        }

        $offHost = array_values(array_filter(
            $stub->calls,
            static fn (array $call): bool => !str_starts_with($call['url'], 'https://api.github.com/repos/ci4-cms-erp/ci4ms/')
        ));

        $this->assertSame([], $offHost, 'no request may leave the repository API namespace');

        foreach ($stub->calls as $call) {
            $headers = (array) ($call['options']['headers'] ?? []);

            if (isset($headers['Authorization'])) {
                $this->assertStringStartsWith('https://api.github.com/', $call['url']);
            }
        }

        $this->assertNotSame([], $this->loggedAt('warning'));
    }

    /**
     * A file the compare endpoint calls "removed" while the signed manifest still ships it aborts the update.
     *
     * This is the suppression attack: the release and its signature stay genuine,
     * only the unsigned diff is rewritten so nothing gets applied while .env is
     * bumped, permanently hiding the release from this installation.
     *
     * @return void
     */
    public function testRemovedStatusForAStillSignedFileAbortsTheUpdate(): void
    {
        $manifest = $this->signedManifest();
        $envelope = $this->envFingerprint();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => self::CHANGED_PATH, 'status' => 'removed', 'sha' => ReleaseFixture::gitBlobSha(self::CHANGED_BODY)],
        ]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('removed_but_signed', $result['code']);
        $this->assertSame(lang('Settings.updateSignatureInvalid'), $result['message']);
        $this->assertArrayNotHasKey('files', $result);
        $this->assertArrayNotHasKey('signed_hashes', $result);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('critical'));
        $this->assertSame($envelope, $this->envFingerprint(), '.env must not be touched by a rejected update');
    }

    /**
     * Suppressing a single signed file while applying the rest is refused too.
     *
     * @return void
     */
    public function testSelectiveRemovedSuppressionAbortsTheWholeUpdate(): void
    {
        $manifest = $this->signedManifestWith([
            self::CHANGED_PATH => self::CHANGED_BODY,
            self::SECOND_PATH  => self::SECOND_BODY,
        ]);
        $envelope = $this->envFingerprint();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => self::CHANGED_PATH, 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha(self::CHANGED_BODY)],
            ['filename' => self::SECOND_PATH, 'status' => 'removed', 'sha' => ReleaseFixture::gitBlobSha(self::SECOND_BODY)],
        ]));
        $stub->onContains(self::RAW_HOST, 200, self::CHANGED_BODY);
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('removed_but_signed', $result['code']);
        $this->assertArrayNotHasKey('files', $result);
        $this->assertNotSame([], $this->loggedAt('critical'));
        $this->assertSame($envelope, $this->envFingerprint());
    }

    /**
     * A diff that resolves to zero applicable files is refused instead of silently bumping the version.
     *
     * @return void
     */
    public function testUpdateResolvingToZeroFilesIsRejected(): void
    {
        $manifest = $this->signedManifest();
        $envelope = $this->envFingerprint();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => 'docs/Retired.md', 'status' => 'removed', 'sha' => ReleaseFixture::gitBlobSha('old')],
        ]));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('empty_apply_set', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('critical'));
        $this->assertSame($envelope, $this->envFingerprint());
    }

    /**
     * applyUpdate() refuses an empty file set even when the signed hash map is populated.
     *
     * assertSignedContent() is vacuously true for an empty set, so the empty set
     * has to be rejected on its own or the version bump happens with zero writes.
     *
     * @return void
     */
    public function testApplyUpdateRejectsAnEmptyFileSetWithSignedHashes(): void
    {
        $this->trustPublisher();

        $backupsBefore = $this->backupDirectories();
        $envelope      = $this->envFingerprint();
        $lockFile      = WRITEPATH . 'ci4ms_update.lock';

        $result = (new UpdateService(new StubCurlRequest()))->applyUpdate(
            self::NEW_VERSION,
            [],
            [['filename' => self::CHANGED_PATH, 'status' => 'removed']],
            [self::CHANGED_PATH => hash('sha256', self::CHANGED_BODY)]
        );

        $this->assertFalse($result['result']);
        $this->assertSame(lang('Settings.updateSignatureInvalid'), $result['message']);
        $this->assertArrayNotHasKey('applied_count', $result);
        $this->assertSame($backupsBefore, $this->backupDirectories(), 'no backup directory may be created');
        $this->assertFileDoesNotExist($lockFile, 'no update lock may be left behind');
        $this->assertSame($envelope, $this->envFingerprint(), '.env must keep its version');
        $this->assertNotSame([], $this->loggedAt('critical'));
    }

    /**
     * A manifest asset that declares more than the size ceiling is refused before it is trusted.
     *
     * @return void
     */
    public function testOversizedManifestAssetIsRejectedWithAnExplicitCode(): void
    {
        $manifest = $this->signedManifest();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest, ['Content-Length' => '900000000']);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('asset_too_large', $result['code']);
        $this->assertSame(lang('Settings.updateAssetTooLarge'), $result['message']);
        $this->assertNotSame('manifest_unreachable', $result['code']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST));
        $this->assertNotSame([], $this->loggedAt('critical'));
    }

    /**
     * A source file that declares more than the size ceiling aborts the update.
     *
     * @return void
     */
    public function testOversizedSourceFileIsRejected(): void
    {
        $manifest = $this->signedManifest();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => self::CHANGED_PATH, 'status' => 'modified', 'sha' => ReleaseFixture::gitBlobSha(self::CHANGED_BODY)],
        ]));
        $stub->onContains(self::RAW_HOST, 200, self::CHANGED_BODY, ['Content-Length' => '900000000']);
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame('asset_too_large', $result['code']);
        $this->assertArrayNotHasKey('files', $result);
    }

    /**
     * A non-string sha in the compare payload is rejected instead of raising a TypeError.
     *
     * hash_equals() is strictly typed, so `"sha": 123` used to escape the
     * \Exception catch as an \Error and surface as a 500.
     *
     * @return void
     */
    public function testNonStringCompareShaIsRejectedWithoutFatalError(): void
    {
        $manifest = $this->signedManifest();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            ['filename' => self::CHANGED_PATH, 'status' => 'modified', 'sha' => 123],
        ]));
        $stub->onContains(self::RAW_HOST, 200, self::CHANGED_BODY);
        $this->trustPublisher();

        $result = (new UpdateService($stub))->downloadPatchRaw(self::CURRENT_VERSION, self::NEW_VERSION);

        $this->assertFalse($result['result']);
        $this->assertSame([self::CHANGED_PATH], $result['failed']);
        $this->assertSame([], $result['files']);
        $this->assertSame([], $stub->callsMatching(self::RAW_HOST), 'a malformed entry must not be fetched at all');
        $this->assertNotSame([], $this->loggedAt('warning'));
    }

    /**
     * Stub wired for a fully valid release whose single changed file serves the given body.
     *
     * @param string      $servedBody Bytes raw.githubusercontent.com returns
     * @param string|null $blobSha    Git blob SHA-1 the compare endpoint advertises
     *
     * @return StubCurlRequest
     */
    private function happyPathStub(string $servedBody, ?string $blobSha = null): StubCurlRequest
    {
        $manifest = $this->signedManifest();

        $stub = $this->stubWithRelease($this->releasePayload($this->assetList()));
        $stub->on(self::MANIFEST_URL, 200, $manifest);
        $stub->on(self::SIGNATURE_URL, 200, self::$publisher->signatureFile($manifest));
        $stub->on(self::COMPARE_URL, 200, $this->comparePayload([
            [
                'filename' => self::CHANGED_PATH,
                'status'   => 'modified',
                'sha'      => $blobSha ?? ReleaseFixture::gitBlobSha(self::CHANGED_BODY),
            ],
        ]));
        $stub->onContains(self::RAW_HOST, 200, $servedBody);

        return $stub;
    }

    /**
     * Stub that answers only the release lookup.
     *
     * @param string $releaseJson Body of the releases/latest response
     *
     * @return StubCurlRequest
     */
    private function stubWithRelease(string $releaseJson): StubCurlRequest
    {
        $stub = new StubCurlRequest();
        $stub->on(self::RELEASE_URL, 200, $releaseJson);

        return $stub;
    }

    /**
     * Raw bytes of the canonical fixture manifest for the new release.
     *
     * @return string
     */
    private function signedManifest(): string
    {
        return $this->signedManifestWith([self::CHANGED_PATH => self::CHANGED_BODY]);
    }

    /**
     * Raw manifest bytes covering exactly the given path => content map.
     *
     * @param array<string, string> $files Repository relative path => published bytes
     *
     * @return string
     */
    private function signedManifestWith(array $files): string
    {
        return ReleaseFixture::manifest([
            'version' => self::NEW_VERSION,
            'files'   => ReleaseFixture::hashes($files),
        ]);
    }

    /**
     * SHA-256 of the project .env, used to prove a rejected update never bumped the version.
     *
     * @return string Empty string when there is no .env to protect
     */
    private function envFingerprint(): string
    {
        $path = ROOTPATH . '.env';

        return is_file($path) ? (string) hash_file('sha256', $path) : '';
    }

    /**
     * GitHub releases/latest payload.
     *
     * @param list<array{name: string, browser_download_url: string}> $assets Published assets
     * @param string                                                  $tag    Release tag name
     *
     * @return string
     */
    private function releasePayload(array $assets, string $tag = 'v0.35.0.0'): string
    {
        return (string) json_encode([
            'tag_name' => $tag,
            'body'     => 'Signed release notes.',
            'assets'   => $assets,
        ]);
    }

    /**
     * The two manifest assets a signed release publishes.
     *
     * @return list<array{name: string, browser_download_url: string}>
     */
    private function assetList(): array
    {
        return [
            ['name' => 'manifest.json', 'browser_download_url' => self::MANIFEST_URL],
            ['name' => 'manifest.json.sig', 'browser_download_url' => self::SIGNATURE_URL],
        ];
    }

    /**
     * GitHub compare payload.
     *
     * @param list<array{filename: string, status: string, sha: string}> $files Changed files
     *
     * @return string
     */
    private function comparePayload(array $files): string
    {
        return (string) json_encode(['files' => $files, 'commits' => []]);
    }

    /**
     * Trusts only the fixture publisher key.
     *
     * @return void
     */
    private function trustPublisher(): void
    {
        $this->injectKeyring([ReleaseFixture::KEY_ID => self::$publisher->keyringEntry('active')]);
    }

    /**
     * Replaces the shipped UpdateKeys config for the duration of the test.
     *
     * @param array<string, array{public_key: string, status: string, added: string, fingerprint: string}> $keys Keyring
     *
     * @return void
     */
    private function injectKeyring(array $keys): void
    {
        $config       = new UpdateKeys();
        $config->keys = $keys;

        Factories::injectMock('config', UpdateKeys::class, $config);
    }

    /**
     * Names of the directories currently sitting under writable/backups/.
     *
     * @return list<string>
     */
    private function backupDirectories(): array
    {
        $dirs = glob(WRITEPATH . 'backups/*', GLOB_ONLYDIR);

        return $dirs === false ? [] : $dirs;
    }

    /**
     * Empties TestLogger's process-wide log buffer.
     *
     * @return void
     */
    private function clearLoggedMessages(): void
    {
        (new ReflectionProperty(TestLogger::class, 'op_logs'))->setValue(null, []);
    }

    /**
     * Every message logged at the given level since the last clear.
     *
     * @param string $level Log level name
     *
     * @return list<string>
     */
    private function loggedAt(string $level): array
    {
        /** @var list<array{level: mixed, message: string, file: string|null}> $entries */
        $entries  = (new ReflectionProperty(TestLogger::class, 'op_logs'))->getValue();
        $messages = [];

        foreach ($entries as $entry) {
            $entryLevel = $entry['level'];

            if (is_string($entryLevel) && strtolower($entryLevel) === $level) {
                $messages[] = $entry['message'];
            }
        }

        return $messages;
    }
}
