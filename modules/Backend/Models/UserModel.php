<?php

namespace Modules\Backend\Models;

use CodeIgniter\Model;
use Config\Services;
use CodeIgniter\I18n\Time;
use Modules\Auth\Config\AuthConfig as Auth;

class UserModel extends Model
{
    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var bool
     */
    protected $useTimestamps = true;

    public function getPermissionsForUser(int $userId, int $groupId): object
    {
        if ($cached = cache("{$userId}_permissions")) return (object) $cached;

        $permissions = $this->db->table('auth_groups_permissions')
            ->select('auth_permissions_pages.id, auth_permissions_pages.pagename, auth_permissions_pages.pageSort, auth_permissions_pages.hasChild, auth_permissions_pages.symbol, auth_permissions_pages.sefLink, auth_permissions_pages.parent_pk, auth_permissions_pages.inNavigation, auth_permissions_pages.className, auth_permissions_pages.methodName, auth_permissions_pages.typeOfPermissions, auth_groups_permissions.create_r, auth_groups_permissions.read_r, auth_groups_permissions.update_r, auth_groups_permissions.delete_r')
            ->join('auth_permissions_pages', 'auth_permissions_pages.id = auth_groups_permissions.page_id')
            ->where('group_id', $groupId)
            ->unionAll(
                $this->db->table('auth_users_permissions')
                    ->select('auth_permissions_pages.id, auth_permissions_pages.pagename, auth_permissions_pages.pageSort, auth_permissions_pages.hasChild, auth_permissions_pages.symbol, auth_permissions_pages.sefLink, auth_permissions_pages.parent_pk, auth_permissions_pages.inNavigation, auth_permissions_pages.className, auth_permissions_pages.methodName, auth_permissions_pages.typeOfPermissions, auth_users_permissions.create_r, auth_users_permissions.read_r, auth_users_permissions.update_r, auth_users_permissions.delete_r')
                    ->join('auth_permissions_pages', 'auth_permissions_pages.id = auth_users_permissions.page_id')
                    ->where('auth_users_permissions.user_id', $userId)
            )
            ->orderBy('pageSort', 'ASC')
            ->get()
            ->getResultArray();

        $finalPermissions = [];
        foreach ($permissions as $perm) {
            $id = $perm['id'];

            if (!isset($finalPermissions[$id])) {
                $finalPermissions[$id] = $perm;
            } else {
                foreach (['create_r', 'read_r', 'update_r', 'delete_r'] as $key) {
                    $finalPermissions[$id][$key] |= $perm[$key]; // Binary OR
                }
            }
        }

        $finalPermissions = array_values($finalPermissions);

        cache()->save("{$userId}_permissions", $finalPermissions, 300);

        return (object)cache("{$userId}_permissions");
    }

    /**
     * @param string $email
     * @param bool $success
     * @param int|null $falseCounter for loginAttempt false
     * @return mixed
     * @throws \Exception
     */
    public function recordLoginAttempt(string $email, bool $success, int $falseCounter)
    {
        $ipAddress = Services::request()->getIPAddress();
        $user_agent = Services::request()->getUserAgent();

        $agent = null;
        if ($user_agent->isBrowser())
            $agent = $user_agent->getBrowser() . ':' . $user_agent->getVersion();
        elseif ($user_agent->isMobile())
            $agent = $user_agent->getMobile();
        else
            $agent = 'nothing';

        $time = new Time('now');

        return $this->commonModel->create('auth_logins', [
            'ip_address' => $ipAddress,
            'email' => $email,
            'trydate' => $time->toDateTimeString(),
            'isSuccess' => $success,
            'user_agent' => $agent,
            'session_id' => session_id(),
            'counter' => ($success === false) ? $falseCounter + 1 : null,
        ]);
    }

    /**
     * @param string $userID
     * @param string $selector
     * @param string $validator
     * @param string $expires
     * @return mixed
     * @throws \Exception
     */
    public function rememberUser(string $userID, string $selector, string $validator, string $expires)
    {
        $expires = new \DateTime($expires);

        return $this->db->table('auth_tokens')->insert([
            'user_id' => $userID,
            'selector' => $selector,
            'hashedValidator' => hash('sha256', $validator),
            'expires' => $expires->format('Y-m-d H:i:s')
        ]);
    }

    /**
     *
     */
    public function purgeOldRememberTokens()
    {
        $config = new Auth();
        if (!$config->allowRemembering) return;
        $this->db->table('auth_tokens')->delete(['expires<=' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param string $selector
     * @param string $validator
     * @return array|object|null
     */
    public function updateRememberValidator(string $selector, string $validator)
    {
        return $this->db->table('auth_tokens')->update(['hashedValidator' => hash('sha256', $validator)], ['selector' => $selector]);
    }

    /**
     * used for list data with where and  where_or
     * @param string $collection
     * @param array $where
     * @param array $or
     * @param array $options
     * @param array $select
     * @return mixed
     * @throws \Exception
     * TODO: builder ile kod düzeltilecek.
     */
    public function getListOr(string $collection, array $where = [], array $options = [], array $select = [], array $or = [])
    {
        return $this->m->options($options)->select($select)->where($where)->where_or($or)->find($collection)->toArray();
    }

    /**
     * @param string $collection
     * @param array $where
     * @param array $options
     * @param array $or
     * @return mixed
     * @throws \Exception
     */
    public function countOr(string $collection, array $where, array $or = [])
    {
        $builder = $this->db->table($collection);
        $builder->where($where)->orWhere($or);
        return $builder->countAllResults();
    }

    /**
     * @param string $collection
     * @param array $where
     * @param array $options
     * @param array $select
     * @param array $or
     * @return mixed
     * @throws \Exception
     */
    public function getOneOr(string $collection, array $where = [], string $order = 'id ASC', string $select = '*', array $or = [])
    {
        $builder = $this->db->table($collection);
        $builder->select($select)->where($where)->orWhere($or)->orderBy($order);
        return $builder->get()->getResult();
    }

    /**
     * @param string $collection
     * @param array $where
     * @param array $set
     * @param array $options
     * @return mixed
     * @throws \Exception
     */

    public function updateManyOr(string $collection, array $where, array $set, string $select = '*', array $or = [])
    {
        $builder = $this->db->table($collection);
        return $builder->select($select)->orWhere($or)->update($set, $where);
    }
}
