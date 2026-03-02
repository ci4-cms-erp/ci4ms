<?php

namespace Modules\Backend\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class BackendLogFilter implements FilterInterface
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

    private $LogBackendActivity=true;
    public function before(RequestInterface $request, $arguments = null)
    {
        if($this->LogBackendActivity==false){
            return;
        }
        // 1. Get the authenticated user (shield)
        $user = auth()->user();
        if (!$user) {
            return; // Not logged in, skip logging
        }
        // 2. Identify the user
        $userId = $user->id;
        $username = $user->email ?? $user->username ?? 'Unknown';

        // 3. Extract request details
        $uri = current_url();
        $method = $request->getMethod();

        // Advanced IP Extraction (in case behind Cloudflare/Proxy)
        $ipAddress = $request->getIPAddress();
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        }
        $ipAddress = trim($ipAddress);

        // Capture Device / User Agent
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $userAgent = method_exists($request, 'getUserAgent') ? $request->getUserAgent() : null;
        $deviceInfo = $userAgent ? $userAgent->getAgentString() : 'Unknown Device';

        // 4. Capture POST data safely (ignoring arrays/objects or massive files if any, and hiding passwords)
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $postData = method_exists($request, 'getPost') ? $request->getPost() : [];

        // Sanitize sensitive fields if they exist
        $sensitiveFields = ['password', 'password_confirm', 'pass', 'token'];
        if (!empty($postData) && is_array($postData)) {
            foreach ($sensitiveFields as $field) {
                if (isset($postData[$field])) {
                    $postData[$field] = '***** (HIDDEN)';
                }
            }
        }

        $postStr = empty($postData) ? 'No POST Data' : json_encode($postData, JSON_UNESCAPED_UNICODE);

        // 5. Construct log entry message
        $logMessage = sprintf(
            "BACKEND_ACTIVITY - %s --> USERID: %s, USER: %s | IP: %s | DEVICE: %s | METHOD: %s | URI: %s | POST_DATA: %s" . PHP_EOL,
            date('Y-m-d H:i:s'),
            $userId,
            $username,
            $ipAddress,
            $deviceInfo,
            strtoupper($method),
            $uri,
            $postStr
        );

        // 6. Define and write to the custom log file
        $logFileName = 'log-backend-'.date('Y-m-d') .'.log';
        $logFilePath = WRITEPATH . 'logs/' . $logFileName;

        helper('filesystem');
        write_file($logFilePath, $logMessage, 'a+');
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
        // No action needed after the request
    }
}
