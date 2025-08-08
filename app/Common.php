<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the frameworks
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @link: https://codeigniter4.github.io/CodeIgniter4/
 */

use CodeIgniter\I18n\Time;

if (!function_exists('clearFilter')) {
    /**
     * @param array $array
     * @return array
     */
    function clearFilter(array $array)
    {
        $clear = array_filter(
            $array,
            function ($value) {
                return $value !== '';
            }
        );

        return array_filter(
            $clear,
            function ($value) {
                return $value !== null;
            }
        );
    }
}

if (!function_exists('show_404')) {
    /**
     * @return mixed
     */
    function show_404()
    {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}

if (!function_exists('seflink')) {
    /**
     * Generates a slug from a given string.
     * Supports Turkish and other languages (Cyrillic, Arabic, Greek...) using transliterator.
     *
     * @param string $str
     * @param array $options
     * @return string
     */
    function seflink(string $str, array $options = []): string
    {
        $defaults = [
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => [],
        ];
        $options = array_merge($defaults, $options);

        // Turkish character map
        $turkishMap = [
            'ş' => 's', 'Ş' => 's',
            'ı' => 'i', 'İ' => 'i',
            'ç' => 'c', 'Ç' => 'c',
            'ü' => 'u', 'Ü' => 'u',
            'ö' => 'o', 'Ö' => 'o',
            'ğ' => 'g', 'Ğ' => 'g'
        ];
        $str = str_replace(array_keys($turkishMap), $turkishMap, $str);

        // Convert encoding
        $str = mb_convert_encoding($str, 'UTF-8', mb_detect_encoding($str));

        // Apply custom replacements
        if (!empty($options['replacements'])) {
            $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
        }

        // Apply transliteration (if possible)
        if (function_exists('transliterator_transliterate')) {
            $str = transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove', $str);
        }

        // Replace non-alphanumeric characters with delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate to limit
        if ($options['limit']) {
            $str = mb_substr($str, 0, $options['limit'], 'UTF-8');
        }

        // Trim delimiter from ends
        $str = trim($str, $options['delimiter']);

        // Lowercase if needed
        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }
}

if (!function_exists('menu')) {
    function menu($kategori, $parent = null)
    {
        foreach ($kategori as $menu) {
            if ($menu->parent == $parent) {
                echo '<li class="';
                if (empty($menu->parent)) echo 'nav-item';
                if ((bool)$menu->hasChildren === true) echo ' dropdown';
                echo '">';
                echo '<a class="';
                if (empty($menu->parent)) echo 'nav-link';
                else echo 'dropdown-item';
                if ((bool)$menu->hasChildren === true) echo ' dropdown-toggle';
                echo '" href="' . site_url($menu->seflink) . '"';
                if ((bool)$menu->hasChildren === true) echo ' role="button" data-bs-toggle="dropdown" aria-expanded="false"';
                echo '>' . $menu->title . '</a>';
                if ((bool)$menu->hasChildren === true) echo '<ul class="dropdown-menu dropdown-menu-end">';
                menu($kategori, $menu->id);
                if ((bool)$menu->hasChildren === true) echo '</ul>';
                echo '</li>';
            }
        }
    }
}

if (!function_exists('_printr')) {
    function _printr($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}

if (!function_exists('_printrDie')) {
    function _printrDie($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        die();
    }
}

if (!function_exists('compressAndOverwriteImage')) {
    function compressAndOverwriteImage($path, $source, $quality = 100)
    {
        $image = imagecreatefromwebp($path . $source);
        $tempImage = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopyresampled($tempImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($image), imagesy($image));

        $tempDestination = $path . 'temp_image.webp';
        imagewebp($tempImage, $tempDestination, $quality);

        // Orijinal resmi silme
        unlink($path . $source);

        // Geçici resmi orijinal dosya adıyla değiştirme
        rename($tempDestination, $path . $source);

        return $source;
    }
}

if (!function_exists('getGitVersion')) {
    function getGitVersion(): string
    {
        // Git versiyonunu almak için shell_exec'i bir metot içinde çağırıyoruz.
        $commitHash = shell_exec('git rev-parse --short HEAD');
        $branchName = shell_exec('git rev-parse --abbrev-ref HEAD');
        $versionTag = shell_exec('git describe --tags --abbrev=0');

        // Eğer herhangi bir hata olursa null kontrolü yapabilirsiniz
        if (!$commitHash || !$branchName || !$versionTag) {
            return 'Version not available';
        }

        // Versiyon bilgisini döndürüyoruz
        return $versionTag . " (Branch: " . $branchName . " @ " . $commitHash . ")";
    }
}

if (!function_exists('hasFilesInFolder')) {
    function hasFilesInFolder(string $folderPath): bool
    {
        try {
            $iterator = new FilesystemIterator($folderPath, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    return true; // İlk dosyada döner
                }
            }
        } catch (UnexpectedValueException $e) {
            // Klasör bulunamadıysa veya açılamadıysa
            return false;
        }

        return false; // Hiç dosya yoksa
    }
}
