<?php

namespace Modules\Media\Controllers;

/**
 * Media Controller for handling media management and elFinder integration.
 *
 * This controller extends the BaseController from the Backend module and provides
 * functionalities for displaying the media view and managing file operations via elFinder.
 *
 * Methods:
 * - index(): Renders the media view page.
 * - elfinderConnection(): Configures and initializes elFinder file manager with custom settings,
 *   including allowed file types, trash management, and optional WebP conversion for image uploads.
 * - elfinderAccess($attr, $path, $data, $volume, $isDir, $relpath): Access control callback for elFinder,
 *   restricting access to files/folders starting with a dot ('.').
 *
 * @package Modules\Media\Controllers
 */
class Media extends \Modules\Backend\Controllers\BaseController
{
    protected bool $mediaCanWrite = false;

    /**
     * elFinder write-capable commands. Single source of truth feeding both
     * Layer 2 (controller 403 gate) and Layer 3 (elFinder disabled list).
     *
     * NOTE: The authoritative guarantee is elfinderAccess() returning write=false
     * (command-independent). This list is defense-in-depth only — if an elFinder
     * upgrade introduces a new write command, it must be added here as well.
     *
     * @var list<string>
     */
    private const WRITE_COMMANDS = [
        'mkdir', 'mkfile', 'rename', 'rm', 'upload', 'paste', 'duplicate',
        'archive', 'extract', 'resize', 'chmod', 'put', 'netmount', 'editor',
        // 'trash'/'restore' are client-side UI macros, not real elFinder server
        // commands (elFinder.class.php's $commands table has no such keys) —
        // the writes they trigger already go through 'paste'/'rm' above. Kept
        // as documented dead entries instead of silently dropped so a future
        // reader doesn't mistake their absence for an oversight.
        'trash', 'restore',
    ];

    /**
     * Extensions that may never be written into the media tree.
     *
     * The MIME allowlist alone does not cover this: elFinder only maps `php` to
     * text/x-php internally, so every other script extension falls through to
     * text/plain — which `settings.allowedFiles` permits — and .phtml, .php5,
     * .pht and .phar are accepted. They are not executable under the current
     * nginx location (`[^/]\.php(/|$)`) plus PHP-FPM's default
     * security.limit_extensions, but neither of those lives in this repository,
     * and .phtml is a stock PHP handler extension on Apache. Blocking the write
     * keeps a dormant payload off disk instead of relying on server config.
     *
     * Mirrors the FilesMatch list in public/media/.htaccess deliberately.
     *
     * @var list<string>
     */
    private const DENIED_EXTENSIONS = [
        'php', 'php0', 'php1', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'php9',
        'phtml', 'phtm', 'phar', 'phps', 'pht', 'phpt', 'inc', 'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx',
        'sh', 'bat', 'exe', 'htaccess', 'htpasswd', 'ini', 'shtml', 'shtm', 'stm', 'hta',
    ];

    /**
     * Largest single upload elFinder will accept.
     */
    private const UPLOAD_MAX_SIZE = '32M';

    /**
     * Renders the media manager (elFinder) backend page.
     *
     * @return string The rendered media view.
     */
    public function index()
    {
        return view('Modules\Media\Views\media', $this->defData);
    }

