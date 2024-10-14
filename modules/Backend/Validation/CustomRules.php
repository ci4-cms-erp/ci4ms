<?php

namespace Modules\Backend\Validation;

use CodeIgniter\Validation\FormatRules;

class CustomRules
{
    public function ipRangeControl(string $range, string &$error = null): bool
    {

        $range = clearFilter(explode(',', preg_replace('/\s+/', '', $range)));
        foreach ($range as $item) {


            $item_exp = explode('-', $item);
            if (!isset($item_exp[1])) {
                $error = "Bu değer içerisinde ( - ) ayırma işareti belirtilmemiştir. <b>" . $item . "</b>";
                return false;
            }

            $ipsFormat = [];
            foreach ($item_exp as $ip) {
                if (!(new FormatRules())->valid_ip($ip, 'ip4') || !(new FormatRules())->valid_ip($ip, 'ip6')) {
                    $error = " Bu değer ip4 yada ip6 formatına uygun değil. <b>" . $ip . "</b>";
                    return false;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $ipsFormat[] = 'ip4';
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $ipsFormat[] = 'ip6';
            }
            if (count(array_unique($ipsFormat)) !== 1) {
                $error = "IP formatları aynı değil. <b>" . $item . "<b>";
                return false;
            }
            if ($this->ip2long_vX($item_exp[0]) >= $this->ip2long_vX($item_exp[1])) {
                $error = "Soldaki değer sağdakinden büyük yada eşit olamaz. <b>" . $item . "<b>";
                return false;
            }
        }
        return true;
    }

    /* ip address type convert to integer. */
    public function ip2long_vX($ip)
    {
        $ip_n = inet_pton($ip);
        $bin = '';
        for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
            $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
        }
        if (function_exists('gmp_init')) {
            return (int)gmp_strval(gmp_init($bin, 2), 10);
        } elseif (function_exists('bcadd')) {
            $dec = '0';
            for ($i = 0; $i < strlen($bin); $i++) {
                $dec = bcmul($dec, '2', 0);
                $dec = bcadd($dec, $bin[$i], 0);
            }
            return (int)$dec;
        } else {
            trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
        }
    }

    public function phoneNumberVal(string $phone, string &$error = null)
    {
        if (!preg_match('/^\(\d{3}\)\s\d{3}\s\d{2}\s\d{2}$/', $phone)) {
            $error = 'Telefon Numarası Uygun formatta değil. Lütfen doğru formatta yazınız.';
            return false;
        }
        return true;
    }

    /**
     * Bir verinin JSON formatında olup olmadığını kontrol eder.
     *
     * @param string $string Kontrol edilecek string.
     * 
     * @return bool Verinin geçerli JSON olup olmadığını döner.
     */
    function isValidJson($string)
    {
        // json_decode ile string'i çözümlemeye çalışıyoruz
        json_decode($string);

        // json_last_error() ile herhangi bir hata olup olmadığını kontrol ediyoruz
        return (json_last_error() === JSON_ERROR_NONE);
    }
}
