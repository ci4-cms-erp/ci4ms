<?php namespace Modules\Backend\Models;

use CodeIgniter\Model;
use Config\Services;
use CodeIgniter\I18n\Time;
use Modules\Backend\Config\Auth;

class UserModel extends Model
{
    /**
     * @var string
     */
    protected $table='users';

    /**
     * @var bool
     */
    protected $useTimestamps = true;

    /**
     * @param string $email
     * @param bool $success
     * @param int|null $falseCounter for loginAttempt false
     * @return mixed
     * @throws \Exception
     */
    public function recordLoginAttempt(string $email, bool $success, int $falseCounter = null)
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
            'counter' => ($success === false  ) ? $falseCounter+1 : null,
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

        return $this->m->insertOne('auth_tokens', [
            'user_id' => new ObjectId($userID),
            'selector' => $selector,
            'hashedValidator' => hash('sha256',$validator),
            'expires' => $expires->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     *
     */
    public function purgeOldRememberTokens()
    {
        $config = new Auth();
        if (!$config->allowRemembering) return;
        $this->m->options(['expires' =>['$lte'=> date('Y-m-d H:i:s')]])->deleteOne('auth_tokens');
    }

    /**
     * @param string $selector
     * @param string $validator
     * @return array|object|null
     */
    public function updateRememberValidator(string $selector, string $validator)
    {
        return $this->m->where(['selector' => $selector])->findOneAndUpdate('auth_tokens', ['hashedValidator' => hash('sha256', $validator)]);
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
     *
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
    public function countOr(string $collection, array $where,  array $or = [])
    {
        $builder=$this->db->table($collection);
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
    public function getOneOr(string $collection, array $where = [], string $order = 'id ASC', string $select = '*',array $or = [])
    {
        $builder=$this->db->table($collection);
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

    public function updateManyOr(string $collection, array $where, array $set, string $select = '*', array $or =[])
    {
        $builder=$this->db->table($collection);
        return $builder->select($select)->orWhere($or)->update($set,$where);
    }
}
