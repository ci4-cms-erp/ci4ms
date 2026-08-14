<?php

declare(strict_types=1);

namespace Tests\Modules\Fileeditor;

require_once SUPPORTPATH . 'Fileeditor/AuthStub.php';

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Modules\Fileeditor\Controllers\Fileeditor;
use ReflectionProperty;

/**
 * Regression suite for BLOKER-2: Fileeditor::saveFile() previously had no
 * core-path or writable-root guard at all, and $hiddenItems
 * (Fileeditor.php:13) does not contain 'public', so an actor with only the
 * fileeditor.*.update permission (not superadmin) could overwrite
 * public/be-assets/js/ci4ms.js — the file that carries the CSRF hash
 * consumed by every backend page (modules/Backend/Views/base.php:209).
 * saveFile() is now guarded by isWritableTarget() (Fileeditor.php:128),
 * scoped to public/templates/ only.
 *
 * The controller is driven directly instead of through call('post', ...): no
 * migration touches this bug, so there is no reason to pay the DatabaseTestTrait
 * cost, and Fileeditor extends Modules\Backend\Controllers\BaseController, whose
 * initController() (BaseController.php:48-97) does auth()->user(), builds a
 * CommonModel, hits the sidebar/settings cache and reads the router service —
 * none of that runs unless CodeIgniter::runController() calls it, which direct
 * instantiation skips entirely (mirrors
 * tests/Modules/Settings/UpdateRollbackControllerTest.php). What is therefore
 * NOT covered here is the router/filter chain in front of these methods
 * (Ci4MsAuthFilter and the fileeditor.* permission).
 *
 * Fileeditor::triggerFileevent() (Fileeditor.php:84-92) still calls
 * auth()->user()->username unconditionally on every successful write, so this
 * suite requires tests/_support/Fileeditor/AuthStub.php, which defines a
 * namespace-scoped auth() that PHP resolves ahead of Shield's real one — see
 * that file's docblock for why.
 *
 * @internal
 */
final class FileeditorWriteAllowlistTest extends CIUnitTestCase
{
    /** Real file carrying the CSRF hash (modules/Backend/Views/base.php:209). */
    private const CSRF_JS_RELATIVE = 'public/be-assets/js/ci4ms.js';

    /** Real theme asset — Fileeditor's one legitimate write target. */
    private const THEME_CSS_RELATIVE = 'public/templates/default/assets/ci4ms.css';

    /**
     * The `name` validation regex shared by createFile() (Fileeditor.php:167),
     * createFolder() (Fileeditor.php:204) and renameFile()'s newName
     * (Fileeditor.php:125). Consists only of an allowed-character class, so it
     * has no notion of '..' as a special token and accepts it.
     */
    private const NAME_FIELD_REGEX = '/^[a-zA-Z0-9_ \-\.]+$/';

    private string $csrfJsAbsolute;

    private string $themeCssAbsolute;

    private string $originalCsrfJs;

    private string $originalThemeCss;

    /** @var list<string> Absolute paths this test created and must remove in tearDown(). */
    private array $createdArtifacts = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Defense in depth, order-independence: every action here goes
        // through Controller::validate() (Fileeditor.php:39,81,115,147,197,
        // 241,279), which uses the process-shared Config\Services::validation()
        // singleton (getShared=true). CodeIgniter\Validation\Validation::run()
        // never clears $this->errors itself — only its own reset() does
        // (vendor/codeigniter4/framework/system/Validation/Validation.php:
        // 1081-1090) — so if ANY earlier test in the same PHPUnit process
        // leaves that singleton with stale errors (SecurityXSSCSRFTest used
        // to, before its own tearDown() started clearing this; see
        // context.md), every dispatch() call below would fail validation
        // regardless of its own fields being valid. resetSingle() is
        // BaseService's test-only API for discarding one shared/mock service
        // (BaseService.php:424-435) so the next service('validation') call
        // builds a fresh instance — the same narrow scope SecurityXSSCSRFTest
        // uses, not the broader Services::reset()/resetFactories(), which
        // would also drop unrelated cached services and could disturb
        // migrateOnce-style connection caching elsewhere in the suite.
        \Config\Services::resetSingle('validation');

        $this->csrfJsAbsolute   = ROOTPATH . self::CSRF_JS_RELATIVE;
        $this->themeCssAbsolute = ROOTPATH . self::THEME_CSS_RELATIVE;

        $this->assertFileExists($this->csrfJsAbsolute, 'Fixture assumption broken: the real CSRF JS file must exist.');
        $this->assertFileExists($this->themeCssAbsolute, 'Fixture assumption broken: the real theme CSS file must exist.');

