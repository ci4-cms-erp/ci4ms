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
     * Validates that a value is a non-empty array of permission action
     * keywords (create/read/update/delete).
     *
     * Framework calls validation rules as $set->{$rule}($value, $error) with
     * $value passed through exactly as received (CodeIgniter\Validation\
     * Validation::run()), so a scalar POST value (e.g. 'typeOfPermissions=5')
     * reaches this rule unmodified. The parameter is intentionally untyped:
     * a type hint would make PHP throw a TypeError before the rule body
     * runs, reproducing the exact crash this rule exists to prevent. Both
     * "not an array" and "empty array" are rejected explicitly — an empty
     * array passes is_array() but still carries no valid action, and
     * Ci4MsAuthFilter::neededAction() would fall back to its 'read' default
     * for it just like for a non-array value.
     *
     * @param mixed       $value The value to validate.
     * @param string|null $error Error message reference.
     * @return bool
     */
    public function permissionActionList($value, ?string &$error = null): bool
    {
        if (!is_array($value) || $value === []) {
            $error = (string) lang('Backend.invalidPermissionTypes');
            return false;
        }

        foreach ($value as $item) {
            if (!in_array($item, ['create', 'read', 'update', 'delete'], true)) {
                $error = (string) lang('Backend.invalidPermissionTypes');
                return false;
            }
        }

        return true;
    }

    /**
     * Validates that a value is a bare hostname or an IPv4 address.
     * IPv6 literals are rejected: CI4's Email passes the host to fsockopen()
     * unbracketed, which cannot connect to a bare IPv6 address.
     * Rejects host:port, scheme://host, whitespace/CRLF, a leading hyphen
     * and underscores. Private/reserved ranges are intentionally allowed.
     * The error string is used verbatim by the framework (no {field} substitution).
     *
     * @param mixed       $str   The value to validate.
     * @param string|null $error Error message reference.
     * @return bool
     */
    public function valid_host($str, ?string &$error = null): bool
    {
        if (is_string($str) && $str !== '' && (
            filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            || filter_var($str, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
        )) {
            return true;
        }

        $error = (string) lang('Backend.invalidHost');
        return false;
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

        // --- Base64 raster-image protection algorithm ---
        // HTMLPurifier strips data: URIs because SVG (data:image/svg+xml)
        // can carry inline <script> / onload handlers and other XSS vectors.
        // To preserve embedded raster images (jpeg/png/gif/webp/avif) we
        // swap them for opaque placeholder URLs before purification and
        // restore them afterwards.
        //
        // SECURITY: the allowlist below MUST exclude svg+xml. A previous
        // version of this regex matched any `data:image/*` and let SVG
        // bypass HTMLPurifier entirely — the audit's Finding 8 stored XSS.
        $placeholders = [];
        $html = preg_replace_callback(
            '/(src|href)=(["\'])(data:image\/(?:png|jpe?g|gif|webp|avif|bmp)(?:;[^"\']*)?,[A-Za-z0-9+\/=]+)\2/i',
            function ($matches) use (&$placeholders) {
                $id = 'http://ci4ms-dummy.local/img_' . count($placeholders) . '.png';
                $placeholders[$id] = $matches[3];
                return $matches[1] . '=' . $matches[2] . $id . $matches[2];
            },
            $html
        );

        // --- HTMLPurifier Configuration ---
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

        // 1. Preserve CSS classes and IDs
        $config->set('Attr.EnableID', true);

        // 2. Allow specific CSS but don't trust everything
        $config->set('CSS.AllowTricky', true); // Often needed for editor styles (visibility etc.)
        $config->set('CSS.Proprietary', true); // For browser-specific prefixes
        $config->set('CSS.Trusted', false);   // NEVER trust CSS fully; let Purifier filter properties.

        // 3. Iframe support (Youtube, Vimeo)
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        // 4. Allow links to open in new tab and force noopener/noreferrer (Security Best Practice)
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer', 'external']);

        // 5. Allowed URI schemes
        // 'data' is NOT allowed — Base64 images are protected via placeholder mechanism above.
        // This blocks data:text/html;base64 XSS bypass attacks.
        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
            'ftp'    => true,
            'tel'    => true,
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
