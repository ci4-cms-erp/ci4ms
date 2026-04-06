<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use CodeIgniter\Model;

class UserSessionModel extends Model
{
    protected $table         = 'user_sessions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'browser',
        'browser_version',
        'os',
        'device_type',
        'device_name',
        'country',
        'city',
        'last_activity',
        'is_active',
        'created_at',
        'terminated_at',
    ];

    protected $useTimestamps = false; // Manuel yönetiyoruz

    // ──────────────────────────────────────────────────────────────────
    // WRITE
    // ──────────────────────────────────────────────────────────────────

    /**
     * Creates a new session record after a login request.
     * To prevent duplicate records, if an existing session matching the provided
     * tracking ID is found, it will update the existing data instead.
     * 
     * @param int    $userId     System ID of the user
     * @param string $sessionId  Permanent device identifier (Tracker ID)
     * @param array  $deviceInfo Device details returned from the device helper
     * @param string $ip         User's connecting IP address
     * @return bool  Result of the operation
     */
    public function recordLogin(int $userId, string $sessionId, array $deviceInfo, string $ip): bool
    {
        $existing = $this->where('session_id', $sessionId)->first();

        $data = [
            'user_id'         => $userId,
            'session_id'      => $sessionId,
            'ip_address'      => $ip,
            'user_agent'      => $deviceInfo['user_agent'] ?? '',
            'browser'         => $deviceInfo['browser'] ?? '',
            'browser_version' => $deviceInfo['browser_version'] ?? '',
            'os'              => $deviceInfo['os'] ?? '',
            'device_type'     => $deviceInfo['device_type'] ?? 'unknown',
            'device_name'     => $deviceInfo['device_name'] ?? '',
            'last_activity'   => date('Y-m-d H:i:s'),
            'is_active'       => 1,
        ];

        $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country,regionName"), true);
        if ($geo && $geo['status'] ?? '' === 'success') {
            $data['city']    = $geo['city'] ?? null;
            $data['country'] = $geo['country'] ?? null;
            $data['region']  = $geo['regionName'] ?? null;
        }
        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->insert($data) !== false;
    }

    /**
     * Updates the last activity date for the active session upon every page request.
     * To optimize database performance, it utilizes a caching mechanism (cooldown) to ensure 
     * a maximum of one UPDATE query is executed per 60 seconds for the same session.
     *
     * @param string $sessionId Permanent device identifier
     * @return void
     */
    public function touchSession(string $sessionId): void
    {
        $cacheKey = 'session_touch_' . $sessionId;
        $cache    = \Config\Services::cache();

        if ($cache->get($cacheKey)) {
            return; // 60 saniye dolmamış, güncelleme
        }

        $this->where('session_id', $sessionId)
            ->where('is_active', 1)
            ->set('last_activity', date('Y-m-d H:i:s'))
            ->update();

        $cache->save($cacheKey, true, 60); // 60 sn cooldown
    }

    /**
     * Terminates a defined device/session record for a specific user.
     * Instead of physically deleting the session file, it safely disables the session 
     * at the Filter level by utilizing the database's "is_active" flag logic.
     *
     * @param int    $userId           System ID of the user
     * @param string $sessionId        Device identifier to be terminated
     * @param bool   $isCurrentSession Indicates whether the session being closed is the current one
     * @return bool  Returns true on success, false otherwise
     */
    public function terminateSession(int $userId, string $sessionId, bool $isCurrentSession = false): bool
    {
        // Ownership verification: Only a session belonging to the concerned user can be terminated
        $session = $this->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->first();

        if (! $session) {
            return false;
        }

        // Sets the session to inactive status in the database (is_active = 0)
        return $this->where('id', $session['id'])->set([
            'is_active'     => 0,
            'terminated_at' => date('Y-m-d H:i:s'),
        ])->update();
    }

    /**
     * Simultaneously disables all active sessions belonging to the user (e.g., other browsers, mobile etc.)
     * except for the current device they are connected from.
     *
     * @param int    $userId           System ID of the user
     * @param string $currentSessionId Device identifier that needs to remain open (current)
     * @return int   Number of successfully terminated sessions
     */
    public function terminateAllExcept(int $userId, string $currentSessionId): int
    {
        $sessions = $this->where('user_id', $userId)
            ->where('is_active', 1)
            ->whereNotIn('session_id', [$currentSessionId])
            ->findAll();

        $count = 0;
        foreach ($sessions as $session) {
            if ($this->terminateSession($userId, $session['session_id'])) {
                $count++;
            }
        }

        return $count;
    }

    // ──────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────

    /**
     * Returns a list of all sessions belonging to the user, including both active and past (closed) sessions.
     *
     * @param int    $userId           System ID of the user
     * @param string $currentSessionId Device ID (used to identify and mark the current session)
     * @param int    $limit            Maximum number of records to retrieve (Default 50)
     * @return array
     */
    public function getUserSessions(int $userId, string $currentSessionId, int $limit = 50): array
    {
        $sessions = $this->where('user_id', $userId)
            ->orderBy('is_active', 'DESC')
            ->orderBy('last_activity', 'DESC')
            ->limit($limit)
            ->findAll();

        // Mark the current session
        foreach ($sessions as &$session) {
            $session['is_current'] = ($session['session_id'] === $currentSessionId);
        }

        return $sessions;
    }

    /**
     * Returns only the active sessions belonging to the user (where "is_active = 1").
     *
     * @param int    $userId           System ID of the user
     * @param string $currentSessionId Device ID (used to identify and mark the current session)
     * @return array
     */
    public function getActiveSessions(int $userId, string $currentSessionId): array
    {
        $sessions = $this->where('user_id', $userId)
            ->where('is_active', 1)
            ->orderBy('last_activity', 'DESC')
            ->findAll();

        foreach ($sessions as &$session) {
            $session['is_current'] = ($session['session_id'] === $currentSessionId);
        }

        return $sessions;
    }

    /**
     * Returns the number of active devices (sessions) the user is currently accessing the system from.
     *
     * @param int $userId System ID of the user
     * @return int Number of active sessions
     */
    public function getActiveCount(int $userId): int
    {
        return $this->where('user_id', $userId)->where('is_active', 1)->countAllResults();
    }

    /**
     * Completely removes archived and expired closed sessions to alleviate database load.
     * Typically meant to be executed via a Job/Cron.
     *
     * @param int $days Defines how many days old the data must be to be deleted (Default: 90)
     * @return int Number of rows physically deleted from the database
     */
    public function cleanupOldSessions(int $days = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete obsolete, inactive sessions
        $this->where('is_active', 0)
            ->where('terminated_at <', $cutoff)
            ->delete();

        return $this->db->affectedRows();
    }
}
