<?php

declare(strict_types=1);

namespace Tests\Modules\Media;

use CodeIgniter\Test\CIUnitTestCase;
use FilesystemIterator;
use Modules\Media\Config\MediaConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionObject;
use RuntimeException;

/**
 * Regression lock for KALEM 2 (S12) of the elFinder 2.1.68 -> 2.1.70 security
 * re-audit's MEDIUM-B finding: before this file existed, nothing in the suite
 * verified either (a) that Media::elfinderConnection() constructs a real,
 * unmodified \elFinderConnector, or (b) that MediaConfig::$csrfExcept still
 * excepts this endpoint from CI4's own CSRF check. Both regressed silently
 * once during this project's history (the anonymous CSRF-disabling subclass
 * removed in KALEM 4), and no test caught it.
 *
 * Why both of these matter together, and why "fixing" one by dropping the
 * other is wrong:
 *
 *  - elFinderConnector::run() (vendor/studio-42/elfinder/php/elFinderConnector.class.php,
 *    2.1.70: exit() at :432/:562/:565) always terminates the request before
 *    returning to CodeIgniter. CI4's own after-filters -- including
 *    csrfTokenRefreshFilter, which is what hands the client a fresh CI4
 *    token after .env:118's `security.regenerate = true` rotates the old one
 *    on a successful check -- never run on this route. If
 *    MediaConfig::$csrfExcept ever dropped
 *    'backend/media/elfinderConnection', the first write request would
 *    consume CI4's token and the browser would never receive a replacement,
 *    breaking every write request after the first in the same session.
 *  - Precisely because CI4's own CSRF check is excepted here, elFinder's OWN
 *    CSRF mechanism (X-elFinder-CSRF header, random_bytes(32) token,
 *    hash_equals() validation, 900s TTL --
 *    elFinderConnector::validateCsrfToken()/issueCsrfToken()) is the only
 *    thing standing between a mere session cookie and an unauthenticated
 *    write on this route. An anonymous subclass overriding
 *    validateCsrfToken() to `return true` (and issueCsrfToken() to
 *    `return ''`) -- the exact shape this endpoint carried before the KALEM 4
 *    hardening -- silently disables that mechanism entirely, and the request
 *    still "works" from the outside, which is what makes the regression
 *    silent rather than loud.
 *
 * elfinderConnection() cannot be invoked directly (its output routine ends in
 * exit(), which would kill the PHPUnit process), so
 * extractConnectorConstructionSource() pulls the literal `$connector = ...;`
 * expression out of the real source file via token_get_all() and evaluates
 * it -- the same technique
 * MediaUploadIntegrationTest::extractUploadPresaveClosureSource() uses for
 * the upload.presave closure. This runs the actual production construction
 * code rather than a hand-copied reimplementation that could silently drift
 * from it.
 *
 * @internal
 */
