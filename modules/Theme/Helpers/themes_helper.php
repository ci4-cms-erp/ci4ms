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

            $appFolders = ['Config', 'Controllers', 'Helpers', 'Libraries', 'Views', 'Database/Migrations'];

            // app altı klasörler
            foreach ($appFolders as $folder) {
                $src = "$tmpPath/app/$folder";
                // ZIP içerisinde boilerplate standardı varsa, doğru dizin kökünü bul
                if (is_dir("$src/templates/$themeName")) {
                    $src = "$src/templates/$themeName";
                }
                
                $dst = "$baseApp/$folder/templates/$themeName";
                $log = array_merge($log, smart_move($src, $dst));
            }

            // public/assets taşı
            $srcAssets = "$tmpPath/public/assets";
            if (is_dir("$tmpPath/public/templates/$themeName/assets")) {
                $srcAssets = "$tmpPath/public/templates/$themeName/assets";
            }
            
            $dstAssets = "$basePublic/templates/$themeName/assets";
            $log = array_merge($log, smart_move($srcAssets, $dstAssets));

            // public root dosyaları (info.xml, screenshot.png vs)
            $publicSearchDir = "$tmpPath/public";
            if (is_dir("$tmpPath/public/templates/$themeName")) {
                $publicSearchDir = "$tmpPath/public/templates/$themeName";
            }
            
            foreach (glob("$publicSearchDir/*.*") as $file) {
                if (is_dir($file) || basename($file) === 'assets') continue;

                $targetDir = "$basePublic/templates/$themeName";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $to = $targetDir . '/' . basename($file);
                rename($file, $to);
                $log[] = "📄 Taşındı: " . basename($file);
            }

            return $log;
        }
    }

    if (!function_exists('smart_move')) {
        function smart_move(string $source, string $target): array
        {
            $log = [];

            if (!is_dir($source)) {
                return ["⛔ Atlandı, klasör yok: $source"];
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
                        $log[] = "📁 Klasör oluşturuldu: $toPath"; // İsteğe bağlı log
                    }
                } else {
                    $toDir = dirname($toPath);
                    if (!is_dir($toDir)) {
                        mkdir($toDir, 0777, true);
                    }

                    rename($fileInfo->getPathname(), $toPath); // veya copy()
                    $log[] = "📄 Taşındı: " . $fileInfo->getFilename(); // İsteğe bağlı log
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
            @rmdir($folderPath); // en sonunda kendisini sil
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

            // app altı klasörleri sil
            foreach ($appFolders as $folder) {
                $target = "$baseApp/$folder/templates/$themeName";
                if (is_dir($target)) {
                    deleteFldr($target);
                    $log[] = "🗑️ Silindi: app/$folder/templates/$themeName";
                }
            }

            // public templates sil
            $publicTarget = "$basePublic/templates/$themeName";
            if (is_dir($publicTarget)) {
                deleteFldr($publicTarget);
                $log[] = "🗑️ Silindi: public/templates/$themeName";
            }

            return $log;
        }
    }
}
