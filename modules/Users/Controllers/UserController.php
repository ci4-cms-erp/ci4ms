<?php

namespace Modules\Users\Controllers;

use App\Libraries\CommonLibrary;
use CodeIgniter\I18n\Time;
use Modules\Users\Models\UserscrudModel;

class UserController extends \Modules\Backend\Controllers\BaseController
{
    /**
     * @var UserscrudModel
     */
    protected $userModel;

    /**
     *
     */
    public function __construct()
    {
        $this->userModel = new UserscrudModel();
    }

    /**
     * @return string
     */
    public function users()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            $postData = ['group_id!=' => 1, 'deleted_at' => null];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->userModel->userList(
                ($data['length'] == '-1') ? 0 : (int)$data['length'],
                'users.id,email,firstname,surname,status,auth_groups.name,black_list_users.notes,reset_expires',
                [/* 'group_id!=' => 1,  */'deleted_at' => null],
                ($data['length'] == '-1') ? 0 : (int)$data['start']
            );
            //$results=$this->commonModel->lists('blog', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('users', $postData, $l);
            $timeClass = new Time();
            foreach ($results as $result) {
                $result->fullname = $result->firstname . ' ' . $result->surname;
                $result->actions = '<a href="' . route_to('update_user', $result->id) . '" class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>';
                if ($result->status == 'banned'):
                    $result->actions .= '<button class="btn btn-outline-dark btn-sm open-blacklist-modal"
                                            data-id="' . $result->id . '"><i
                                                class="fas fa-user-slash"></i> ' . lang('Users.inBlackList') . '
                                        </button>';
                else:
                    $result->actions .= '<button class="btn btn-outline-dark btn-sm open-blacklist-modal"
                                            data-id="' . $result->id . '"><i
                                                class="fas fa-user-slash"></i> ' . lang('Users.blackList') . '
                                        </button>';
                endif;
                $result->actions .= '<button class="btn btn-outline-dark btn-sm fpwd ';
                if (!empty($result->reset_expires)) {
                    $time = $timeClass::parse($result->reset_expires);
                    if (time() < $time->getTimestamp())
                        $result->actions .= 'disabled';
                }
                $result->actions .= '" data-uid="' . $result->id . '">' . lang('Users.resetPassword') . '</button>
                                    <a href="' . route_to('user_perms', $result->id) . '"
                                        class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-sitemap"></i> ' . lang('Users.spacialAuth') . '
                                    </a>
                                    <a class="btn btn-outline-danger btn-sm" href="' . route_to('user_del', $result->id) . '">' . lang('Backend.delete') . '</a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
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
                'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'surname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
                'group' => ['label' => 'Yetkisi', 'rules' => 'required'],
                'password' => ['label' => 'Şifre', 'rules' => 'required|min_length[8]']
            ]);

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            if ($this->commonModel->isHave('users', ['email' => $this->request->getPost('email')]) === 1) return redirect()->back()->withInput()->with('errors', ['E-posta adresi daha önce kayıt edilmiş lütfen üye listesini kontrol ediniz.']);
            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => $this->request->getPost('firstname'),
                'surname' => $this->request->getPost('surname'),
                'activate_hash' => $this->authLib->generateActivateHash(),
                'password_hash' => $this->authLib->setPassword($this->request->getPost('password')),
                'status' => 'deactive',
                'group_id' => $this->request->getPost('group'),
                'created_at' => new Time('now'),
                'who_created' => (int)session()->get('logged_in')
            ];
            $result = $this->commonModel->create('users', $data);
            $auth_users_permissions = [
                [
                    'page_id' => 1,
                    'create_r' => true,
                    'update_r' => true,
                    'read_r' => true,
                    'delete_r' => true,
                    'who_perm' => !empty($this->defData['logged_in_user']->id) ? $this->defData['logged_in_user']->id : NULL,
                    'created_at' => new Time('now'),
                    'user_id' => $result
                ],
                [
                    'page_id' => 9,
                    'create_r' => true,
                    'update_r' => true,
                    'read_r' => true,
                    'delete_r' => true,
                    'who_perm' => !empty($this->defData['logged_in_user']->id) ? $this->defData['logged_in_user']->id : NULL,
                    'created_at' => new Time('now'),
                    'user_id' => $result
                ],
                [
                    'page_id' => 10,
                    'create_r' => true,
                    'update_r' => true,
                    'read_r' => true,
                    'delete_r' => true,
                    'who_perm' => !empty($this->defData['logged_in_user']->id) ? $this->defData['logged_in_user']->id : NULL,
                    'created_at' => new Time('now'),
                    'user_id' => $result
                ]
            ];
            $this->commonModel->createMany('auth_users_permissions', $auth_users_permissions);
            if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Kullanıcı oluşturulamadı.');
            $commonLibrary = new CommonLibrary();
            $mailResult = $commonLibrary->phpMailer(
                'noreply@' . $_SERVER['HTTP_HOST'],
                'noreply@' . $_SERVER['HTTP_HOST'],
                [['mail' => $this->request->getPost('email')]],
                'noreply@' . $_SERVER['HTTP_HOST'],
                'Information',
                'Üyelik Aktivasyonu',
                'Üyeliğiniz şirket yetkilisi tarafından oluşturuldu. Üyeliğinizi aktif etmek için lütfen <a href="' . site_url('backend/activate-account/' . $data['activate_hash']) . '"><b>buraya</b></a> tıklayınız. Tıkladıktan sonra sizinle paylaşılan <b>email</b> ve <b>şifre</b> ile giriş yapabilirsiniz.<br>E-mail adresi : ' . $this->request->getPost('email') . '<br>Şifreniz : ' . $this->request->getPost('password')
            );
            if ($mailResult === true) return redirect()->route('users', [1])->with('message', lang('Auth.activationSuccess'));
            else return redirect()->back()->withInput()->with('error', $mailResult);
        }
        $this->defData['groups'] = $this->commonModel->lists('auth_groups');
        $this->defData['authLib'] = $this->authLib;
        return view('Modules\Users\Views\usersCrud\createUser', $this->defData);
    }

    /**
     * @param $id
     * @return string
     */
    public function update_user(int $id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'surname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
                'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
                'group' => ['label' => 'Yetkisi', 'rules' => 'required']
            ]);

            if ($this->request->getPost('password')) $valData['password'] = ['label' => 'Şifre', 'rules' => 'required|min_length[8]'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => $this->request->getPost('firstname'),
                'surname' => $this->request->getPost('surname'),
                'status' => 'deactive',
                'force_pass_reset' => false,
                'group_id' => $this->request->getPost('group'),
                'created_at' => new Time('now'),
                'who_created' => session()->get('logged_in')
            ];
            if ($this->request->getPost('password')) $data['password_hash'] = $this->authLib->setPassword($this->request->getPost('password'));

            $result = (string)$this->commonModel->edit('users', $data, ['id' => $id]);
            if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Kullanıcı oluşturulamadı.');
            else return redirect()->route('users', [1])->with('message', 'Üyelik Güncellendi.');
        }
        $this->defData['groups'] = $this->commonModel->lists('auth_groups');
        $this->defData['authLib'] = $this->authLib;
        $this->defData['userInfo'] = $this->commonModel->selectOne('users', ['id' => $id]);
        return view('Modules\Users\Views\usersCrud\updateUser', $this->defData);
    }

    /**
     * @param string $id
     */
    public function user_del(string $id)
    {
        if ($this->commonModel->edit('users', ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'deleted'], ['id' => $id]) === true) return redirect()->route('users', [1])->with('message', 'Üyelik Silindi.');
        return redirect()->route('users', [1])->with('error', 'Üyelik Silinemedi.');
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
            ]);

            if ($this->request->getPost('password')) $valData['password'] = ['label' => 'Şifre', 'rules' => 'required|min_length[8]'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $user = $this->commonModel->selectOne('users', ['id' => session()->get('logged_in')], 'email');

            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => $this->request->getPost('firstname'),
                'surname' => $this->request->getPost('surname')
            ];

            if ($this->request->getPost('password')) $data['password_hash'] = $this->authLib->setPassword($this->request->getPost('password'));

            if ($user->email != $data['email']) {
                if ($this->commonModel->isHave('users', ['id!=' => $user->id, 'email' => $this->request->getPost('email')]) === 1) return redirect()->back()->withInput()->with('error', 'Daha önce bu mail adresi başka bir kullanıcı tarafından alınmıştır lütfen bilgilerinizi kontrol ediniz.');

                $data['activate_hash'] = $this->authLib->generateActivateHash();
                $data['status'] = 'deactive';

                $result = $this->commonModel->edit('users', $data, ['id' => $user->id]);

                if ((bool)$result == true) {
                    $commonLibrary = new CommonLibrary();
                    $mailResult = $commonLibrary->phpMailer(
                        'noreply@' . $_SERVER['HTTP_HOST'],
                        'noreply@' . $_SERVER['HTTP_HOST'],
                        ['mail' => $this->request->getPost('email')],
                        'noreply@' . $_SERVER['HTTP_HOST'],
                        'Information',
                        'Mail Aktivasyonu',
                        'Mail adresiniz tarafınızdan güncellenmiştir. Lütfen <a href="' . site_url('backend/activate-email/' . $data['activate_hash']) . '"><b>buraya</b></a> tıklayınız.'
                    );
                    if ($mailResult === true) return redirect()->route('users', [1])->with('message', 'Üyelik oluşturuldu. Aktiflik maili gönderildi.');
                    else return redirect()->back()->withInput()->with('error', $mailResult);
                }
            } else $result = $this->commonModel->edit('users', $data, ['id' => session()->get('logged_in')]);

            if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Profil Güncellenemedi.');
            else return redirect()->back()->withInput()->with('message', 'Profil Güncellendi.');
        }
        $this->defData['user'] = $this->commonModel->selectOne('users', ['id' => session()->get('logged_in')], 'email,firstname,surname');
        return view('Modules\Users\Views\usersCrud\profile', $this->defData);
    }

    /**
     * @return false|string|string[]
     */
    public function ajax_blackList_post()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['note' => ['label' => 'Note', 'rules' => 'required'], 'uid' => ['label' => 'Kullanıcı id', 'rules' => 'required']]);
        if ($this->validate($valData) == false) return $this->validator->getErrors();
        $result = [];
        if ($this->commonModel->isHave('black_list_users', ['blacked_id' => $this->request->getPost('uid')]) === 0) $bid = $this->commonModel->create('black_list_users', ['blacked_id' => $this->request->getPost('uid'), 'who_blacklisted' => session()->get('logged_in'), 'notes' => $this->request->getPost('note'), 'created_at' => new Time('now')]);
        else $result = ['result' => true, 'error' => ['type' => 'warning', 'message' => 'üyelik karalisteye daha önce eklendi.']];

        if (!empty($bid) && $this->commonModel->edit('users', ['status' => 'banned', 'statusMessage' => $this->request->getPost('note')], ['id' => $this->request->getPost('uid')])) $result = ['result' => true, 'error' => ['type' => 'success', 'message' => 'üyelik karalisteye eklendi.']];
        else $result = ['result' => true, 'error' => ['type' => 'danger', 'message' => 'üyelik karalisteye eklenemedi.']];

        return $this->respond($result, 200);
    }

    public function ajax_remove_from_blackList_post()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['uid' => ['label' => 'Kullanıcı id', 'rules' => 'required']]);

        if ($this->validate($valData) == false) return $this->validator->getErrors();

        $result = [];

        $pwd = $this->authLib->randomPassword();
        $data = [
            'password_hash' => $this->authLib->setPassword($pwd),
            'status' => 'deactive',
            'activate_hash' => $this->authLib->generateActivateHash(),
            'statusMessage' => null
        ];
        if ($this->commonModel->update('users', $data, ['id' => $this->request->getPost('uid')]) && $this->commonModel->deleteOne('black_list_users', ['blacked_id' => $this->request->getPost('uid')])) {
            $user = $this->commonModel->selectOne('users', ['id' => $this->request->getPost('uid')], 'email');

            $commonLibrary = new CommonLibrary();
            $mailResult = $commonLibrary->phpMailer(
                'noreply@' . $_SERVER['HTTP_HOST'],
                'noreply@' . $_SERVER['HTTP_HOST'],
                ['mail' => $user->email],
                'noreply@' . $_SERVER['HTTP_HOST'],
                'Information',
                'Mail Aktivasyonu',
                'Üyeliğinizi yeniden aktif edebilimeniz için şirket yetkilisi müdehale etti. Üyeliğinizi aktif etmek için lütfen <a href="' . site_url('backend/activate-account/' . $data['activate_hash']) . '"><b>buraya</b></a> tıklayınız. Tıkladıktan sonra sizinle paylaşılan <b>email</b> ve <b>şifre</b> ile giriş yapabilirsiniz.<br>E-mail adresi : ' . $user->email . '<br>Şifreniz : ' . $pwd
            );
            if ($mailResult === true) $result = ['result' => true, 'error' => ['type' => 'success', 'message' => $user->email . ' e-mail adresli üyelik karalisteden çıkarıldı.']];
            else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => $mailResult]];
        } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => 'üyelik karalisteden çıkarılamadı.']];

        return $this->response->setJSON($result);
    }

    public function ajax_force_reset_password()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['uid' => ['label' => 'Kullanıcı id', 'rules' => 'required']]);

        if ($this->validate($valData) == false) return $this->validator->getErrors();

        $result = [];

        if ($this->commonModel->edit('users', ['status' => 'deactive', 'reset_hash' => $this->authLib->generateActivateHash(), 'reset_expires' => date('Y-m-d H:i:s', time() + $this->config->resetTime)], ['id' => $this->request->getPost('uid')])) {
            $user = $this->commonModel->selectOne('users', ['id' => $this->request->getPost('uid')]);
            $commonLibrary = new CommonLibrary();
            $mailResult = $commonLibrary->phpMailer(
                'noreply@' . $_SERVER['HTTP_HOST'],
                'noreply@' . $_SERVER['HTTP_HOST'],
                ['mail' => $user->email],
                'noreply@' . $_SERVER['HTTP_HOST'],
                'Information',
                'Üyelik Şifre Sıfırlama',
                'Üyeliğinizin şifre sıfırlaması yetkili gerçekleştirildi. Şifre yenileme isteğiniz ' . date('d-m-Y H:i:s', strtotime($user->reset_expires)) . ' tarihine kadar geçerlidir. Lütfen yeni şifrenizi belirlemek için <a href="' . site_url('backend/reset-password/' . $user->reset_hash) . '"><b>buraya</b></a> tıklayınız.'
            );
            if ($mailResult === true) $result = ['result' => true, 'error' => ['type' => 'success', 'message' => $user->email . ' e-posta adresli kullanıcıya şifre yenileme maili atıldı.']];
            else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => $mailResult]];
        } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => 'Şifre sıfırlama isteği gerçekleştirilemedi.']];

        return $this->response->setJSON($result);
    }
}
