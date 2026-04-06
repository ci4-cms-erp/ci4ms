<?php

/**
 * CI4MS System Device Helper
 *
 * Utilizes CodeIgniter 4's \CodeIgniter\HTTP\UserAgent class to parse the device,
 * browser, and operating system information accessed by users. It provides specific 
 * corrections where native library methods are insufficient (e.g. tablet device detection).
 */

use CodeIgniter\HTTP\UserAgent;

// ─────────────────────────────────────────────────────────────────────────────
// ANA FONKSİYON
// ─────────────────────────────────────────────────────────────────────────────

if (! function_exists('extract_device_info'))
{
    /**
     * Extracts and maps data from the CodeIgniter 4 UserAgent object, making it 
     * available to internally analyze the visitor's device.
     *
     * Usage:
     *   $agent      = $request->getUserAgent();
     *   $deviceInfo = extract_device_info($agent);
     *
     * @param  UserAgent $agent  $request->getUserAgent() sonucu
     * @return array{
     *     user_agent: string,
     *     browser: string,
     *     browser_version: string,
     *     os: string,
     *     device_type: string,
     *     device_name: string
     * }
     */
    function extract_device_info(UserAgent $agent): array
    {
        /* ── DEVICE TYPE LOGIC ──────────────────────────────────────────
         * Priority order: robot > tablet > mobile > desktop
         * Since the native CI4 library lacks an isTablet() method, it is resolved locally via regex.
         */
        $rawUa = $agent->getAgentString();

        if ($agent->isRobot()) {
            $deviceType = 'bot';
        } elseif (is_tablet_ua($rawUa)) {
            $deviceType = 'tablet';
        } elseif ($agent->isMobile()) {
            $deviceType = 'mobile';
        } else {
            $deviceType = 'desktop';
        }

        /* ── BROWSER EXTRACTION ─────────────────────────────────────
         * If isBrowser() returns true, fetches the default browser name.
         * Otherwise (for bots, etc.) it attempts alternative identification.
         */
        $browser        = '';
        $browserVersion = '';

        if ($agent->isBrowser()) {
            $browser        = $agent->getBrowser();        // "Chrome", "Firefox" vs.
            $browserVersion = $agent->getVersion();        // "120.0"
        } elseif ($agent->isRobot()) {
            $browser = $agent->getRobot();                 // "Googlebot" vs.
        }

        /* ── OS EXTRACTION ──────────────────────────────────────
         * Example yields: "Windows", "Mac OS X", "Android", "iOS"
         */
        $os = $agent->getPlatform() ?: 'Bilinmiyor';

        /* ── MOBILE DEVICE BRAND/MODEL ───────────────────────────
         * Specifically fetches known models (iPhone, iPad, etc.) via the getMobile() method.
         * It remains an empty string for desktop endpoints and bots.
         */
        $deviceName = '';
        if ($deviceType === 'mobile' || $deviceType === 'tablet') {
            $deviceName = $agent->getMobile() ?: '';
        }

        return [
            'user_agent'      => $rawUa,
            'browser'         => $browser,
            'browser_version' => $browserVersion,
            'os'              => $os,
            'device_type'     => $deviceType,
            'device_name'     => $deviceName,
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// YARDIMCI: TABLET TESPİTİ
// ─────────────────────────────────────────────────────────────────────────────

if (! function_exists('is_tablet_ua'))
{
    /**
         * Missing tablet device detection logic from CI4 UserAgent base.
         * Due to structural encapsulation, it's scoped only to this file and its scope.
         */
    function is_tablet_ua(string $ua): bool
    {
        return (bool) preg_match(
            '/ipad|tablet|kindle|silk|playbook|nexus\s(7|9|10)/i',
            $ua
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// VIEW YARDIMCILARI (CI4 ile ilgisi yok — sadece icon mapping)
// ─────────────────────────────────────────────────────────────────────────────

if (! function_exists('device_icon'))
{
    /**
     * Generates a corresponding FontAwesome icon (class) mapped to the device type data.
     */
    function device_icon(string $deviceType): string
    {
        return match ($deviceType) {
            'mobile'  => 'fas fa-mobile-alt',
            'tablet'  => 'fas fa-tablet-alt',
            'desktop' => 'fas fa-laptop',
            'bot'     => 'fas fa-robot',
            default   => 'fas fa-question-circle',
        };
    }
}

if (! function_exists('browser_icon'))
{
    /**
     * Maps the browser name defined by CodeIgniter 4 to its corresponding 
     * font (FontAwesome) icon element and returns the related CSS class.
     *
     * @param string $browser Browser name
     * @return string
     */
    function browser_icon(string $browser): string
    {
        return match (true) {
            str_contains($browser, 'Chrome')          => 'fab fa-chrome',
            str_contains($browser, 'Firefox')         => 'fab fa-firefox',
            str_contains($browser, 'Safari')          => 'fab fa-safari',
            str_contains($browser, 'Edge')            => 'fab fa-edge',
            str_contains($browser, 'Opera')           => 'fab fa-opera', // varsa
            str_contains($browser, 'Samsung Browser') => 'fas fa-mobile-alt',
            default                                   => 'fas fa-globe',
        };
    }
}
