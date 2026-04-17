<?php

if (!function_exists('findDuplicateSubfolders')) {
    function findDuplicateSubfolders(array $baseDirs): array
    {
        $subfolderMap = [];

        foreach ($baseDirs as $dir) {
            if (!is_dir($dir)) continue;

            $iterator = new DirectoryIterator($dir);
            foreach ($iterator as $item) {
                if ($item->isDot() || !$item->isDir()) continue;

                $folderName = $item->getFilename();
                $subfolderMap[$folderName][] = $dir;
            }
        }

        return array_filter($subfolderMap, fn($dirs) => count($dirs) > 1);
    }

    if (!function_exists('install_theme_from_tmp')) {
        function install_theme_from_tmp(string $themeName = 'ci4ms'): array
        {
            $log = [];
            $tmpPath    = rtrim(WRITEPATH . 'tmp/' . $themeName, '/');
            $baseApp    = rtrim(APPPATH, '/');
            $basePublic = rtrim(ROOTPATH . 'public', '/');

            // ── Security: Allowed extensions for public/ directory ──
            $allowedPublicExtensions = [
                'css', 'js', 'map',
                'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'bmp', 'avif',
                'woff', 'woff2', 'ttf', 'eot', 'otf',
                'xml', 'json', 'txt', 'md',
                'mp4', 'webm', 'ogg', 'mp3', 'wav',
                'pdf',
            ];
            // ── End Security ────────────────────────────────────────

            $appFolders = ['Config', 'Controllers', 'Helpers', 'Libraries', 'Views', 'Database/Migrations'];

            // folders under app
            foreach ($appFolders as $folder) {
                $src = "$tmpPath/app/$folder";
                // Find the correct directory root if the boilerplate standard exists in the ZIP
                if (is_dir("$src/templates/$themeName")) {
                    $src = "$src/templates/$themeName";
                }

                $dst = "$baseApp/$folder/templates/$themeName";
                $log = array_merge($log, smart_move($src, $dst));
            }

            // Move public/assets (with extension filter)
            $srcAssets = "$tmpPath/public/assets";
            if (is_dir("$tmpPath/public/templates/$themeName/assets")) {
                $srcAssets = "$tmpPath/public/templates/$themeName/assets";
            }

            $dstAssets = "$basePublic/templates/$themeName/assets";
            $log = array_merge($log, smart_move($srcAssets, $dstAssets, $allowedPublicExtensions));

            // public root files (info.xml, screenshot.png etc.)
            $publicSearchDir = "$tmpPath/public";
            if (is_dir("$tmpPath/public/templates/$themeName")) {
                $publicSearchDir = "$tmpPath/public/templates/$themeName";
            }

            foreach (glob("$publicSearchDir/*.*") as $file) {
                if (is_dir($file) || basename($file) === 'assets') continue;

                // ── Security: Filter public root files by extension ──
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!empty($ext) && !in_array($ext, $allowedPublicExtensions, true)) {
                    $log[] = "⛔ Blocked (forbidden extension): " . basename($file);
                    continue;
                }
                // ── End Security ─────────────────────────────────────

                $targetDir = "$basePublic/templates/$themeName";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $to = $targetDir . '/' . basename($file);
                rename($file, $to);
                $log[] = "📄 Moved: " . basename($file);
            }

            return $log;
        }
    }

    if (!function_exists('smart_move')) {
        /**
         * Recursively move files from source to target directory.
         *
         * @param string     $source              Source directory path
         * @param string     $target              Target directory path
         * @param array|null $allowedExtensions    If provided, only files with these extensions will be moved.
         *                                        This is used as a defense-in-depth measure for public/ directory writes.
         * @return array     Log messages
         */
        function smart_move(string $source, string $target, ?array $allowedExtensions = null): array
        {
            $log = [];

            if (!is_dir($source)) {
                return ["⛔ Skipped, folder does not exist: $source"];
            }

            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($rii as $fileInfo) {
                $subPath = str_replace($source, '', $fileInfo->getPathname());
                $toPath = $target . $subPath;

                if ($fileInfo->isDir()) {
                    if (!is_dir($toPath)) {
                        mkdir($toPath, 0777, true);
                        $log[] = "📁 Folder created: $toPath";
                    }
                } else {
                    // ── Security: Extension filter (defense-in-depth) ──
                    if ($allowedExtensions !== null) {
                        $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                        if (!empty($ext) && !in_array($ext, $allowedExtensions, true)) {
                            $log[] = "⛔ Blocked (forbidden extension): " . $fileInfo->getFilename();
                            continue;
                        }
                    }
                    // ── End Security ───────────────────────────────────

                    $toDir = dirname($toPath);
                    if (!is_dir($toDir)) {
                        mkdir($toDir, 0777, true);
                    }

                    rename($fileInfo->getPathname(), $toPath);
                    $log[] = "📄 Moved: " . $fileInfo->getFilename();
                }
            }

            return $log;
        }
    }

    function deleteFldr($folderPath)
    {
        if (!is_dir($folderPath)) {
            return true;
        }

        $items = scandir($folderPath);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = $folderPath . DIRECTORY_SEPARATOR . $item;

                if (is_dir($fullPath)) {
                    deleteFldr($fullPath); // recursive
                } else {
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }
        }

        if (is_dir($folderPath)) {
            @rmdir($folderPath); // finally delete itself
        }

        return true;
    }

    if (!function_exists('remove_theme_files')) {
        function remove_theme_files(string $themeName): array
        {
            $log = [];
            $baseApp    = rtrim(APPPATH, '/');
            $basePublic = rtrim(ROOTPATH . 'public', '/');

            $appFolders = ['Config', 'Controllers', 'Helpers', 'Libraries', 'Views', 'Database/Migrations'];

            // delete folders under app
            foreach ($appFolders as $folder) {
                $target = "$baseApp/$folder/templates/$themeName";
                if (is_dir($target)) {
                    deleteFldr($target);
                    $log[] = lang('Theme.deleted', ["app/$folder/templates/$themeName"]);
                }
            }

            // public templates sil
            $publicTarget = "$basePublic/templates/$themeName";
            if (is_dir($publicTarget)) {
                deleteFldr($publicTarget);
                $log[] = "🗑️ Deleted: public/templates/$themeName";
            }

            return $log;
        }
    }
}
