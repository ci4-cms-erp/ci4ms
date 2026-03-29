<?php

declare(strict_types=1);

namespace Modules\LanguageManager\Filters;

use ci4commonmodel\CommonModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * LocaleFilter
 *
 * Detects locale from URL prefix (e.g. /tr/blog/...) and sets the application locale.
 * In single-language mode, this filter does nothing.
 * In multi-language mode:
 *   - Extracts locale from the first URI segment
 *   - Validates it against active frontend languages
 *   - Sets CI4 locale via $request->setLocale()
 *   - Redirects to default language if locale is missing or invalid
 */
class LocaleFilter implements FilterInterface
{
    /**
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return RequestInterface|ResponseInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Skip if no .env (not installed)
        if (!file_exists(ROOTPATH . '.env')) {
            return;
        }

        $settings = cache('settings');
        $mode = $settings['siteLanguageMode'] ?? 'single';

        // Single language mode — do nothing
        if ($mode !== 'multi') {
            return;
        }

        $commonModel = new CommonModel();

        // Cache frontend languages for performance (Full objects for UI, codes for validation)
        $frontendLangs = cache('frontend_languages');
        if ($frontendLangs === null) {
            $frontendLangs = $commonModel->lists('languages', 'code, flag, name as title', [
                'is_active'   => 1,
                'is_frontend' => 1,
            ], 'sort_order ASC');
            cache()->save('frontend_languages', $frontendLangs, 3600);
        }

        if (empty($frontendLangs)) {
            return;
        }

        $frontendLangCodes = array_map(function($l) {
            return is_object($l) ? $l->code : $l;
        }, $frontendLangs);

        // Get default language
        $defaultLang = cache('default_frontend_language');
        if ($defaultLang === null) {
            $def = $commonModel->selectOne('languages', [
                'is_default'  => 1,
                'is_active'   => 1,
                'is_frontend' => 1,
            ], 'code');
            $defaultLang = $def->code ?? $frontendLangs[0] ?? 'tr';
            cache()->save('default_frontend_language', $defaultLang, 3600);
        }

        $uri = $request->getUri();
        $segments = $uri->getSegments();
        $firstSegment = $segments[0] ?? '';

        // Skip static files (css, js, images, etc)
        $path = $uri->getPath();
        if (preg_match('/\.(js|css|gif|jpg|jpeg|png|ico|svg|woff|woff2|ttf|eot|map)$/i', $path)) {
            return;
        }

        // Skip backend, install, api and common ajax routes
        $skipSegments = ['backend', 'install', 'api', 'newComment', 'repliesComment', 'loadMoreComments', 'commentCaptcha', 'forms'];
        if (in_array($firstSegment, $skipSegments, true)) {
            return;
        }

        // Add cookie helper if we need to use it
        helper('cookie');

        // Check if first segment is a valid locale
        if (in_array($firstSegment, $frontendLangCodes, true)) {
            // Set locale — CI4's {locale} route group also sets this automatically
            // because App.php now initializes supportedLocales dynamically,
            // but we additionally sync the Language service and session.
            \Config\Services::language()->setLocale($firstSegment);
            session()->set('site_locale', $firstSegment);
            set_cookie('site_locale', $firstSegment, 31536000); // 1 year cookie

            return;
        }

        // NO LOCALE PREFIX — User is at / or some other non-prefixed path
        
        // 1. Check if user already has a preferred locale saved (Session or Cookie)
        $trackedLocale = session()->get('site_locale') ?? get_cookie('site_locale');
        
        // 2. Validate tracked locale against active frontend languages, fallback to defaultLang if invalid
        $targetLocale = (in_array($trackedLocale, $frontendLangCodes, true)) ? $trackedLocale : $defaultLang;

        // Force redirect to tracked or default language prefix
        $targetUrl = site_url($targetLocale . '/' . implode('/', $segments));
        return redirect()->to($targetUrl);
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after
    }
}
