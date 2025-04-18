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
     * @param $str
     * @param $options
     * @example for $options
     * array(
     *   'delimiter' => '-',
     *   'limit' => null,
     *   'lowercase' => true,
     *   'replacements' => array(),
     *   'transliterate' => true
     * )
     * @return string
     */
    function seflink(string $str, $options = array())
    {
        $str = mb_convert_encoding($str, 'UTF-8', mb_detect_encoding($str));
        $defaults = array(
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => array(),
            'transliterate' => true
        );
        $options = array_merge($defaults, $options);
        $char_map = array(
            /* latin */
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Ă' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ő' => 'O',
            'Ø' => 'O',
            'Ş' => 'S',
            'Ț' => 'T',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ű' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'ă' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ő' => 'o',
            'ø' => 'o',
            'ş' => 's',
            'ț' => 't',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ű' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',
            'ı' => 'i',
            'İ' => 'i',
            'Ğ' => 'g',
            'ğ' => 'g',
            'ş' => 's',

            /* latin_symbols */
            '©' => '(c)'
        );
        if (!empty($options['locale'])) {
            switch ($options['locale']) {
                case 'de':
                    /* German */
                    $char_map = array_merge($char_map, array('Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ẞ' => 'SS'));
                    break;
                case 'el':
                    /* Greek */
                    $char_map = array_merge($char_map, array(
                        'α' => 'a',
                        'β' => 'b',
                        'γ' => 'g',
                        'δ' => 'd',
                        'ε' => 'e',
                        'ζ' => 'z',
                        'η' => 'h',
                        'θ' => '8',
                        'ι' => 'i',
                        'κ' => 'k',
                        'λ' => 'l',
                        'μ' => 'm',
                        'ν' => 'n',
                        'ξ' => '3',
                        'ο' => 'o',
                        'π' => 'p',
                        'ρ' => 'r',
                        'σ' => 's',
                        'τ' => 't',
                        'υ' => 'y',
                        'φ' => 'f',
                        'χ' => 'x',
                        'ψ' => 'ps',
                        'ω' => 'w',
                        'ά' => 'a',
                        'έ' => 'e',
                        'ί' => 'i',
                        'ό' => 'o',
                        'ύ' => 'y',
                        'ή' => 'h',
                        'ώ' => 'w',
                        'ς' => 's',
                        'ϊ' => 'i',
                        'ΰ' => 'y',
                        'ϋ' => 'y',
                        'ΐ' => 'i',
                        'Α' => 'A',
                        'Β' => 'B',
                        'Γ' => 'G',
                        'Δ' => 'D',
                        'Ε' => 'E',
                        'Ζ' => 'Z',
                        'Η' => 'H',
                        'Θ' => '8',
                        'Ι' => 'I',
                        'Κ' => 'K',
                        'Λ' => 'L',
                        'Μ' => 'M',
                        'Ν' => 'N',
                        'Ξ' => '3',
                        'Ο' => 'O',
                        'Π' => 'P',
                        'Ρ' => 'R',
                        'Σ' => 'S',
                        'Τ' => 'T',
                        'Υ' => 'Y',
                        'Φ' => 'F',
                        'Χ' => 'X',
                        'Ψ' => 'PS',
                        'Ω' => 'W',
                        'Ά' => 'A',
                        'Έ' => 'E',
                        'Ί' => 'I',
                        'Ό' => 'O',
                        'Ύ' => 'Y',
                        'Ή' => 'H',
                        'Ώ' => 'W',
                        'Ϊ' => 'I',
                        'Ϋ' => 'Y'
                    ));
                    break;
                case 'ru':
                    /* Russian */
                    $char_map = array_merge($char_map, array(
                        'а' => 'a',
                        'б' => 'b',
                        'в' => 'v',
                        'г' => 'g',
                        'д' => 'd',
                        'е' => 'e',
                        'ё' => 'yo',
                        'ж' => 'zh',
                        'з' => 'z',
                        'и' => 'i',
                        'й' => 'j',
                        'к' => 'k',
                        'л' => 'l',
                        'м' => 'm',
                        'н' => 'n',
                        'о' => 'o',
                        'п' => 'p',
                        'р' => 'r',
                        'с' => 's',
                        'т' => 't',
                        'у' => 'u',
                        'ф' => 'f',
                        'х' => 'h',
                        'ц' => 'c',
                        'ч' => 'ch',
                        'ш' => 'sh',
                        'щ' => 'sh',
                        'ъ' => '',
                        'ы' => 'y',
                        'ь' => '',
                        'э' => 'e',
                        'ю' => 'yu',
                        'я' => 'ya',
                        'А' => 'A',
                        'Б' => 'B',
                        'В' => 'V',
                        'Г' => 'G',
                        'Д' => 'D',
                        'Е' => 'E',
                        'Ё' => 'Yo',
                        'Ж' => 'Zh',
                        'З' => 'Z',
                        'И' => 'I',
                        'Й' => 'J',
                        'К' => 'K',
                        'Л' => 'L',
                        'М' => 'M',
                        'Н' => 'N',
                        'О' => 'O',
                        'П' => 'P',
                        'Р' => 'R',
                        'С' => 'S',
                        'Т' => 'T',
                        'У' => 'U',
                        'Ф' => 'F',
                        'Х' => 'H',
                        'Ц' => 'C',
                        'Ч' => 'Ch',
                        'Ш' => 'Sh',
                        'Щ' => 'Sh',
                        'Ъ' => '',
                        'Ы' => 'Y',
                        'Ь' => '',
                        'Э' => 'E',
                        'Ю' => 'Yu',
                        'Я' => 'Ya',
                        '№' => ''
                    ));
                    break;
                case 'uk':
                    /* Ukrainian */
                    $char_map = array_merge($char_map, array('Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G', 'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g'));
                    break;
                case 'cs':
                    /* Czech */
                    $char_map = array_merge($char_map, array(
                        'č' => 'c',
                        'ď' => 'd',
                        'ě' => 'e',
                        'ň' => 'n',
                        'ř' => 'r',
                        'š' => 's',
                        'ť' => 't',
                        'ů' => 'u',
                        'ž' => 'z',
                        'Č' => 'C',
                        'Ď' => 'D',
                        'Ě' => 'E',
                        'Ň' => 'N',
                        'Ř' => 'R',
                        'Š' => 'S',
                        'Ť' => 'T',
                        'Ů' => 'U',
                        'Ž' => 'Z'
                    ));
                    break;
                case 'pl':
                    /* Polish */
                    $char_map = array_merge($char_map, array(
                        'ą' => 'a',
                        'ć' => 'c',
                        'ę' => 'e',
                        'ł' => 'l',
                        'ń' => 'n',
                        'ś' => 's',
                        'ź' => 'z',
                        'ż' => 'z',
                        'Ą' => 'A',
                        'Ć' => 'C',
                        'Ę' => 'e',
                        'Ł' => 'L',
                        'Ń' => 'N',
                        'Ś' => 'S',
                        'Ź' => 'Z',
                        'Ż' => 'Z'
                    ));
                    break;
                case 'ro':
                    /* Romanian */
                    $char_map = array_merge($char_map, array('Ţ' => 'T', 'ţ' => 't'));
                    break;
                case 'lv':
                    /* Latvian */
                    $char_map = array_merge($char_map, array(
                        'ā' => 'a',
                        'č' => 'c',
                        'ē' => 'e',
                        'ģ' => 'g',
                        'ī' => 'i',
                        'ķ' => 'k',
                        'ļ' => 'l',
                        'ņ' => 'n',
                        'š' => 's',
                        'ū' => 'u',
                        'ž' => 'z',
                        'Ā' => 'A',
                        'Č' => 'C',
                        'Ē' => 'E',
                        'Ģ' => 'G',
                        'Ī' => 'i',
                        'Ķ' => 'k',
                        'Ļ' => 'L',
                        'Ņ' => 'N',
                        'Š' => 'S',
                        'Ū' => 'u',
                        'Ž' => 'Z'
                    ));
                    break;
                case 'lt':
                    /* Lithuanian */
                    $char_map = array_merge($char_map, array(
                        'ą' => 'a',
                        'č' => 'c',
                        'ę' => 'e',
                        'ė' => 'e',
                        'į' => 'i',
                        'š' => 's',
                        'ų' => 'u',
                        'ū' => 'u',
                        'ž' => 'z',
                        'Ą' => 'A',
                        'Č' => 'C',
                        'Ę' => 'E',
                        'Ė' => 'E',
                        'Į' => 'I',
                        'Š' => 'S',
                        'Ų' => 'U',
                        'Ū' => 'U',
                        'Ž' => 'Z'
                    ));
                    break;
                case 'vi':
                    /* Vietnamese */
                    $char_map = array_merge($char_map, array(
                        'Ả' => 'A',
                        'Ạ' => 'A',
                        'Ắ' => 'A',
                        'Ằ' => 'A',
                        'Ẳ' => 'A',
                        'Ẵ' => 'A',
                        'Ặ' => 'A',
                        'Ấ' => 'A',
                        'Ầ' => 'A',
                        'Ẩ' => 'A',
                        'Ẫ' => 'A',
                        'Ậ' => 'A',
                        'ả' => 'a',
                        'ạ' => 'a',
                        'ắ' => 'a',
                        'ằ' => 'a',
                        'ẳ' => 'a',
                        'ẵ' => 'a',
                        'ặ' => 'a',
                        'ấ' => 'a',
                        'ầ' => 'a',
                        'ẩ' => 'a',
                        'ẫ' => 'a',
                        'ậ' => 'a',
                        'Ẻ' => 'E',
                        'Ẽ' => 'E',
                        'Ẹ' => 'E',
                        'Ế' => 'E',
                        'Ề' => 'E',
                        'Ể' => 'E',
                        'Ễ' => 'E',
                        'Ệ' => 'E',
                        'ẻ' => 'e',
                        'ẽ' => 'e',
                        'ẹ' => 'e',
                        'ế' => 'e',
                        'ề' => 'e',
                        'ể' => 'e',
                        'ễ' => 'e',
                        'ệ' => 'e',
                        'Ỉ' => 'i',
                        'Ĩ' => 'i',
                        'Ị' => 'i',
                        'ỉ' => 'i',
                        'ĩ' => 'i',
                        'ị' => 'i',
                        'Ỏ' => 'O',
                        'Ọ' => 'O',
                        'Ố' => 'O',
                        'Ồ' => 'O',
                        'Ổ' => 'O',
                        'Ỗ' => 'O',
                        'Ộ' => 'O',
                        'Ơ' => 'O',
                        'Ớ' => 'O',
                        'Ờ' => 'O',
                        'Ở' => 'O',
                        'Ỡ' => 'O',
                        'Ợ' => 'O',
                        'ỏ' => 'o',
                        'ọ' => 'o',
                        'ố' => 'o',
                        'ồ' => 'o',
                        'ổ' => 'o',
                        'ỗ' => 'o',
                        'ộ' => 'o',
                        'ơ' => 'o',
                        'ớ' => 'o',
                        'ờ' => 'o',
                        'ở' => 'o',
                        'ỡ' => 'o',
                        'ợ' => 'o',
                        'Ủ' => 'U',
                        'Ũ' => 'U',
                        'Ụ' => 'U',
                        'Ư' => 'U',
                        'Ứ' => 'U',
                        'Ừ' => 'U',
                        'Ử' => 'U',
                        'Ữ' => 'U',
                        'Ự' => 'U',
                        'ủ' => 'u',
                        'ũ' => 'u',
                        'ụ' => 'u',
                        'ư' => 'u',
                        'ứ' => 'u',
                        'ừ' => 'u',
                        'ử' => 'u',
                        'ữ' => 'u',
                        'ự' => 'u',
                        'Ỳ' => 'Y',
                        'Ỷ' => 'Y',
                        'Ỹ' => 'Y',
                        'Ỵ' => 'Y',
                        'ỳ' => 'y',
                        'ỷ' => 'y',
                        'ỹ' => 'y',
                        'ỵ' => 'y',
                        'Đ' => 'D',
                        'đ' => 'd'
                    ));
                    break;
                case 'ar':
                    /* Arabic */
                    $char_map = array_merge($char_map, array(
                        'أ' => 'a',
                        'ب' => 'b',
                        'ت' => 't',
                        'ث' => 'th',
                        'ج' => 'g',
                        'ح' => 'h',
                        'خ' => 'kh',
                        'د' => 'd',
                        'ذ' => 'th',
                        'ر' => 'r',
                        'ز' => 'z',
                        'س' => 's',
                        'ش' => 'sh',
                        'ص' => 's',
                        'ض' => 'd',
                        'ط' => 't',
                        'ظ' => 'th',
                        '/**/ع' => 'aa',
                        'غ' => 'gh',
                        'ف' => 'f',
                        'ق' => 'k',
                        'ك' => 'k',
                        'ل' => 'l',
                        'م' => 'm',
                        'ن' => 'n',
                        'ه' => 'h',
                        'و' => 'o',
                        'ي' => 'y'
                    ));
                    break;
                case 'sr':
                    /* Serbian */
                    $char_map = array_merge($char_map, array(
                        'ђ' => 'dj',
                        'ј' => 'j',
                        'љ' => 'lj',
                        'њ' => 'nj',
                        'ћ' => 'c',
                        'џ' => 'dz',
                        'đ' => 'dj',
                        'Ђ' => 'Dj',
                        'Ј' => 'j',
                        'Љ' => 'Lj',
                        'Њ' => 'Nj',
                        'Ћ' => 'C',
                        'Џ' => 'Dz',
                        'Đ' => 'Dj'
                    ));
                    break;
                case 'az':
                    /* Azerbaijani */
                    $char_map = array_merge($char_map, array('ə' => 'e', 'Ə' => 'e'));
                    break;
            }
        }
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
        if ($options['transliterate']) $str = str_replace(array_keys($char_map), $char_map, $str);
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
        $str = trim($str, $options['delimiter']);
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
        return "Version: " . $versionTag . " (Branch: " . $branchName . " @ " . $commitHash . ")";
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
