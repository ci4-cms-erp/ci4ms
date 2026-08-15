<?php

declare(strict_types=1);

namespace Modules\Backend\Libraries;

use ZipArchive;

/**
 * Pre-extraction ZIP hardening shared by the theme and module upload paths
 * (Modules\Theme::upload and Modules\Methods::moduleUpload), which had each
 * grown their own near-identical copy of this loop. This is the UNION of both
 * copies' entry checks: the module path already rejected Windows drive-letter
 * prefixes and NUL bytes while the theme path did not, so folding them here
 * closes that gap for themes too.
 *
 * validate() is side-effect free -- it inspects an already-open archive and
 * never extracts -- so it is unit-testable against crafted archives without
 * touching the filesystem. Callers keep their own domain checks on top (theme:
 * info.xml/screenshot metadata + public/ extension allowlist; module: single
 * top-level PascalCase folder + realpath containment) and their own
 * post-extraction symlink walk.
 */
class ZipSecurityValidator
{
    public const OK             = 'ok';
    public const PATH_TRAVERSAL = 'path_traversal';
    public const ZIP_BOMB       = 'zip_bomb';
    public const SYMLINK        = 'symlink';

    /**
     * Validate every entry of an open archive before extraction. Checks run in
     * the same per-entry order both original copies used (path -> size ->
     * symlink), so the first-failure reason is preserved.
     *
     * @return array{ok: bool, reason: string, entry: string|null}
     */
    public function validate(ZipArchive $zip, int $maxEntryBytes, int $maxTotalBytes): array
    {
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if (
                $entryName === false
                || $entryName === ''
                || preg_match('/^[\\/\\\\]/', $entryName)                    // absolute path
                || preg_match('/^[A-Za-z]:[\\/\\\\]/', $entryName)           // Windows drive letter
                || preg_match('/(^|[\\/\\\\])\.\.([\\/\\\\]|$)/', $entryName) // .. segment anywhere
                || str_contains($entryName, "\0")                            // null byte
            ) {
                return ['ok' => false, 'reason' => self::PATH_TRAVERSAL, 'entry' => $entryName === false ? null : $entryName];
            }

            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $size = (int) ($stat['size'] ?? 0);
            if ($size > $maxEntryBytes) {
                return ['ok' => false, 'reason' => self::ZIP_BOMB, 'entry' => $entryName];
            }
            $totalUncompressed += $size;
            if ($totalUncompressed > $maxTotalBytes) {
                return ['ok' => false, 'reason' => self::ZIP_BOMB, 'entry' => $entryName];
            }

            // Entry-level symlink rejection. Both original copies read this
            // from statIndex()['external_attr'], but statIndex() never returns
            // that key, so their check was a dead no-op and (had it fired)
            // masked with 0xA000, which also matches regular files (S_IFREG
            // 0x8000). Read the attributes explicitly instead and compare the
            // full S_IFMT (0xF000) nibble against S_IFLNK (0xA000), gated on
            // Unix so DOS/Windows external attrs are not misread as a mode.
            // This is now a functional pre-extraction guard; the callers still
            // run their post-extraction is_link() walk as defense in depth.
            $opsys = 0;
            $attr  = 0;
            if (
                $zip->getExternalAttributesIndex($i, $opsys, $attr)
                && $opsys === ZipArchive::OPSYS_UNIX
                && ((($attr >> 16) & 0xF000) === 0xA000)
            ) {
                return ['ok' => false, 'reason' => self::SYMLINK, 'entry' => $entryName];
            }
        }

        return ['ok' => true, 'reason' => self::OK, 'entry' => null];
    }
}