        $this->originalCsrfJs   = (string) file_get_contents($this->csrfJsAbsolute);
        $this->originalThemeCss = (string) file_get_contents($this->themeCssAbsolute);
    }

    /**
     * Restores both real files to their pre-test bytes and removes every
     * fixture this test created, regardless of whether the test passed. This
     * always runs (PHPUnit calls tearDown() on failure too), so it is the
     * primary safety net — not a best-effort cleanup.
     */
    protected function tearDown(): void
    {
        file_put_contents($this->csrfJsAbsolute, $this->originalCsrfJs);
        file_put_contents($this->themeCssAbsolute, $this->originalThemeCss);

        foreach ($this->createdArtifacts as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }
        $this->createdArtifacts = [];

        parent::tearDown();
    }

    /**
     * R1 — BLOKER-2 proof. saveFile() previously had no core-path or
     * writable-root guard at all, so it happily overwrote the
     * CSRF-hash-carrying JS file. saveFile() is now guarded by
     * isWritableTarget() (Fileeditor.php:128), scoped to public/templates/
     * only, which rejects this target — this assertion pins the fixed,
     * permanent expected behaviour (403, byte-identical file), not a
     * pre-fix characterization. Content is asserted via sha1, not status
     * code alone, so a fix that merely changes the response code without
     * stopping the write would not fool this test either.
     *
     * @return void
     */
    public function testSaveFileRejectsWriteToBackendCsrfJs(): void
    {
        $originalSha1 = sha1_file($this->csrfJsAbsolute);
        $payload      = '/* ci4ms-qa regression payload ' . bin2hex(random_bytes(8)) . ' */';

        $response = $this->dispatch('saveFile', [
            'path'    => '/' . self::CSRF_JS_RELATIVE,
            'content' => $payload,
        ]);

        $this->assertSame(
            $originalSha1,
            sha1_file($this->csrfJsAbsolute),
            'saveFile() must not modify public/be-assets/js/ci4ms.js — this is the CSRF-hash file every backend page loads.',
        );
        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * R2 — theme regression guard. The rejected naive fix for BLOKER-2 was
     * adding isCorePath() to saveFile(): 'public' was a coreItems segment,
     * so that fix would have killed theme editing along with fixing the
     * CSRF-file bug. The chosen fix instead added isWritableTarget()
     * (Fileeditor.php:128), scoped to public/templates/, which permits this
     * theme target. This stayed green both before and after Görev 7's fix.
     *
     * @return void
     */
    public function testSaveFileOnThemeCssStillSucceedsAndChangesContent(): void
    {
        $originalSha1 = sha1_file($this->themeCssAbsolute);
        $payload      = '/* ci4ms-qa regression payload ' . bin2hex(random_bytes(8)) . ' */';

        $response = $this->dispatch('saveFile', [
            'path'    => '/' . self::THEME_CSS_RELATIVE,
            'content' => $payload,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame($originalSha1, sha1_file($this->themeCssAbsolute));
    }

    /**
     * R2 (read path) — readFile() completion. saveFile() already proved theme
     * editing still works via the write path (R2 above); this proves the READ
     * path for the same theme asset also works today, so a fix that adds
     * isWritableTarget() to readFile() (it must not — see the dispatch
     * context) cannot silently break theme viewing without this test
     * catching it. Must stay green both before and after Görev 7: readFile()
     * only gained a pathContainsSymlink() guard (see
     * testReadFileViaSymlinkIsRejected() below), and
     * public/templates/default/assets/ci4ms.css is a real file, not a
     * symlink, so that guard cannot reject it either.
     *
     * @return void
     */
    public function testReadFileOnThemeCssReturnsContent(): void
    {
        $response = $this->dispatch('readFile', [
            'path' => '/' . self::THEME_CSS_RELATIVE,
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame($this->originalThemeCss, $body['content'] ?? null);
    }

    /**
     * R7 — pathContainsSymlink() regression guard for readFile(). readFile()
     * (Fileeditor.php:78-97) previously never called pathContainsSymlink(),
     * unlike saveFile() (:120), renameFile() (:152) and
     * deleteFileOrFolder() (:279) — see the guard matrix in the dispatch
     * context. Görev 7 closed that gap (:89-90, right after isHiddenPath(),
     * before the fullPath resolution). An actor who plants a symlink under a
     * location that is neither hidden nor core (docs/, the same
     * overlap-free target R3 uses) must not be able to read through it to
     * land on whatever it points to, regardless of any write-side
     * isWritableTarget() allowlist — that guard applies only to the write
     * methods, never to readFile(). This is the fixed, permanent
     * expected behaviour (403), not a pre-fix characterization.
     *
     * @return void
     */
    public function testReadFileViaSymlinkIsRejected(): void
    {
        $targetName     = '__ci4ms_qa_symlink_target_' . bin2hex(random_bytes(6)) . '.md';
        $targetAbsolute = ROOTPATH . 'docs/' . $targetName;
        $targetContent  = 'ci4ms-qa symlink target content ' . bin2hex(random_bytes(8));
        file_put_contents($targetAbsolute, $targetContent);
        $this->createdArtifacts[] = $targetAbsolute;

        $linkName     = '__ci4ms_qa_symlink_link_' . bin2hex(random_bytes(6)) . '.md';
        $linkAbsolute = ROOTPATH . 'docs/' . $linkName;
        symlink($targetAbsolute, $linkAbsolute);
        $this->createdArtifacts[] = $linkAbsolute;

        $response = $this->dispatch('readFile', [
            'path' => '/docs/' . $linkName,
        ]);

        $this->assertSame(
            403,
            $response->getStatusCode(),
            "readFile() must reject reading through docs/{$linkName} (a symlink to a real file) via its pathContainsSymlink() guard.",
        );
    }

    /**
     * R3 — isWritableTarget() regression guard for createFile(). Görev 7
     * added isWritableTarget() (Fileeditor.php:220), scoped to
     * public/templates/, so a location that is neither hidden nor under
     * that allowlist — docs/ qualifies on both counts — must now be
     * rejected before any filesystem write happens. This is the fixed,
     * permanent expected behaviour, not a pre-fix characterization: the
     * file must never be created.
     *
     * @return void
     */
    public function testCreateFileOutsideWritableRootIsRejected(): void
    {
        $name     = '__ci4ms_qa_fileeditor_regression_' . bin2hex(random_bytes(6)) . '.md';
        $absolute = ROOTPATH . 'docs/' . $name;

        $response = $this->dispatch('createFile', [
            'path' => '/docs',
            'name' => $name,
        ]);

        $this->assertSame(
            403,
            $response->getStatusCode(),
            "createFile() must reject a non-core, non-hidden target outside isWritableTarget()'s public/templates/ allowlist (docs/).",
        );
        $this->assertFileDoesNotExist($absolute);
    }

    /**
     * R4a — isWritableTarget() regression guard for createFolder(), same
     * reasoning as R3's testCreateFileOutsideWritableRootIsRejected(). This
     * is the fixed, permanent expected behaviour: the folder must never be
     * created.
     *
     * @return void
     */
    public function testCreateFolderOutsideWritableRootIsRejected(): void
    {
        $name     = '__ci4ms_qa_fileeditor_regression_' . bin2hex(random_bytes(6));
        $absolute = ROOTPATH . 'docs/' . $name;

        $response = $this->dispatch('createFolder', [
            'path' => '/docs',
            'name' => $name,
        ]);

        $this->assertSame(
            403,
            $response->getStatusCode(),
            "createFolder() must reject a non-core, non-hidden target outside isWritableTarget()'s public/templates/ allowlist (docs/).",
        );
        $this->assertDirectoryDoesNotExist($absolute);
    }

    /**
     * R4b — Fileeditor.php:204 validates createFolder()'s `name` field with
     * self::NAME_FIELD_REGEX, an allowed-character class that has no special
     * handling for '..' and therefore accepts it. Asserted directly against
     * the regex (not through a live mkdir() call): mkdir() against an
     * already-existing target such as ROOTPATH itself would only fail with a
     * PHP warning, and this suite's phpunit.xml.dist runs with
     * failOnWarning="true", so that path proves nothing safely. This
     * assertion is not expected to flip after Görev 7 — the plan keeps the
     * regex unchanged and adds a separate explicit str_contains($name, '..')
     * rejection alongside it.
     *
     * @return void
     */
    public function testCreateFolderNameRegexAcceptsParentTraversalSegment(): void
    {
        $this->assertSame(
            1,
            preg_match(self::NAME_FIELD_REGEX, '..'),
            "createFolder()'s name regex (Fileeditor.php:204) is expected to still accept '..' today.",
        );
    }

    /**
     * TDD-red proof (written before this task's fix landed) for the
     * createFolder() empty-intersection regression. createFolder() used to
     * reject a path whenever isHiddenPath() OR isCorePath() was true, and
     * isCorePath() treated ANY 'public' path segment as core — but
     * createFolder() also required isWritableTarget(), which only accepts
     * paths under 'public/templates/'. Those two requirements had an empty
     * intersection: a path under 'public/templates/...' was always rejected
     * by isCorePath() before isWritableTarget() was ever reached, and every
     * other path was rejected by isWritableTarget() itself, so
     * createFolder() could not succeed against ANY path while both guards
     * were active — theme folder creation under public/templates/ is
     * createFolder()'s one legitimate use case, the same target
     * saveFile()/createFile() already allow via isWritableTarget()-only
     * (T1) semantics.
     *
     * Was expected to FAIL against pre-fix code (real outcome: 403, no
     * directory created). The accepted fix removed isCorePath() from
     * createFolder() entirely — the method no longer exists in this
     * controller — so it matches saveFile()/createFile()'s
     * isWritableTarget()-only pattern (Fileeditor.php:266), leaving
     * isHiddenPath() (:253) and pathContainsSymlink() (:255) as
     * createFolder()'s only path-segment guards alongside isWritableTarget().
     *
     * @return void
     */
    public function testCreateFolderUnderWritableRootSucceeds(): void
    {
        $name     = '__ci4ms_qa_createfolder_' . bin2hex(random_bytes(6));
        $absolute = ROOTPATH . 'public/templates/default/' . $name;
        $this->createdArtifacts[] = $absolute;

        $response = $this->dispatch('createFolder', [
            'path' => '/public/templates/default',
            'name' => $name,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDirectoryExists($absolute);
    }

    /**
     * R5a — renameFile() guards hidden paths before touching the filesystem
     * (Fileeditor.php:149), so this is a plain regression check, not a proof
     * of a gap. 'vendor' is used as the target because it is a hiddenItems
     * segment — before this task's (b) fix it was also rejected as a
     * coreItems segment, giving double coverage; today isHiddenPath() alone
     * is enough to reject it, so the request never reaches
     * realpath()/rename() and cannot touch the real vendor/ directory. A
     * path that is core-only (e.g. a bare 'app' segment) is deliberately not
     * used here: this method's actual filesystem call is not part of
     * BLOKER-2's scope, and there is no need to risk it for a redundant
     * assertion.
     *
     * @return void
     */
    public function testRenameFileOnHiddenCoreOverlapPathIsRejected(): void
    {
        $response = $this->dispatch('renameFile', [
            'path'    => '/vendor/ci4ms-qa-regression-source.txt',
            'newName' => 'ci4ms-qa-regression-target.txt',
        ]);

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * R5b — same reasoning as R5a, for deleteFileOrFolder() (Fileeditor.php:233).
     *
     * @return void
     */
    public function testDeleteFileOrFolderOnHiddenCoreOverlapPathIsRejected(): void
    {
        $response = $this->dispatch('deleteFileOrFolder', [
            'path' => '/vendor/ci4ms-qa-regression-target.txt',
        ]);

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * R5c — createFolder() (Fileeditor.php:241) previously checked BOTH
     * isHiddenPath() and isCorePath() before isWritableTarget(). Because
     * isCorePath() matched 'public' as a coreItems segment and
     * isWritableTarget() only accepts paths under 'public/templates/', that
     * combination had zero intersection — createFolder() could not succeed
     * against ANY path before the fix (see the former TDD-red
     * testCreateFolderUnderWritableRootSucceeds() above). The accepted fix
     * removed isCorePath() from createFolder() entirely — the method no
     * longer exists in this controller — leaving it on the same
     * isWritableTarget()-only (T1) semantics already used by
     * saveFile()/createFile(). 'vendor' is used here for the same overlap
     * reason as R5a: it is caught by isHiddenPath() (Fileeditor.php:253),
     * which the fix leaves untouched, so this 403 assertion holds
     * identically before and after the fix — only the gate that would
     * reject a pure-core, non-hidden path (e.g. 'app') changed, from
     * isCorePath() to isWritableTarget()'s allowlist miss.
     *
     * @return void
     */
    public function testCreateFolderOnHiddenCoreOverlapPathIsRejected(): void
    {
        $response = $this->dispatch('createFolder', [
            'path' => '/vendor',
            'name' => 'ci4ms-qa-regression-folder',
        ]);

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * R6 — traversal. saveFile() resolves the submitted path with realpath()
     * and rejects anything whose resolved path does not start with
     * realpath(ROOTPATH) (Fileeditor.php:108,111). A naive implementation that
     * only checked the raw, unresolved string (e.g. str_starts_with(ROOTPATH .
     * $path, ROOTPATH)) would never catch this, because the raw concatenation
     * always starts with ROOTPATH by construction regardless of embedded '..'
     * segments — only resolving the path first exposes the escape. This is
     * proven against a real file one directory above ROOTPATH (created and
     * removed by this test) rather than a non-existent target, because
     * realpath() returns false for paths that do not exist, which would also
     * yield a 400 but for an unrelated reason (existence, not containment) and
     * would not discriminate a naive implementation from a correct one.
     *
     * The probe name deliberately does NOT start with ROOTPATH's own basename
     * ("ci4ms"): doing so would exercise the sibling-basename-prefix scenario
     * instead — see
     * testSaveFileRejectsSiblingPathSharingRootpathsBasenameViaContainmentCheck()
     * below, a related but distinct probe (BLOKER-A).
     *
     * @return void
     */
    public function testSaveFileRejectsPathThatResolvesOutsideProjectRoot(): void
    {
        $outsideDir = dirname(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));

        if (! is_writable($outsideDir)) {
            $this->markTestSkipped("Cannot write a traversal probe outside ROOTPATH ({$outsideDir} is not writable in this environment).");
        }

        $probeName = 'outside_root_qa_probe_' . bin2hex(random_bytes(6)) . '.txt';
        $probePath = $outsideDir . DIRECTORY_SEPARATOR . $probeName;

        file_put_contents($probePath, 'outside-root-marker');
        $this->createdArtifacts[] = $probePath;

        $response = $this->dispatch('saveFile', [
            'path'    => '/../' . $probeName,
            'content' => 'ci4ms-qa regression payload',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('outside-root-marker', (string) file_get_contents($probePath), 'A rejected traversal request must not touch the file it targeted.');
    }

    /**
     * NEW FINDING — not one of R1-R6, found while building the R6 fixture and
     * kept as a permanent regression test for BLOKER-A, the sibling-
     * basename-prefix path-containment leak.
     *
     * saveFile()'s containment check used to be
     * `strpos($fullPath, realpath(ROOTPATH)) !== 0` (Fileeditor.php:126,
     * pre-fix). realpath(ROOTPATH) carries no trailing directory separator,
     * and strpos() has no notion of a path boundary, so any sibling of the
     * project root whose name happens to start with the project directory's
     * own basename ("ci4ms") was treated as if it were inside ROOTPATH —
     * e.g. a real sibling directory/file named "ci4ms-evil" or, as
     * reproduced here, "ci4ms_whatever.txt". The identical pattern (no
     * trailing separator) previously existed in every other path-containment
     * check in this controller (listFiles, readFile, renameFile, createFile,
     * createFolder, deleteFileOrFolder) — the whole controller shared the
     * same gap.
     *
     * BLOKER-A's fix replaced every one of those checks with the new
     * isInsideProject() helper (Fileeditor.php:330-352), which appends a
     * trailing DIRECTORY_SEPARATOR before comparing and so bounds the match
     * exactly. saveFile() now calls isInsideProject() at Fileeditor.php:126,
     * which runs BEFORE isWritableTarget() (:132) — so this test's
     * sibling-prefix probe path is rejected right there, with 400
     * (Backend.invalid), and never reaches isWritableTarget() at all. That
     * is the actual closure of BLOKER-A for this write path: a direct,
     * correct containment rejection — not the accidental 403 that
     * isWritableTarget()'s blanket '..' rejection produced in the
     * intermediate state this test previously pinned (superseded now that
     * the real bug is fixed). This 400/probe-untouched behaviour is the
     * fixed, permanent expected outcome, not a pre-fix characterization. The
     * primary security assertion — the probe file's content is never
     * overwritten — is unchanged from every previous version of this test;
     * only the status-code expectation and this narrative changed, to match
     * the actual fix mechanism (isInsideProject(), not
     * isWritableTarget()'s '..' rejection).
     *
     * @return void
     */
    public function testSaveFileRejectsSiblingPathSharingRootpathsBasenameViaContainmentCheck(): void
    {
        $outsideDir = dirname(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));
        $rootBasename = basename(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));

        if (! is_writable($outsideDir)) {
            $this->markTestSkipped("Cannot write a traversal probe outside ROOTPATH ({$outsideDir} is not writable in this environment).");
        }

        // Deliberately starts with ROOTPATH's own basename ("ci4ms..."), the
        // exact string this controller's containment check fails to bound.
        $probeName = $rootBasename . '_qa_prefix_collision_probe_' . bin2hex(random_bytes(6)) . '.txt';
        $probePath = $outsideDir . DIRECTORY_SEPARATOR . $probeName;

        file_put_contents($probePath, 'outside-root-marker');
        $this->createdArtifacts[] = $probePath;

        $response = $this->dispatch('saveFile', [
            'path'    => '/../' . $probeName,
            'content' => 'ci4ms-qa regression payload',
        ]);

        $this->assertSame(
            'outside-root-marker',
            (string) file_get_contents($probePath),
            "saveFile() must not write through a path outside ROOTPATH ({$probePath}) even though it shares ROOTPATH's basename as a string prefix — isInsideProject()'s trailing-separator boundary check rejects it.",
        );
        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * BLOKER-A (HIGH) — readFile()'s containment check (Fileeditor.php:94) is
     * `strpos($fullPath, realpath(ROOTPATH)) !== 0`. realpath(ROOTPATH) has no
     * trailing directory separator, and strpos() has no notion of a path
     * boundary, so a sibling of the project root whose name happens to start
     * with the project directory's own basename ("ci4ms") is treated as if it
     * were inside ROOTPATH — the same string-prefix gap documented on
     * saveFile() above
     * (testSaveFileRejectsSiblingPathSharingRootpathsBasenameViaContainmentCheck()).
     * Unlike saveFile(), readFile() never calls isWritableTarget(), so this
     * gap has no other guard behind it and is fully exploitable today: an
     * actor with only the fileeditor.*.read permission can read a file
     * planted one directory above ROOTPATH, as long as its name starts with
     * "ci4ms" and its extension is in $allowedExtensions.
     *
     * This pins the fixed/expected behaviour (400, marker content absent
     * from the response body) and is expected to FAIL against today's code,
     * where the real outcome is 200 with the marker content in the JSON
     * body's `content` field.
     *
     * @return void
     */
    public function testReadFileRejectsSiblingPathSharingRootpathsBasenamePrefix(): void
    {
        $outsideDir   = dirname(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));
        $rootBasename = basename(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));

        if (! is_writable($outsideDir)) {
            $this->markTestSkipped("Cannot write a traversal probe outside ROOTPATH ({$outsideDir} is not writable in this environment).");
        }

        // Deliberately starts with ROOTPATH's own basename ("ci4ms..."), the
        // exact string readFile()'s containment check fails to bound. '.md'
        // is used because it is in $allowedExtensions (Fileeditor.php:10).
        $probeName = $rootBasename . '_qa_read_prefix_collision_probe_' . bin2hex(random_bytes(6)) . '.md';
        $probePath = $outsideDir . DIRECTORY_SEPARATOR . $probeName;
        $marker    = 'SECRET-OUTSIDE-ROOT-' . bin2hex(random_bytes(8));

        file_put_contents($probePath, $marker);
        $this->createdArtifacts[] = $probePath;

        $response = $this->dispatch('readFile', [
            'path' => '/../' . $probeName,
        ]);

        $this->assertSame(
            400,
            $response->getStatusCode(),
            "readFile() must reject a sibling-of-ROOTPATH path whose name shares ROOTPATH's basename as a string prefix ({$probePath}).",
        );

        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotSame(
            $marker,
            $body['content'] ?? null,
            "readFile()'s JSON response must not carry the sibling-path probe's content in its `content` field.",
        );
        $this->assertStringNotContainsString(
            $marker,
            (string) $response->getBody(),
            'A rejected sibling-prefix read request must never leak the target file\'s content anywhere in the response body.',
        );
    }

    /**
     * BLOKER-A (HIGH) — listFiles()'s containment check (Fileeditor.php:51)
     * shares the identical `strpos($fullPath, realpath(ROOTPATH)) !== 0` gap
     * documented on readFile() above
     * (testReadFileRejectsSiblingPathSharingRootpathsBasenamePrefix()). A
     * directory sibling of the project root whose name starts with
     * ROOTPATH's own basename ("ci4ms") is treated as inside ROOTPATH, so its
     * contents — including any marker file inside it — are listed in the
     * JSON response. listFiles() has no isWritableTarget() guard at all
     * (isCorePath() no longer exists anywhere in this controller), so
     * nothing else stops this.
     *
     * listFiles() also validates a `_` POST field (Fileeditor.php:34) as
     * `required`: this is the cache-busting query parameter jQuery appends
     * automatically to every `cache: false` GET AJAX call
     * (public/be-assets/js/fileeditor.js:26-28), not an application field,
     * but dispatch() below drives the controller directly, so it must be
     * supplied explicitly or the request never reaches the containment check
     * at all.
     *
     * This pins the fixed/expected behaviour (400) and is expected to FAIL
     * against today's code, where the real outcome is 200 with the probe
     * directory's listing — including the marker file's name — in the JSON
     * body.
     *
     * @return void
     */
    public function testListFilesRejectsSiblingPathSharingRootpathsBasenamePrefix(): void
    {
        $outsideDir   = dirname(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));
        $rootBasename = basename(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));

        if (! is_writable($outsideDir)) {
            $this->markTestSkipped("Cannot write a traversal probe outside ROOTPATH ({$outsideDir} is not writable in this environment).");
        }

        // Deliberately starts with ROOTPATH's own basename ("ci4ms..."), the
        // exact string listFiles()'s containment check fails to bound.
        $probeDirName = $rootBasename . '_qa_list_prefix_collision_probe_' . bin2hex(random_bytes(6));
        $probeDirPath = $outsideDir . DIRECTORY_SEPARATOR . $probeDirName;
        mkdir($probeDirPath);

        $markerName = 'SECRET_MARKER_' . bin2hex(random_bytes(6)) . '.txt';
        $markerPath = $probeDirPath . DIRECTORY_SEPARATOR . $markerName;
        file_put_contents($markerPath, 'outside-root-marker');

        // Pushed marker file before the directory: tearDown() walks
        // createdArtifacts sequentially and unlinks files / rmdir()s
        // directories in that order, and rmdir() on a non-empty directory
        // silently no-ops (@rmdir), so the file must be removed first.
        $this->createdArtifacts[] = $markerPath;
        $this->createdArtifacts[] = $probeDirPath;

        $response = $this->dispatch('listFiles', [
            'path' => '/../' . $probeDirName,
            '_'    => (string) time(),
        ]);

        $this->assertSame(
            400,
            $response->getStatusCode(),
            "listFiles() must reject a sibling-of-ROOTPATH directory whose name shares ROOTPATH's basename as a string prefix ({$probeDirPath}).",
        );

        $this->assertStringNotContainsString(
            $markerName,
            (string) $response->getBody(),
            'A rejected sibling-prefix list request must never leak the probe directory\'s contents in the response body.',
        );
    }

    /**
     * T2-functional (1/8) — renameFile() succeeds under isWritableTarget()'s
     * public/templates/ allowlist. Before this task's (b) fix,
     * renameFile() checked `isHiddenPath($path) || $this->isCorePath($path)`
     * — isCorePath() treated 'public' as a coreItems segment, so every path
     * under public/templates/ (the only allowlist
     * isWritableTarget() accepts) was rejected before ever reaching
     * isWritableTarget(), leaving renameFile() unable to succeed against ANY
     * path. Uses a throwaway fixture, never the real theme assets covered by
     * setUp()/tearDown()'s byte-restore.
     *
     * Expected to FAIL (403, not renamed) against pre-(b)-fix code.
     *
     * @return void
     */
    public function testRenameFileUnderWritableRootSucceeds(): void
    {
        $originalName = '__ci4ms_qa_rename_src_' . bin2hex(random_bytes(6)) . '.txt';
        $renamedName  = '__ci4ms_qa_rename_dst_' . bin2hex(random_bytes(6)) . '.txt';
        $dir          = ROOTPATH . 'public/templates/default/assets/';

        $originalAbsolute = $dir . $originalName;
        $renamedAbsolute  = $dir . $renamedName;

        file_put_contents($originalAbsolute, 'ci4ms-qa rename fixture content');
        $this->createdArtifacts[] = $originalAbsolute;
        $this->createdArtifacts[] = $renamedAbsolute;

        $response = $this->dispatch('renameFile', [
            'path'    => '/public/templates/default/assets/' . $originalName,
            'newName' => $renamedName,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFileDoesNotExist($originalAbsolute);
        $this->assertFileExists($renamedAbsolute);
    }

    /**
     * T2-functional (2/8) — deleteFileOrFolder() succeeds under
     * isWritableTarget()'s public/templates/ allowlist. Same empty-
     * intersection reasoning as testRenameFileUnderWritableRootSucceeds()
     * above, applied to deleteFileOrFolder()'s `isHiddenPath($path) ||
     * $this->isCorePath($path)` guard (pre-fix; isCorePath() no longer
     * exists). Uses a throwaway fixture, never a real theme asset.
     *
     * Expected to FAIL (403, not deleted) against pre-(b)-fix code.
     *
     * @return void
     */
    public function testDeleteFileOrFolderUnderWritableRootSucceeds(): void
    {
        $name     = '__ci4ms_qa_delete_' . bin2hex(random_bytes(6)) . '.txt';
        $absolute = ROOTPATH . 'public/templates/default/assets/' . $name;

        file_put_contents($absolute, 'ci4ms-qa delete fixture content');
        $this->createdArtifacts[] = $absolute;

        $response = $this->dispatch('deleteFileOrFolder', [
            'path' => '/public/templates/default/assets/' . $name,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFileDoesNotExist($absolute);
    }

    /**
     * T2-functional (3/8) — renameFile() must still reject a target outside
     * isWritableTarget()'s public/templates/ allowlist now that isCorePath()
     * has been removed from its guard entirely. public/be-assets/ is used
     * because it is not in hiddenItems, and — since isCorePath() no longer
     * exists — is no longer checked by it either, so isWritableTarget()'s
     * prefix allowlist is now the only guard standing between this path and
     * rename() — this is the test that proves removing isCorePath() did not
     * also remove containment. The fixture is created fresh (never a real
     * be-assets file) so this test cannot corrupt anything if the guard
     * were ever broken; it also asserts the fixture is untouched afterward.
     *
     * Expected behaviour (403, file untouched) holds both before and after
     * the (b) fix — before, isCorePath() rejected it ('public' was a
     * coreItems segment); after, isWritableTarget() does (be-assets is not
     * under public/templates/). This is a permanent regression lock, not a
     * red/green proof.
     *
     * @return void
     */
    public function testRenameFileOutsideWritableRootIsRejected(): void
    {
        $name     = '__ci4ms_qa_reject_rename_' . bin2hex(random_bytes(6)) . '.txt';
        $absolute = ROOTPATH . 'public/be-assets/' . $name;

        file_put_contents($absolute, 'ci4ms-qa reject-rename fixture content');
        $this->createdArtifacts[] = $absolute;

        $response = $this->dispatch('renameFile', [
            'path'    => '/public/be-assets/' . $name,
            'newName' => 'ci4ms-qa-should-not-exist.txt',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFileExists($absolute, 'A rejected rename must leave the source file untouched.');
    }

    /**
     * T2-functional (4/8) — renameFile() must still reject app/ now that
     * isCorePath() has been removed from its guard entirely. A nonexistent
     * path under app/ cannot be used here: this method's existence/containment check
     * (Fileeditor.php, moved earlier by this task's (a) fix) runs BEFORE
     * isWritableTarget(), so a nonexistent source now returns 400
     * (Backend.invalid) rather than 403 regardless of isWritableTarget() —
     * verified empirically while building this test. app/Config/App.php is
     * used instead: a real, harmless, already-tracked file. It is never
     * written to or renamed — isWritableTarget() rejects the request with
     * 403 before rename() is reached, which this test also asserts by
     * checking the file still exists at its original path afterward.
     *
     * Expected behaviour (403, file untouched) holds both before and after
     * the (b) fix — before, isCorePath() rejected it ('app' was a coreItems
     * segment); after, isWritableTarget() does (app/ is not under
     * public/templates/). Permanent regression lock, not a red/green proof.
     *
     * @return void
     */
    public function testRenameFileUnderAppIsRejected(): void
    {
        $absolute = ROOTPATH . 'app/Config/App.php';
        $this->assertFileExists($absolute, 'Fixture assumption broken: app/Config/App.php must exist.');
        $originalSha1 = sha1_file($absolute);

        $response = $this->dispatch('renameFile', [
            'path'    => '/app/Config/App.php',
            'newName' => 'ci4ms-qa-should-not-exist.php',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFileExists($absolute);
        $this->assertSame($originalSha1, sha1_file($absolute), 'A rejected rename must leave app/Config/App.php byte-identical.');
    }

    /**
     * T2-functional (5/8) — deleteFileOrFolder() must still reject a target
     * outside isWritableTarget()'s public/templates/ allowlist now that
     * isCorePath() has been removed from its guard entirely. Same reasoning
     * as testRenameFileOutsideWritableRootIsRejected() above. Fresh
     * fixture, never a real be-assets file.
     *
     * Permanent regression lock (403, file untouched), holds before and
     * after the (b) fix.
     *
     * @return void
     */
    public function testDeleteFileOrFolderOutsideWritableRootIsRejected(): void
    {
        $name     = '__ci4ms_qa_reject_delete_' . bin2hex(random_bytes(6)) . '.txt';
        $absolute = ROOTPATH . 'public/be-assets/' . $name;

        file_put_contents($absolute, 'ci4ms-qa reject-delete fixture content');
        $this->createdArtifacts[] = $absolute;

        $response = $this->dispatch('deleteFileOrFolder', [
            'path' => '/public/be-assets/' . $name,
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFileExists($absolute, 'A rejected delete must leave the target file untouched.');
    }

    /**
     * T2-functional (6/8) — allowedFileTypes($newName) still blocks a
     * rename to a dangerous extension under public/templates/, i.e. once
     * isWritableTarget() itself would otherwise allow the request through.
     * Proves the (b) fix (dropping isCorePath() from renameFile()) did not
     * also weaken the pre-existing allowlist check (Fileeditor.php:160-161),
     * which runs before isWritableTarget() and rejects unconditionally on
     * extension alone.
     *
     * Expected behaviour (403, source untouched) holds both before and
     * after the (b) fix.
     *
     * @return void
     */
    public function testRenameFileToPhpExtensionIsRejected(): void
    {
        $name     = '__ci4ms_qa_reject_ext_' . bin2hex(random_bytes(6)) . '.txt';
        $absolute = ROOTPATH . 'public/templates/default/assets/' . $name;

        file_put_contents($absolute, 'ci4ms-qa reject-extension fixture content');
        $this->createdArtifacts[] = $absolute;

        $response = $this->dispatch('renameFile', [
            'path'    => '/public/templates/default/assets/' . $name,
            'newName' => 'ci4ms-qa-should-not-exist.php',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFileExists($absolute, 'A rejected extension-allowlist rename must leave the source file untouched.');
        $this->assertFileDoesNotExist(ROOTPATH . 'public/templates/default/assets/ci4ms-qa-should-not-exist.php');
    }

    /**
     * (a) hypothesis test (7/8) — dispatch task item (a). renameFile()'s
     * pre-fix block (Fileeditor.php:163-169) computes
     * `$fullPath = realpath(ROOTPATH . $path)` and immediately calls
     * `dirname($fullPath)` without first checking whether realpath()
     * returned false (nonexistent path) — the existence/containment check
     * (`!$fullPath || !file_exists($fullPath) || !$this->isInsideProject($fullPath)`)
     * used to run LAST, at the end of the method, after dirname() had
     * already been called twice. Under this file's declare(strict_types=1)
     * (:2), dirname() requires a string argument; realpath() returns false
     * (bool) for a path that does not exist, and strict_types disallows the
     * bool→string coercion PHP would otherwise perform, so PHP throws a
     * TypeError instead of the controller returning a 400 response. docs/
     * is used because it is neither a hiddenItems segment nor was it ever
     * rejected by isCorePath() (removed by this task's (b) fix), so nothing
     * earlier in the method stops execution before reaching the crash — verified
     * reproducible against today's unmodified code, independent of the (b)
     * fix, since isCorePath() never matched docs/ either.
     *
     * Pins the fixed behaviour (400, no exception) — expected to ERROR
     * (uncaught TypeError) against pre-(a)-fix code, per this suite's
     * established red/green convention.
     *
     * @return void
     */
    public function testRenameFileWithNonexistentSourceReturnsErrorResponseInsteadOfCrashing(): void
    {
        $missingName = '__ci4ms_qa_rename_missing_source_' . bin2hex(random_bytes(6)) . '.txt';

        $response = $this->dispatch('renameFile', [
            'path'    => '/docs/' . $missingName,
            'newName' => 'ci4ms-qa-rename-target.txt',
        ]);

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * (a) hypothesis, second scenario (8/8) — a rename source that resolves
     * outside ROOTPATH via '../' traversal, using the same probe-outside-
     * root fixture pattern as testSaveFileRejectsPathThatResolvesOutsideProjectRoot()
     * (R6) above. The chosen rename target ('ci4ms-qa-renamed-outside-probe.txt')
     * deliberately shares ROOTPATH's own basename ("ci4ms") as a string
     * prefix — running this red first exposed that the pre-fix :168 check
     * (`strpos($realNewDir . DIRECTORY_SEPARATOR . $newName, realpath(ROOTPATH)) !== 0`)
     * has the exact same unbounded string-prefix bug as BLOKER-A's other
     * findings: $realNewDir (outside ROOTPATH) concatenated with a $newName
     * starting with "ci4ms" produces a string that DOES start with
     * realpath(ROOTPATH), so :168 fails to reject it. The request is still
     * stopped, but by a different, incidental guard: isWritableTarget()
     * (called right after :168, before rename()) rejects any raw $path
     * containing a '..' segment unconditionally — see context.md's ":168
     * kapsam dışı bırakıldı" decision from the prior BLOKER-A round, which
     * relied on exactly this fallback. Measured pre-fix outcome: 403 (via
     * isWritableTarget), not 400. After this task's (a) fix, the new
     * existence/containment check (`!$fullPath || !file_exists($fullPath)
     * || !$this->isInsideProject($fullPath)`), now run immediately after
     * $fullPath is computed and before dirname() or :168's logic ever run,
     * rejects the out-of-root source directly — 400, independent of
     * $newName's value. This is therefore a genuine red/green proof (403 →
     * 400), not a pre-existing pass, and it additionally documents a real
     * (if — per the isWritableTarget() fallback — not currently reachable
     * as a write primitive) boundary bug in the old :168 check.
     *
     * @return void
     */
    public function testRenameFileRejectsPathThatResolvesOutsideProjectRoot(): void
    {
        $outsideDir = dirname(rtrim(ROOTPATH, DIRECTORY_SEPARATOR));

        if (! is_writable($outsideDir)) {
            $this->markTestSkipped("Cannot write a traversal probe outside ROOTPATH ({$outsideDir} is not writable in this environment).");
        }

        $probeName = 'outside_root_qa_rename_probe_' . bin2hex(random_bytes(6)) . '.txt';
        $probePath = $outsideDir . DIRECTORY_SEPARATOR . $probeName;

        file_put_contents($probePath, 'outside-root-rename-marker');
        $this->createdArtifacts[] = $probePath;

        $renamedProbePath        = $outsideDir . DIRECTORY_SEPARATOR . 'ci4ms-qa-renamed-outside-probe.txt';
        $this->createdArtifacts[] = $renamedProbePath;

        $response = $this->dispatch('renameFile', [
            'path'    => '/../' . $probeName,
            'newName' => 'ci4ms-qa-renamed-outside-probe.txt',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            'outside-root-rename-marker',
            (string) file_get_contents($probePath),
            'A rejected traversal rename request must not touch the file it targeted.',
        );
        $this->assertFileDoesNotExist($renamedProbePath);
    }

    /**
     * Runs the real controller method against an injected request/response,
     * mirroring tests/Modules/Settings/UpdateRollbackControllerTest.php's
     * dispatch() helper: Fileeditor has no constructor of its own, so plain
     * `new Fileeditor()` is enough, and initController() is never invoked.
     *
     * @param string              $action Public method name to call on Fileeditor
     * @param array<string,mixed> $params POST fields the controller reads via getVar()
     *
     * @return ResponseInterface Response object returned by the controller action
     */
    private function dispatch(string $action, array $params): ResponseInterface
    {
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());

        // getVar() reads the 'request' global (IncomingRequest::getVar(),
        // RequestTrait::fetchGlobal()); Validation::withRequest() does too.
        // 'post' and 'request' are tracked separately by Superglobals, so both
        // must be set.
        $request->setGlobal('post', $params);
        $request->setGlobal('request', $params);

        $controller = new Fileeditor();

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));

        $result = $controller->{$action}();

        $this->assertInstanceOf(ResponseInterface::class, $result);

        return $result;
    }
}
