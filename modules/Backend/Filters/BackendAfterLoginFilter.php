<?php

namespace Modules\Backend\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Libraries\AuthLibrary;

class BackendAfterLoginFilter implements FilterInterface
{
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
        helper('filesystem');
        if(is_dir(ROOTPATH.'/modules/Installation')) {
            $result = delete_files(ROOTPATH . '/modules/Installation', true);
            if($result==true) $result=rmdir(ROOTPATH . '/modules/Installation');
            if (!$result) return view('\Modules\Installation\Views\deleteModule');
        }

        $templates=directory_map(ROOTPATH.'public/templates');
        foreach($templates as $key=>$template){
            if(!is_file(ROOTPATH.'public/templates/'.$key.'info.xml') || !is_file(ROOTPATH.'public/templates/'.$key.'screenshot.png'))
            {
                session()->setFlashdata('warning', ROOTPATH.'public/templates/'.$key.' klasöründe "info.xml" ve/veya "screenshot.png" dosyaları bulunmuyor. Kontrolü sağlayınız.');
                break;
            }
        }

        $authLib=new AuthLibrary();
        if (!$authLib->check()) return redirect()->route('logout');

        $router = service('router');
        $perms = $authLib->has_perm($router->controllerName(), $router->methodName());
        if (!$perms) return redirect()->to('/backend/403');
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
	    //
	}
}
