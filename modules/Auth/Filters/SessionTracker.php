<?php

namespace Modules\Auth\Filters;

use Modules\Auth\Models\UserSessionModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Security and Tracking Filter: User Session Tracker
 *
 * Designed to track the real-time device information and connection durations
 * of logged-in users. It also synchronously prevents database-controlled (DB-Driven)
 * session termination (revocation) at the Filter level.
 */
class SessionTracker implements FilterInterface
{
    /**
     * Intercepts the request before it reaches the Controller.
     * Checks for a permanent "Device ID" (Tracker ID) belonging to the user, generates one if missing.
     * Uses this ID to verify the active status in the database; if inactive, terminates the process and logs the user out.
     *
     * @param RequestInterface $request   Incoming HTTP Request
     * @param mixed            $arguments Additional arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('device');

        $userId  = auth()->id();

        if (! $userId) {
            return;
        }

        $session = session();
        $sessionId = $session->get('ci4ms_session_tracker_id');
        
        if (! $sessionId) {
            $sessionId = bin2hex(random_bytes(16));
            $session->set('ci4ms_session_tracker_id', $sessionId);
        }
        
        $model     = new UserSessionModel();

        $exists = $model->where('session_id', $sessionId)->first();

        if (! $exists) {
            $agent      = $request->getUserAgent();
            $deviceInfo = extract_device_info($agent);

            $model->recordLogin(
                userId:     (int) $userId,
                sessionId:  $sessionId,
                deviceInfo: $deviceInfo,
                ip:         $request->getIPAddress()
            );
        } else {
            // If the current device's session status in the database has been remotely set to "is_active = 0",
            // forces the visitor out of their current device.
            if ($exists['is_active'] == 0) {
                auth()->logout();
                session()->destroy();
                return redirect()->route('login')->with('error', lang('Users.currentSessionTerminated'));
            }

            $model->touchSession($sessionId);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No further manipulation needed afterwards.
    }
}