final class MediaElfinderConnectorCsrfTest extends CIUnitTestCase
{
    private string $sandbox;
    private string|false|null $originalRequestMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/ci4ms-media-qa-csrf-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox, 0777, true);

        // elFinderConnector::__construct() reads $_SERVER['REQUEST_METHOD']
        // directly (not through CI4's request object); PHPUnit's CLI SAPI
        // does not set it. Snapshot and restore so this doesn't leak into
        // other test files sharing the process.
        $this->originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD']   = 'POST';
    }

    protected function tearDown(): void
    {
        if ($this->originalRequestMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
        }

        $this->removeDirectory($this->sandbox);
        parent::tearDown();
    }

    /**
     * getShortName() is the strong assertion here, not a string search over
     * the source: an anonymous class always reports a short name shaped like
     * `class@anonymous...`, never the literal `elFinderConnector` that a
     * plain `new \elFinderConnector(...)` produces. That is exactly the
     * distinction a regression of the old CSRF-disabling override would
     * break, and reflection on a REAL object built by the REAL source line
     * (via extractConnectorConstructionSource()) means this test exercises
     * production code, not a description of it.
     */
    public function testElfinderConnectionConstructsARealConnectorWithoutAnAnonymousCsrfOverride(): void
    {
        $opts = [
            'debug' => false,
            'roots' => [
                ['driver' => 'LocalFileSystem', 'path' => $this->sandbox, 'URL' => 'http://localhost/media/'],
            ],
        ];

        $connector = eval('return ' . $this->extractConnectorConstructionSource() . ';');

        $this->assertInstanceOf(
            \elFinderConnector::class,
            $connector,
            'elfinderConnection() must still construct an \elFinderConnector.',
        );
        $this->assertSame(
            'elFinderConnector',
            (new ReflectionObject($connector))->getShortName(),
            "elfinderConnection() must construct a plain \\elFinderConnector, not an anonymous subclass -- " .
            'an anonymous subclass overriding validateCsrfToken()/issueCsrfToken() would silently disable ' .
            "elFinder's own CSRF protection, which is this route's only write-side CSRF guard because " .
            'MediaConfig::$csrfExcept excepts it from CI4\'s own CSRF check.',
        );
    }

    /**
     * elFinderConnector::run() always exit()s, so CI4's after-filters (which
     * would otherwise rotate the CI4 CSRF token per .env:118's
     * `security.regenerate = true`) never run on this route. Removing this
     * exception breaks the second write request in any session, because the
     * client is never handed a fresh CI4 token to send with it -- see the
     * class docblock for the full chain.
     */
    public function testCsrfExceptStillListsTheElfinderConnectionRoute(): void
    {
        $csrfExcept = (new MediaConfig())->csrfExcept;

        $this->assertContains(
            'backend/media/elfinderConnection',
            $csrfExcept,
            'MediaConfig::$csrfExcept must keep excepting backend/media/elfinderConnection from CI4\'s CSRF ' .
            "check -- elFinderConnector::run() always exit()s, so CI4's own after-filters (including the " .
            'token-rotation filter) never run on this endpoint, and removing the exception breaks every ' .
            'write request after the first.',
        );
    }

    /**
     * Extracts the real, unmodified `$connector = ...;` expression straight
     * out of Media::elfinderConnection()'s source via the tokenizer, the same
     * technique MediaUploadIntegrationTest::extractUploadPresaveClosureSource()
     * uses for the upload.presave closure. Depth-tracks on braces/parens so it
     * captures either a plain `new \elFinderConnector(...)` call or a whole
     * anonymous-class expression (`new class(...) extends \elFinderConnector
     * { ... }`) with equal fidelity -- both must parse identically for the
     * control experiment (deliberately reverting to the anonymous override) to
     * be meaningful.
     */
    private function extractConnectorConstructionSource(): string
    {
        $path   = ROOTPATH . 'modules/Media/Controllers/Media.php';
        $tokens = token_get_all(file_get_contents($path));

        $exprStart = null;
        foreach ($tokens as $i => $token) {
            if (! is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$connector') {
                continue;
            }

            for ($j = $i + 1; isset($tokens[$j]); $j++) {
                $next = $tokens[$j];
                if (is_array($next) && $next[0] === T_WHITESPACE) {
                    continue;
                }
                $text = is_array($next) ? $next[1] : $next;
                if ($text === '=') {
                    $exprStart = $j + 1;
                }
                break;
            }

            if ($exprStart !== null) {
                break;
            }
        }

        if ($exprStart === null) {
            throw new RuntimeException(
                'Could not locate a "$connector = ...;" assignment in Media.php -- has ' .
                'elfinderConnection() stopped naming the connector variable $connector?',
            );
        }

        $source = '';
        $depth  = 0;

        for ($i = $exprStart; isset($tokens[$i]); $i++) {
            $token = $tokens[$i];
            $text  = is_array($token) ? $token[1] : $token;

            if ($text === '(' || $text === '{') {
                $depth++;
            } elseif ($text === ')' || $text === '}') {
                $depth--;
            } elseif ($text === ';' && $depth === 0) {
                break;
            }

            $source .= $text;
        }

        return trim($source);
    }

    /**
     * Recursively deletes a sandbox directory created under sys_get_temp_dir().
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
