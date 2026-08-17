<?php

declare(strict_types=1);

namespace App\Filters;

use ci4commonmodel\CommonModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Ci4ms implements FilterInterface
{

    protected $commonModel;

    public function __construct()
    {
        if (file_exists(ROOTPATH . '.env'))
            $this->commonModel = new CommonModel();
    }

    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!file_exists(ROOTPATH . '.env')) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            return redirect()->to($protocol . $_SERVER['SERVER_NAME'] . '/install');

        }
        // Config\Filters warms the settings cache, but it swallows any failure and
        // leaves the cache empty. Indexing null here then fataled the whole request,
        // which is how a freshly installed site could 500 on its very first hit.
        if ((bool) (cache()->get('settings')['maintenanceMode'] ?? false) === true)
            return redirect()->route('maintenance-mode');
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Menu caching is now handled in BaseController::getDefaultData() with localization support.
    }
}
