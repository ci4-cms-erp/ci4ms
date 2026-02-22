<?php

namespace Modules\Backend\Validation;

use CodeIgniter\Validation\FormatRules;

class CustomRules
{
    public function phoneNumberVal(string $phone, string &$error = null)
    {
        if (!preg_match('/^\(\d{3}\)\s\d{3}\s\d{2}\s\d{2}$/', $phone)) {
            $error = lang('Backend.invalidPhoneNumber');
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
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}
