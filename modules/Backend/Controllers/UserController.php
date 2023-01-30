<?php namespace Modules\Backend\Controllers;

use App\Libraries\CommonLibrary;
use CodeIgniter\I18n\Time;
use JasonGrimes\Paginator;
use Modules\Backend\Models\UserscrudModel;

/**
 *
 */
class UserController extends BaseController
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
    public function officeWorker()
    {
        $this->defData['timeClass'] = new Time();
        $totalItems = $this->commonModel->count('users', ['group_id!=' => 1, 'deleted_at' => null]);
        $itemsPerPage = 20;
        $currentPage = $this->request->uri->getSegment('3', 1);
        $urlPattern = '/backend/officeWorker/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        $bpk = ($this->request->uri->getSegment(3, 1) - 1) * $itemsPerPage;
        $this->defData['userLists'] = $this->userModel->userList($itemsPerPage,
            'users.id,email,firstname,sirname,status,auth_groups.name,black_list_users.notes,reset_expires',
            [/*'group_id!=' => 1,*/ 'deleted_at' => null], $bpk);
        return view('Modules\Backend\Views\usersCrud\officeWorkerUsers', $this->defData);
    }

    /**
     * @return string
     */
    public function create_user()
    {
        $this->defData['groups'] = $this->commonModel->lists('auth_groups');
        $this->defData['authLib'] = $this->authLib;

        return view('Modules\Backend\Views\usersCrud\createUser', $this->defData);
    }

    public function create_user_post()
    {
        $valData = ([
            'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
            'sirname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
            'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
            'group' => ['label' => 'Yetkisi', 'rules' => 'required'],
            'password' => ['label' => 'Şifre', 'rules' => 'required|min_length[8]']
        ]);

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        if ($this->commonModel->isHave('users',['email' => $this->request->getPost('email')]) === 1) return redirect()->back()->withInput()->with('errors', ['E-posta adresi daha önce kayıt edilmiş lütfen üye listesini kontrol ediniz.']);
        d(session()->get('logged_in'));
        $data = [
            'email' => $this->request->getPost('email'),
            'firstname' => $this->request->getPost('firstname'),
            'sirname' => $this->request->getPost('sirname'),
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
                'who_perm' => null,
                'created_at' => new Time('now'),
                'user_id'=>$result
            ],
            [
                'page_id' => 9,
                'create_r' => true,
                'update_r' => true,
                'read_r' => true,
                'delete_r' => true,
                'who_perm' => null,
                'created_at' => new Time('now'),
                'user_id'=>$result
            ],
            [
                'page_id' => 10,
                'create_r' => true,
                'update_r' => true,
                'read_r' => true,
                'delete_r' => true,
                'who_perm' => null,
                'created_at' => new Time('now'),
                'user_id'=>$result
            ]
        ];
        $this->commonModel->createMany('auth_users_permissions',$auth_users_permissions);
        if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Kullanıcı oluşturulamadı.');
        $commonLibrary = new CommonLibrary();
        $mailResult = $commonLibrary->phpMailer('noreply@ci4ms.com', 'noreply@ci4ms.com',
            [['mail' => $this->request->getPost('email')]],
            'noreply@ci4ms.com', 'Information',
            'Üyelik Aktivasyonu',
            'Üyeliğiniz şirket yetkilisi tarafından oluşturuldu. Üyeliğinizi aktif etmek için lütfen <a href="' . site_url('backend/activate-account/' . $data['activate_hash']) . '"><b>buraya</b></a> tıklayınız. Tıkladıktan sonra sizinle paylaşılan <b>email</b> ve <b>şifre</b> ile giriş yapabilirsiniz.<br>E-mail adresi : ' . $this->request->getPost('email') . '<br>Şifreniz : ' . $this->request->getPost('password')
        );
        if ($mailResult === true) return redirect()->route('officeWorker', [1])->with('message', 'Üyelik oluşturuldu. Aktiflik maili gönderildi.');
        else return redirect()->back()->withInput()->with('error', $mailResult);
    }

    /**
     * @param $id
     * @return string
     */
    public function update_user($id)
    {
        $this->defData['groups'] = $this->commonModel->lists('auth_groups');
        $this->defData['authLib'] = $this->authLib;
        $this->defData['userInfo'] = $this->commonModel->selectOne('users', ['id' => $id]);
        return view('Modules\Backend\Views\usersCrud\updateUser', $this->defData);
    }

    /**
     * @param $id
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function update_user_post($id)
    {
        $valData = ([
            'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
            'sirname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
            'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
            'group' => ['label' => 'Yetkisi', 'rules' => 'required']
        ]);

        if ($this->request->getPost('password')) $valData['password'] = ['label' => 'Şifre', 'rules' => 'required|min_length[8]'];

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $data = [
            'email' => $this->request->getPost('email'),
            'firstname' => $this->request->getPost('firstname'),
            'sirname' => $this->request->getPost('sirname'),
            'status' => 'deactive',
            'force_pass_reset' => false,
            'group_id' => $this->request->getPost('group'),
            'created_at' => new Time('now'),
            'who_created' => session()->get('logged_in')
        ];
        if ($this->request->getPost('password')) $data['password_hash'] = $this->authLib->setPassword($this->request->getPost('password'));

        $result = (string)$this->commonModel->edit('users', $data,['id' => $id]);

        if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Kullanıcı oluşturulamadı.');
        else return redirect()->route('officeWorker', [1])->with('message', 'Üyelik Güncellendi.');
    }

    /**
     * @param string $id
     */
    public function user_del(string $id)
    {
        if ($this->commonModel->update('users', ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'deleted'], ['id' => $id]) === true) return redirect()->route('officeWorker', [1])->with('message', 'Üyelik Silindi.');
        return redirect()->route('officeWorker', [1])->with('error', 'Üyelik Silinemedi.');
    }

    /**
     * @return string
     */
    public function profile()
    {
        $this->defData['user'] = $this->commonModel->selectOne('users', ['id' => session()->get('logged_in')], 'email,firstname,sirname');
        return view('Modules\Backend\Views\usersCrud\profile', $this->defData);
    }

    public function profile_post()
    {
        $valData = ([
            'firstname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
            'sirname' => ['label' => 'Ad Soyadı', 'rules' => 'required'],
            'email' => ['label' => 'E-posta adresi', 'rules' => 'required|valid_email'],
        ]);

        if ($this->request->getPost('password')) $valData['password'] = ['label' => 'Şifre', 'rules' => 'required|min_length[8]'];

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $user = $this->commonModel->selectOne('users', ['id' => session()->get('logged_in')], 'email');

        $data = [
            'email' => $this->request->getPost('email'),
            'firstname' => $this->request->getPost('firstname'),
            'sirname' => $this->request->getPost('sirname')
        ];

        if ($this->request->getPost('password')) $data['password_hash'] = $this->authLib->setPassword($this->request->getPost('password'));

        if ($user->email != $data['email']) {
            if ($this->commonModel->isHave('users',['id!=' => $user->id, 'email' => $this->request->getPost('email')]) === 1) return redirect()->back()->withInput()->with('error', 'Daha önce bu mail adresi başka bir kullanıcı tarafından alınmıştır lütfen bilgilerinizi kontrol ediniz.');

            $data['activate_hash'] = $this->authLib->generateActivateHash();
            $data['status'] = 'deactive';

            $result = $this->commonModel->edit('users', $data,['id' => $user->id]);

            if ((bool)$result == true) {
                $commonLibrary = new CommonLibrary();
                $mailResult = $commonLibrary->phpMailer('noreply@ci4ms.com', 'noreply@ci4ms.com',
                    ['mail' => $this->request->getPost('email')],
                    'noreply@ci4ms.com', 'Information',
                    'Mail Aktivasyonu',
                    'Mail adresiniz tarafınızdan güncellenmiştir. Lütfen <a href="' . site_url('backend/activate-email/' . $data['activate_hash']) . '"><b>buraya</b></a> tıklayınız.'
                );
                if ($mailResult === true) return redirect()->route('officeWorker', [1])->with('message', 'Üyelik oluşturuldu. Aktiflik maili gönderildi.');
                else return redirect()->back()->withInput()->with('error', $mailResult);
            }
        } else $result = $this->commonModel->edit('users', $data,['id' =>session()->get('logged_in')]);

        if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Profil Güncellenemedi.');
        else return redirect()->back()->withInput()->with('message', 'Profil Güncellendi.');
    }

    /**
     * @return false|string|string[]
     */
    public function ajax_blackList_post()
    {
        if ($this->request->isAJAX()) {
            $valData = (['note' => ['label' => 'Note', 'rules' => 'required'], 'uid' => ['label' => 'Kullanıcı id', 'rules' => 'required']]);

            if ($this->validate($valData) == false) return $this->validator->getErrors();

            $result = [];

            if ($this->commonModel->isHave('black_list_users',['blacked_id' => $this->request->getPost('uid')]) === 0) $bid = $this->commonModel->create('black_list_users', ['blacked_id' => $this->request->getPost('uid'), 'who_blacklisted' => session()->get('logged_in'), 'notes' => $this->request->getPost('note'), 'created_at' => new Time('now')]);
            else $result = ['result' => true, 'error' => ['type' => 'warning', 'message' => 'üyelik karalisteye daha önce eklendi.']];

            if (!empty($bid) && $this->commonModel->edit('users', ['status' => 'banned', 'statusMessage' => $this->request->getPost('note')],['id' => $this->request->getPost('uid')])) $result = ['result' => true, 'error' => ['type' => 'success', 'message' => 'üyelik karalisteye eklendi.']];
            else $result = ['result' => true, 'error' => ['type' => 'danger', 'message' => 'üyelik karalisteye eklenemedi.']];

            return $this->response->setJSON($result);
        }
    }

    public function ajax_remove_from_blackList_post()
    {
        if ($this->request->isAJAX()) {
            $valData = (['uid' => ['label' => 'Kullanıcı id', 'rules' => 'required']]);

            if ($this->validate($valData) == false) return $this->validator->getErrors();

            $result = [];

            $pwd = $this->authLib->randomPassword();
            $data = ['password_hash' => $this->authLib->setPassword($pwd),
                'status' => 'deactive',
                'activate_hash' => $this->authLib->generateActivateHash(),
                'statusMessage' => null];
            if ($this->commonModel->update('users', $data,['id' => $this->request->getPost('uid')]) && $this->commonModel->deleteOne('black_list_users', ['blacked_id' => new ObjectId($this->request->getPost('uid'))])) {
                $user = $this->commonModel->selectOne('users', ['id' =>$this->request->getPost('uid')], 'email');

                $commonLibrary = new CommonLibrary();
                $mailResult = $commonLibrary->phpMailer('noreply@ci4ms.com', 'noreply@ci4ms.com',
                    ['mail' => $user->email],
                    'noreply@ci4ms.com', 'Information',
                    'Mail Aktivasyonu',
                    'Üyeliğinizi yeniden aktif edebilimeniz için şirket yetkilisi müdehale etti. Üyeliğinizi aktif etmek için lütfen <a href="' . site_url('backend/activate-account/' . $data['activate_hash']) . '"><b>buraya</b></a> tıklayınız. Tıkladıktan sonra sizinle paylaşılan <b>email</b> ve <b>şifre</b> ile giriş yapabilirsiniz.<br>E-mail adresi : ' . $user->email . '<br>Şifreniz : ' . $pwd
                );
                if ($mailResult === true) $result = ['result' => true, 'error' => ['type' => 'success', 'message' => $user->email . ' e-mail adresli üyelik karalisteden çıkarıldı.']];
                else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => $mailResult]];
            } else
                $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => 'üyelik karalisteden çıkarılamadı.']];

            return $this->response->setJSON($result);
        }
    }

    public function ajax_force_reset_password()
    {
        if ($this->request->isAJAX()) {
            $valData = (['uid' => ['label' => 'Kullanıcı id', 'rules' => 'required']]);

            if ($this->validate($valData) == false) return $this->validator->getErrors();

            $result = [];

            if ($this->commonModel->edit('users', ['status' => 'deactive', 'reset_hash' => $this->authLib->generateActivateHash(), 'reset_expires' => date('Y-m-d H:i:s', time() + $this->config->resetTime)],['id' => $this->request->getPost('uid')])) {
                $user = $this->commonModel->selectOne('users', ['id' => $this->request->getPost('uid')]);
                $commonLibrary = new CommonLibrary();
                $mailResult = $commonLibrary->phpMailer('noreply@ci4ms.com', 'noreply@ci4ms.com',
                    ['mail' => $user->email],
                    'noreply@ci4ms.com', 'Information',
                    'Üyelik Şifre Sıfırlama',
                    'Üyeliğinizin şifre sıfırlaması yetkili gerçekleştirildi. Şifre yenileme isteğiniz ' . date('d-m-Y H:i:s', strtotime($user->reset_expires)) . ' tarihine kadar geçerlidir. Lütfen yeni şifrenizi belirlemek için <a href="' . site_url('backend/reset-password/' . $user->reset_hash) . '"><b>buraya</b></a> tıklayınız.'
                );
                if ($mailResult === true) $result = ['result' => true, 'error' => ['type' => 'success', 'message' => $user->email . ' e-posta adresli kullanıcıya şifre yenileme maili atıldı.']];
                else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => $mailResult]];
            } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => 'Şifre sıfırlama isteği gerçekleştirilemedi.']];

            return $this->response->setJSON($result);
        }
    }
}
