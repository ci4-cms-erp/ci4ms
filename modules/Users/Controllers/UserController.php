<?php

namespace Modules\Users\Controllers;

use CodeIgniter\Shield\Authentication\Actions\EmailActivator;
use CodeIgniter\Shield\Entities\User;

class UserController extends \Modules\Backend\Controllers\BaseController
{
    /**
     * @return string
     */
    public function users()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            $users = auth()->getProvider();
            $users->select('users.*, auth_identities.secret as email, auth_identities.force_reset')
                ->withGroups()
                ->withPermissions()
                ->join('auth_identities', 'auth_identities.user_id = users.id')
                ->where(['users.deleted_at' => null])
                ->whereNotIn('users.id', function ($builder) {
                    return $builder->select('user_id')->from('auth_groups_users')->join('auth_groups', 'auth_groups.group = auth_groups_users.group')->where('auth_groups.group', 'superadmin');
                });
            if (!empty($like)) {
                $l = ['firstname' => $like, 'surname' => $like, 'secret' => $like];
                $users->groupStart();
                foreach ($l as $field => $value) {
                    $users->orLike($field, $value);
                }
                $users->groupEnd();
            }
            $results = $users->findAll(($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start']);

            $users->select('users.*, auth_identities.secret as email')
                ->join('auth_identities', 'auth_identities.user_id = users.id')
                ->where(['users.deleted_at' => null])
                ->whereNotIn('users.id', function ($builder) {
                    return $builder->select('user_id')->from('auth_groups_users')->join('auth_groups', 'auth_groups.group = auth_groups_users.group')->where('auth_groups.group', 'superadmin');
                });
            if (!empty($like)) {
                $l = ['firstname' => $like, 'surname' => $like, 'secret' => $like];
                $users->groupStart();
                foreach ($l as $field => $value) {
                    $users->orLike($field, $value);
                }
                $users->groupEnd();
            }
            $totalRecords = $users->countAllResults();
            foreach ($results as $result) {
                $result->groupName = implode(',', $result->getGroups());
                $result->fullname = esc($result->firstname) . ' ' . esc($result->surname);
                $result->actions = '<a href="' . route_to('update_user', $result->id) . '" class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>';
                if ($result->status == 'banned'):
                    $result->actions .= '<button type="button" class="btn btn-outline-dark btn-sm open-blacklist-modal"
                                            data-id="' . $result->id . '" data-status="' . $result->status . '" data-note="' . $result->status_message . '"><i
                                                class="fas fa-user-slash"></i> ' . lang('Users.inBlackList') . '
                                        </button>';
                else:
                    $result->actions .= '<button type="button" class="btn btn-outline-dark btn-sm open-blacklist-modal"
                                            data-id="' . $result->id . '" data-status="' . $result->status . '"><i
                                                class="fas fa-user-slash"></i> ' . lang('Users.blackList') . '
                                        </button>';
                endif;
                $result->actions .= '<button type="button" class="btn btn-outline-dark btn-sm fpwd' . $result->id . ' ';
                if (!empty($result->force_reset)) {
                    $result->actions .= 'disabled';
                }
                $result->actions .= '" onclick="forceResetPassword(' . $result->id . ')" ';
                if (!empty($result->force_reset)) {
                    $result->actions .= 'disabled';
                }
                $result->actions .= '>' . lang('Users.resetPassword') . '</button>
                                    <a href="' . route_to('user_perms', $result->id) . '"
                                        class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-sitemap"></i> ' . lang('Users.spacialAuth') . '
                                    </a>
                                    <a href="javascript:void(0);" onclick="deleteItem(' . $result->id . ')"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => array_values($results)
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Users\Views\usersCrud\list', $this->defData);
    }

    /**
     * @return string
     */
    public function create_user()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'username' => 'required|regex_match[/\A[a-zA-Z0-9\.]+\z/]|min_length[3]|max_length[30]|is_unique[users.username]',
                'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'surname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email|is_unique[auth_identities.secret]'],
                'group' => ['label' => 'Yetkisi', 'rules' => 'required'],
                'password' => ['label' => 'Şifre', 'rules' => 'required|min_length[8]']
            ]);

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $users = auth()->getProvider();
            try {
                $d = [
                    'email' => $this->request->getPost('email'),
                    'firstname' => esc($this->request->getPost('firstname')),
                    'surname' => esc($this->request->getPost('surname')),
                    'password' => $this->request->getPost('password'),
                    'active' => false,
                    'username' => esc($this->request->getPost('username')),
                    'who_created' => user_id()
                ];
                $user = new User($d);

                if (!$users->save($user)) return redirect()->back()->withInput()->with('errors', $users->errors());
                $new_user = $users->findById($users->getInsertID());

                $group = $this->commonModel->selectOne('auth_groups', ['id' => $this->request->getPost('group')]);
                $new_user->syncGroups($group->group);

                $activator = new EmailActivator();

                $code = $activator->createIdentity($new_user);

                $emailSent = $this->sendActivationEmail($new_user, $code);
                if (!$emailSent) {
                    throw new \Exception($emailSent->printDebugger(['headers']));
                }

                return redirect()->route('users')->with('message', lang('Auth.activationSuccess'));
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }
        $this->defData['groups'] = $this->commonModel->lists('auth_groups', '*', ['group!=' => 'superadmin']);
        $this->defData['authLib'] = $this->authLib;
        return view('Modules\Users\Views\usersCrud\createUser', $this->defData);
    }

    private function sendActivationEmail($user, $code)
    {
        $url = url_to('register-verify-account');
        $fullUrl = $url . '?token=' . $code;
        $email = service('email');
        $email->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $email->setTo($user->email);
        $email->setSubject(lang('Auth.emailActivateSubject'));

        $email->setMessage(view(
            'Modules\Users\Views\usersCrud\Email\email_activate_email',
            ['url'  => $fullUrl, 'user' => $user],
            ['debug' => false],
        ));
        return $email->send();
    }

    /**
     * @param $id
     * @return string
     */
    public function update_user(int $id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'username' => 'required|regex_match[/\A[a-zA-Z0-9\.]+\z/]|min_length[3]|max_length[30]',
                'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'surname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
                'group' => ['label' => 'Yetkisi', 'rules' => 'required']
            ]);

            if ($this->request->getPost('password')) $valData['password'] = ['label' => 'Şifre', 'rules' => 'required|min_length[8]'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $user = auth()->getProvider();
            if ($user->inGroup('superadmin')) return redirect()->route('403');
            $u = $user->findById($id);
            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => esc($this->request->getPost('firstname')),
                'surname' => esc($this->request->getPost('surname')),
                'group_id' => $this->request->getPost('group'),
                'update_at' => date('Y-m-d H:i:s'),
                'username' => esc($this->request->getPost('username')),
                'who_created' => user_id(),
            ];
            if ($this->request->getPost('password')) $data['password'] = $this->request->getPost('password');

            $u->fill($data);
            if ($user->save($u)) return redirect()->route('users')->with('message', lang('Backend.updated', [$data['username']]));
            else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [$data['username']]));
        }
        $user = auth()->getProvider();
        $this->defData['groups'] = $this->commonModel->lists('auth_groups', '*', ['group!=' => 'superadmin']);
        $this->defData['userInfo'] = $user->findById($id);

        return view('Modules\Users\Views\usersCrud\updateUser', $this->defData);
    }

    /**
     * @param string $id
     */
    public function user_del(string $id)
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $user = auth()->getProvider();
        if ($user->inGroup('superadmin')) return redirect()->route('403');
        if ($user->delete($this->request->getPost('id'), true)) {
            return  $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$user->username])]);
        }
        return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$user->username])]);
    }

    /**
     * @return string
     */
    public function profile()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'surname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
                'profileIMG' => [
                    'label' => 'Profil Resmi',
                    'rules' => 'is_image[profileIMG]|mime_in[profileIMG,image/jpg,image/jpeg,image/png,image/webp]|max_size[profileIMG,2048]|max_dims[profileIMG,150,150]'
                ]
            ]);

            if ($this->request->getPost('password')) {
                $valData['password'] = ['label' => 'Şifre', 'rules' => 'required|min_length[8]'];
            }

            if ($this->validate($valData) == false) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $users = auth()->getProvider();

            $user = $users->findById(user_id());

            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => esc($this->request->getPost('firstname')),
                'surname' => esc($this->request->getPost('surname'))
            ];

            // Image Upload Handling
            $file = $this->request->getFile('profileIMG');
            if ($file->isValid() && !$file->hasMoved()) {
                $newName = $file->getRandomName();
                if ($file->move(FCPATH . 'media/avatars', $newName)) {
                    $data['profileIMG'] = '/media/avatars/' . $newName;
                    // Optional: Delete old image if it exists and isn't default
                    if (!empty($user->profileIMG) && file_exists(FCPATH . 'media/avatars/' . $user->profileIMG)) {
                        @unlink(FCPATH . 'media/avatars/' . $user->profileIMG);
                    }
                }
            }

            if ($this->request->getPost('password')) {
                $data['password'] = $this->request->getPost('password');
            }

            if ($user->email != $data['email']) {
                $existingUser = $users->findByCredentials(['email' => $data['email']]);
                if ($existingUser && $existingUser->id != $user->id) {
                    return redirect()->back()->withInput()->with('error', 'Daha önce bu mail adresi başka bir kullanıcı tarafından alınmıştır lütfen bilgilerinizi kontrol ediniz.');
                }


                $user->fill($data);
                $result = $users->save($user);
                if ($result) {
                    $activator = new EmailActivator();
                    $code = $activator->createIdentity($user);
                    $url = url_to('register-verify-account');
                    $fullUrl = $url . '?token=' . $code;
                    $email = service('email');
                    $email->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
                    $email->setTo($user->email);
                    $email->setSubject(lang('Auth.emailActivateSubject'));

                    $email->setMessage(view(
                        'Modules\Users\Views\usersCrud\Email\profile_email_activate_email',
                        ['url'  => $fullUrl, 'user' => $user],
                        ['debug' => false],
                    ));
                    if (!$email->send()) {
                        throw new \Exception($email->printDebugger(['headers']));
                    }
                }
                if ($result) {
                    return redirect()->route('logout');
                }
            } else {
                $user->fill($data);
                $result = $users->save($user);
            }

            if ((bool)$result == false) return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [esc($user->firstname . ' ' . $user->surname)]));
            else return redirect()->back()->with('message', lang('Backend.updated', [esc($user->firstname . ' ' . $user->surname)]));
        }
        $this->defData['user'] = auth()->getProvider()->findById(user_id());
        return view('Modules\Users\Views\usersCrud\profile', $this->defData);
    }

    /**
     * @return false|string|string[]
     */
    public function ajax_blackList_post()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['note' => ['label' => 'Note', 'rules' => 'required'], 'uid' => ['label' => 'uid', 'rules' => 'required|is_natural_no_zero']]);
        if ($this->validate($valData) == false) return $this->validator->getErrors();

        $user = auth()->getProvider()->findById($this->request->getPost('uid'));
        if ($user->inGroup('superadmin')) return $this->failForbidden();
        $user->ban($this->request->getPost('note'));
        $this->commonModel->edit('auth_identities', ['who_banned' => user_id()], ['user_id' => $this->request->getPost('uid')],);
        $result = [];

        if ($user->isBanned()) {
            $result = ['result' => true, 'error' => ['type' => 'success', 'message' => 'üyelik karalisteye eklendi.']];
            $user->forcePasswordReset();
        } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => 'üyelik karalisteye eklenemedi.']];

        return $this->respond($result, 200);
    }

    public function ajax_remove_from_blackList_post()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['uid' => ['label' => 'Kullanıcı id', 'rules' => 'required|is_natural_no_zero']]);

        if ($this->validate($valData) == false) return $this->validator->getErrors();
        $user = auth()->getProvider()->findById($this->request->getPost('uid'));
        if ($user->inGroup('superadmin')) return $this->failForbidden();

        $user->unBan();
        $this->commonModel->edit('auth_identities', ['who_banned' => null], ['user_id' => $this->request->getPost('uid')],);
        $result = [];
        if (!$user->isBanned()) {
            $result = ['result' => true, 'error' => ['type' => 'success', 'message' => $user->email . ' e-mail adresli üyelik karalisteden çıkarıldı.']];
            $user->undoForcePasswordReset();
        } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => 'üyelik karalisteden çıkarılamadı.']];

        return $this->response->setJSON($result);
    }

    public function ajax_force_reset_password()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['uid' => ['label' => 'Kullanıcı id', 'rules' => 'required|is_natural_no_zero']]);

        if ($this->validate($valData) == false) return $this->validator->getErrors();

        $user = auth()->getProvider()->findById($this->request->getPost('uid'));
        if ($user->inGroup('superadmin')) return $this->failForbidden();
        $result = [];
        if ($user->requiresPasswordReset()) {
            $result = ['result' => true, 'error' => ['type' => 'warning', 'message' => $user->email . ' e-mail adresli üyelik şifre sıfırlama adımında.']];
        } else {
            $user->forcePasswordReset();
            if ($user->requiresPasswordReset())
                $result = ['result' => true, 'error' => ['type' => 'success', 'message' => $user->email . ' e-mail adresli üyelik şifre sıfırlandı.']];
            else
                $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => $user->email . ' e-mail adresli üyelik şifre sıfırlanamadı.']];
        }

        return $this->response->setJSON($result);
    }
}