    /**
     * elFinder connector endpoint (POST-only, CSRF-excepted in MediaConfig).
     *
     * Enforces a three-layer access-control chain so only users with the
     * `media.media.create` permission (or superadmin) may run write commands:
     *  - Layer 1: resolve write capability from the permission/group.
     *  - Layer 2: reject write commands with HTTP 403 before elFinder runs.
     *  - Layer 3: hand the write-command blacklist to elFinder's disabled list.
     * The authoritative server-side guarantee remains elfinderAccess().
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|null A 403 response when a
     *         write command is denied; otherwise the elFinder connector writes
     *         the response directly and the method returns null.
     */
    public function elfinderConnection()
    {
        // ── Layer 1: Access control ───────────────────────────────────────
        // Since the elFinder connector can execute all file commands,
        // we bind the write capability to the `media.media.create` permission.
        $user                = auth()->user();
        $isSuperadmin        = $user->inGroup('superadmin');
        $this->mediaCanWrite = $isSuperadmin || $user->can('media.media.create');

        // ── Layer 2: Blocking write commands at the Controller level ──────
        // Defense-in-depth ahead of elFinder's own gates. (The MIME allowlist
        // does reach mkfile in this elFinder version — elFinderVolumeDriver
        // calls allowPutMime() at mkfile() — but it only stops `.php`; see
        // DENIED_EXTENSIONS for why that is not enough.)
        // Therefore, we check the cmd parameter BEFORE reaching elFinder.
        // getPost('cmd') only sees the request BODY. elFinderConnector::run()
        // (elFinderConnector.class.php:320-321) actually resolves the command
        // from array_merge($_GET, $_POST), so `POST ...?cmd=mkdir` with no
        // `cmd` in the body sails past this check (isWriteBlocked('') is
        // false). That gap is NOT this method's security boundary: Layer 3's
        // `disabled` list (self::WRITE_COMMANDS) and elfinderAccess()'s own
        // authoritative write=false guarantee (see its docblock below) reject
        // the command anyway, and the same request shape never registers the
        // upload.presave listener either — elFinder only binds a handler
        // whose command name matches $_POST['cmd']/$_GET['cmd']. Layer 2 is
        // an early-403 convenience for the common case, not a guarantee; do
        // not remove Layer 3 or elfinderAccess() on the assumption Layer 2
        // already covers this.
        $cmd = $this->request->getPost('cmd') ?? '';
        if ($this->isWriteBlocked($cmd)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => [lang('Backend.err403Heading')]]);
        }

        // ── Layer 3: elFinder disabled list (UI + server side) ────────────
        $disabled = $this->mediaCanWrite ? [] : self::WRITE_COMMANDS;

        $allowedFiles = $this->defData['settings']->allowedFiles;
        $opts = array(
            'debug' => (ENVIRONMENT !== 'production'),
            'roots' => array(
                // Items volume
                array(
                    'driver' => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
                    'path' => ROOTPATH . '/public/media/',                 // path to files (REQUIRED)
                    'URL' => site_url('media/'), // URL to files (REQUIRED)
                    'trashHash' => 't1_Lw',                     // elFinder's hash of trash folder
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                    'dirrm' => true
                ) + $this->volumeSecurityOptions($allowedFiles, $disabled),
                // Trash volume
                array(
                    'id' => '1',
                    'driver' => 'Trash',
                    'path' => ROOTPATH . '/public/media/.trash/',
                    'tmbURL' => site_url('media/.trash/.tmb/'),
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                ) + $this->volumeSecurityOptions($allowedFiles, $disabled)
            ),
            'bind' => array(
                // elFinder passes $name straight from the request's `name[]`
                // field here — before nameAccepted()/allowCreate() ever run —
                // so this closure must defend itself: basename() first,
                // isDeniedName() next, then a realpath-verified write boundary
                // before ever touching disk via SimpleImage.
                'upload.presave' => array(function (&$thash, &$name, $tmpname, $elfinder, $volume) {
                    $name = basename($name);

                    if ($this->isDeniedName($name)) {
                        throw new \elFinderTriggerException();
                    }

                    if ((bool)($this->defData['settings']->convertWebp ?? false)===true) {
                        $char_map = ['.jpg' => '.webp', '.png' => '.webp', '.jpeg' => '.webp'];
                        $ext = strtolower(strrchr($name, '.'));
                        if (in_array($ext, array('.jpg', '.jpeg', '.png'))) {
                            $webpName = str_replace(array_keys($char_map), $char_map, $name);

                            $tmpDir = realpath(dirname($tmpname));
                            $webpPath = $tmpDir !== false ? $tmpDir . DIRECTORY_SEPARATOR . $webpName : false;

                            // Path-boundary check by realpath equality, not string
                            // prefix (CLAUDE.md Fileeditor convention): $webpPath
                            // does not exist yet, so its *parent* is resolved and
                            // compared against the source tmp dir instead.
                            if ($tmpDir === false || $webpPath === false || realpath(dirname($webpPath)) !== $tmpDir) {
                                throw new \elFinderTriggerException();
                            }

                            try {
                                $img = new \claviska\SimpleImage();
                                $img->fromFile($tmpname)->toFile($webpPath, 'image/webp', ['quality' => 80]);
                            } catch (\Throwable $e) {
                                throw new \elFinderTriggerException();
                            }

                            $name = $webpName;
                            $tmpname = $webpPath;
                        }
                    }
                })
            )
        );

        // elFinder's own CSRF protection (X-elFinder-CSRF header, random_bytes(32)
        // token, hash_equals() validation, 900s TTL) runs on top of CI4 Shield
        // auth + backendGuard. elFinderSession::start() only calls session_start()
        // when session_status() !== PHP_SESSION_ACTIVE, so it does not conflict
        // with the already-active CI4 session — there is no reason to disable it.
        // This endpoint is CSRF-excepted from CI4's own token only
        // (MediaConfig::$csrfExcept) because elFinderConnector::output() exits
        // the request before CI4's after-filters (which rotate the CI4 token)
        // ever run; elFinder's independent token mechanism is the real guard here.
        $connector = new \elFinderConnector(new \elFinder($opts));
        $connector->run();

        // Unreachable in practice — elFinderConnector::output() ends in exit();
        // kept so the declared return type is satisfied without behaviour change.
        return null;
    }

    /**
     * Decides whether the given elFinder command must be blocked at the
     * controller layer (Layer 2) for the current user.
     *
     * Pure, side-effect-free helper so the access-control gate can be
     * unit-tested without instantiating the elFinder connector (whose output
     * routine calls exit()).
     *
     * @param string $cmd elFinder command read from the POST body.
     *
     * @return bool True when the command is write-capable and the user lacks
     *              write permission; false otherwise.
     */
    protected function isWriteBlocked(string $cmd): bool
    {
        return ! $this->mediaCanWrite && in_array($cmd, self::WRITE_COMMANDS, true);
    }

    /**
     * Decides whether a file name may never be written into the media tree.
     *
     * Every dot-separated segment is checked, not just the last one, so
     * `shell.php.jpg` is refused as well: which segment a web server treats as
     * the handler depends on its configuration, and this gate must not depend
     * on that. Trailing dots and spaces are stripped first because Windows and
     * some upload paths silently drop them.
     *
     * Pure and side-effect free so it can be unit-tested without the elFinder
     * connector, whose output routine calls exit().
     *
     * @param string $basename File or directory name, without its directory.
     */
    /**
     * Upload-security options shared by every elFinder volume.
     *
     * Extracted so the settings can be asserted without booting the connector.
     * `uploadOrder` must stay deny-first: with allow-first an entry missing from
     * the allowlist would be permitted by default.
     *
     * @param mixed        $allowedFiles Permitted MIME types from settings.
     * @param list<string> $disabled     elFinder commands to switch off.
     *
     * @return array<string, mixed>
     */
    protected function volumeSecurityOptions($allowedFiles, array $disabled): array
    {
        return array(
            'uploadDeny'    => array('all'),
            'uploadAllow'   => (array) $allowedFiles,
            'uploadOrder'   => array('deny', 'allow'),
            'uploadMaxSize' => self::UPLOAD_MAX_SIZE,
            'accessControl' => array($this, 'elfinderAccess'),
            'acceptedName'  => array($this, 'isNameAccepted'),
            'disabled'      => $disabled,
        );
    }

    /**
     * elFinder acceptedName validator — the write-path counterpart to
     * elfinderAccess().
     *
     * elFinderVolumeDriver::nameAccepted() calls this on every write path,
     * including upload(), which never reaches allowCreate()/accessControl()
     * (only mkdir/mkfile/rename/paste/archive do). Without this, isDeniedName()
     * is never consulted for uploads and DENIED_EXTENSIONS is bypassable via
     * multi-segment names such as `shell.php.png`.
     *
     * Also preserves elFinder's own default acceptedName behaviour (reject
     * empty names and names starting with '.') since this callable replaces
     * that default outright rather than adding to it — dropping it would
     * weaken an existing control instead of only adding a new one.
     *
     * Must be public: elFinderVolumeDriver::nameAccepted() invokes it as an
     * external array callable (`is_callable([$this, 'isNameAccepted'])`),
     * exactly like elfinderAccess() below — protected visibility makes
     * is_callable() report false from that external scope and silently
     * turns this into a no-op (elFinder falls back to accepting everything).
     *
     * @param string $name Candidate file or directory name.
     *
     * @return bool True when the name may be written; false to reject it.
     */
    public function isNameAccepted(string $name): bool
    {
        return $name !== '' && $name[0] !== '.' && ! $this->isDeniedName($name);
    }

    protected function isDeniedName(string $basename): bool
    {
        $normalized = strtolower(rtrim(trim($basename), '. '));
        if ($normalized === '') {
            return false;
        }

        $segments = explode('.', $normalized);
        array_shift($segments);

        foreach ($segments as $segment) {
            if (in_array($segment, self::DENIED_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * elFinder access-control callback — the authoritative server-side guarantee.
     *
     * Denies every `write` attribute for users without write permission
     * regardless of the command, and hides dot-prefixed files/folders.
     *
     * @param string $attr    Requested attribute (read|write|locked|hidden).
     * @param string $path    Absolute path of the target.
     * @param mixed  $data    Volume-specific data (unused).
     * @param mixed  $volume  Active elFinder volume driver (unused).
     * @param bool   $isDir   Whether the target is a directory (unused).
     * @param string $relpath Path relative to the volume root.
     *
     * @return bool|null False to deny, true to grant, null to let elFinder decide.
     */
    public function elfinderAccess($attr, $path, $data, $volume, $isDir, $relpath)
    {
        // Block all write operations for users without write permission.
        // Since elFinder queries the 'write' attribute of the target/parent folder
        // via this callback for every write operation, returning false rejects
        // mkfile/put/rm/upload/rename/paste entirely on the server side.
        if (! $this->mediaCanWrite && $attr === 'write') {
            return false;
        }

        $basename = basename($path);

        if ($attr === 'write' && $this->isDeniedName($basename)) {
            return false;
        }

        return $basename[0] === '.'                  // if file/folder begins with '.' (dot)
            && strlen($relpath) !== 1           // but with out volume root
            ? !($attr == 'read' || $attr == 'write') // set read+write to false, other (locked+hidden) set to true
            :  null;                                 // else elFinder decide it itself
    }
}
