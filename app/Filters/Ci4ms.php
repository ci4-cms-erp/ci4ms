<?php

namespace App\Filters;

use ci4commonmodel\Models\CommonModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Ci4ms implements FilterInterface
{

    protected $commonModel;

    public function __construct()
    {
        if (file_exists(ROOTPATH . '.env')) $this->commonModel = new CommonModel();
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
        if ((bool)cache()->get('settings')['maintenanceMode']->scalar === true) return redirect()->route('maintenance-mode');
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
        if (empty(cache('menus'))) cache()->save('menus', $this->commonModel->lists('menu', 'id,title,seflink,parent,pages_id,hasChildren', [], 'queue ASC'), 86400);
    }
}
