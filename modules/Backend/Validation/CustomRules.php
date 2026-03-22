<?php

namespace Modules\Backend\Validation;

use CodeIgniter\Validation\FormatRules;

class CustomRules
{
    /**
     * Stores sanitized data from html_purify rule.
     * Access in controller: CustomRules::getClean('field_value')
     */
    private static array $cleanCache = [];

    /**
     * Validates phone number format: (5XX) XXX XX XX
     *
     * @param string|null $phone Phone number to validate
     * @param string|null $error Error message reference
     * @return bool
     */
    public function phoneNumberVal(?string $phone, ?string &$error = null)
    {
        if (!preg_match('/^\(\d{3}\)\s\d{3}\s\d{2}\s\d{2}$/', $phone)) {
            $error = lang('Backend.invalidPhoneNumber');
            return false;
        }
        return true;
    }

    /**
     * Checks if a string is valid JSON.
     *
     * @param string $string The string to validate
     * @return bool Whether the string is valid JSON
     */
    function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Sanitizes HTML content from WYSIWYG editors against XSS attacks.
     * Usage as validation rule: 'required|html_purify'
     *
     * To retrieve clean data in controller:
     *   $content = CustomRules::getClean($this->request->getPost('content'));
     *
     * @param string|null $str   Data to validate
     * @param string|null $error Error message reference
     * @return bool
     */
    public function html_purify(?string &$str = null, ?string &$error = null): bool
    {
        if (empty(trim((string)$str))) {
            return true;
        }

        if (!class_exists('\HTMLPurifier')) {
            $error = lang('Backend.htmlPurifierNotFound');
            return false;
        }

        $clean = self::sanitizeHtml($str);
        $str = $clean;

        // Store sanitized data in cache in case CI4 validation
        // does not propagate the reference mutation.
        self::$cleanCache[md5((string)$str)] = $clean;

        return true;
    }

    /**
     * Returns sanitized data from html_purify rule cache.
     * Falls back to on-the-fly sanitization if not cached.
     *
     * Usage in controller after validation:
     *   $content = CustomRules::getClean($this->request->getPost('content'));
     *
     * @param string $original Raw (possibly unsanitized) POST data
     * @return string Sanitized HTML
     */
    public static function getClean(string $original): string
    {
        $key = md5($original);
        if (isset(self::$cleanCache[$key])) {
            return self::$cleanCache[$key];
        }
        // Not in cache, sanitize directly
        return self::sanitizeHtml($original);
    }

    /**
     * Sanitizes HTML content against XSS while preserving theme integrity.
     * Base64 images, CSS classes, IDs, inline styles, and HTML5 tags are preserved.
     *
     * Can be called directly from any Controller or Library:
     *   use Modules\Backend\Validation\CustomRules;
     *   $cleanContent = CustomRules::sanitizeHtml($dirtyHtml);
     *
     * @param string $html HTML content to sanitize
     * @return string Sanitized safe HTML
     */
    public static function sanitizeHtml(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }

        if (!class_exists('\HTMLPurifier')) {
            throw new \RuntimeException('HTMLPurifier library not found. Please run "composer require ezyang/htmlpurifier".');
        }

        // --- Base64 image protection algorithm ---
        // HTMLPurifier removes Base64 data assuming SVG may contain malicious code.
        // To prevent theme design breakage, we mask these images with temporary URLs before purification.
        $placeholders = [];
        $html = preg_replace_callback(
            '/(src|href)=["\']?(data:image\/[^"\' ]+)["\']?/i',
            function ($matches) use (&$placeholders) {
                $id = 'http://ci4ms-dummy.local/img_' . count($placeholders) . '.png';
                $placeholders[$id] = $matches[2];
                return $matches[1] . '="' . $id . '"';
            },
            $html
        );

        // --- HTMLPurifier Configuration ---
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

        // 1. Preserve CSS classes and IDs
        $config->set('Attr.EnableID', true);

        // 2. Allow inline CSS (style="...")
        $config->set('CSS.AllowTricky', true);
        $config->set('CSS.Proprietary', true);
        $config->set('CSS.Trusted', true);

        // 3. Iframe support (Youtube, Vimeo)
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        // 4. Allow links to open in new tab (target="_blank")
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        // 5. Allowed URI schemes (including data: for Base64)
        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
            'ftp'    => true,
            'nntp'   => true,
            'news'   => true,
            'tel'    => true,
            'data'   => true
        ]);

        // 6. HTML5 Semantic Elements
        $config->set('HTML.DefinitionID', 'ci4ms-custom-purifier');
        $config->set('HTML.DefinitionRev', 1);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('section',    'Block', 'Flow', 'Common');
            $def->addElement('nav',        'Block', 'Flow', 'Common');
            $def->addElement('article',    'Block', 'Flow', 'Common');
            $def->addElement('aside',      'Block', 'Flow', 'Common');
            $def->addElement('header',     'Block', 'Flow', 'Common');
            $def->addElement('footer',     'Block', 'Flow', 'Common');
            $def->addElement('main',       'Block', 'Flow', 'Common');
            $def->addElement('figure',     'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');

            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
        }

        // --- Purify ---
        $purifier = new \HTMLPurifier($config);
        $cleanHtml = $purifier->purify($html);

        // --- Restore masked Base64 images ---
        if (!empty($placeholders)) {
            $cleanHtml = strtr($cleanHtml, $placeholders);
        }

        // --- Remove body tags keep content ---
        $cleanHtml = preg_replace('/<\/?body[^>]*>/i', '', $cleanHtml);

        return $cleanHtml;
    }
}
