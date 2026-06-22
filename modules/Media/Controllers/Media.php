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
        'archive', 'extract', 'resize', 'chmod', 'put', 'edit', 'netmount',
        'trash', 'restore',
    ];

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
        // elFinder's disabled/accessControl mechanisms can be bypassed
        // (uploadDeny is only valid for the upload command, not for mkfile/put).
        // Therefore, we check the cmd parameter BEFORE reaching elFinder.
        // getPost('cmd') is intentional: the route is POST-only and elFinder
        // reads the command from the POST body. Do NOT switch to getVar() —
        // it would introduce a parsing inconsistency with elFinder.
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
                    'uploadDeny' => array('all'),                // All Mimetypes not allowed to upload
                    'uploadAllow' => (array)$allowedFiles, // Mimetype `image` and `text/plain` allowed to upload
                    'uploadOrder' => array('deny', 'allow'),      // allowed Mimetype `image` and `text/plain` only
                    'accessControl' => array($this, 'elfinderAccess'), // disable and hide dot starting files (OPTIONAL)
                    'disabled' => $disabled,
                    'dirrm' => true
                ),
                // Trash volume
                array(
                    'id' => '1',
                    'driver' => 'Trash',
                    'path' => ROOTPATH . '/public/media/.trash/',
                    'tmbURL' => site_url('media/.trash/.tmb/'),
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
                    'uploadDeny' => array('all'),                // Recomend the same settings as the original volume that uses the trash
                    'uploadAllow' => (array)$allowedFiles, // Same as above
                    'uploadOrder' => array('deny', 'allow'),      // Same as above
                    'accessControl' => array($this, 'elfinderAccess'),                   // Same as above
                    'disabled' => $disabled
                )
            ),
            'bind' => array(
                'upload.presave' => array(function (&$thash, &$name, $tmpname, $elfinder, $volume) {
                    if ((bool)$this->defData['settings']->convertWebp->scalar===true) {
                        $char_map = ['.jpg' => '.webp', '.png' => '.webp', '.jpeg' => '.webp'];
                        $ext = strtolower(strrchr($name, '.'));
                        if (in_array($ext, array('.jpg', '.jpeg', '.png'))) {
                            $webpName = str_replace(array_keys($char_map), $char_map, $name);
                            $webpPath = dirname($tmpname) . DIRECTORY_SEPARATOR . $webpName;
                            $img = new \claviska\SimpleImage();
                            $img->fromFile($tmpname)->toFile($webpPath, 'image/webp', ['quality' => 80]);
                            $name = $webpName;
                            $tmpname = $webpPath;
                        }
                    }
                })
            )
        );

        // CI4 Shield auth + backendGuard already protect the session.
        // elFinder's internal CSRF was bypassed because it conflicts with the CI4 session.
        $connector = new class(new \elFinder($opts)) extends \elFinderConnector {
            protected function validateCsrfToken(): bool { return true; }
            protected function issueCsrfToken(): string { return ''; }
        };
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
        return $basename[0] === '.'                  // if file/folder begins with '.' (dot)
            && strlen($relpath) !== 1           // but with out volume root
            ? !($attr == 'read' || $attr == 'write') // set read+write to false, other (locked+hidden) set to true
            :  null;                                 // else elFinder decide it itself
    }
}
