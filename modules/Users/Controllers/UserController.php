<?php

namespace Modules\Users\Controllers;

use CodeIgniter\Shield\Authentication\Actions\EmailActivator;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Models\UserSessionModel;

class UserController extends \Modules\Backend\Controllers\BaseController
{
    protected UserSessionModel $sessionModel;
    public function __construct()
    {
        helper(['device', 'url', 'form']);
        $this->sessionModel = new UserSessionModel();
    }
    /**
     * @return \CodeIgniter\HTTP\ResponseInterface|string
     */
    public function users()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
            $like = $parsed['searchString'];
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
                $like = ['firstname' => $like, 'surname' => $like, 'secret' => $like];
                $users->groupStart();
                foreach ($like as $field => $value) {
                    $users->orLike($field, $parsed['searchString']);
                }
                $users->groupEnd();
            }
            $results = $users->findAll($parsed['length'], $parsed['start']);
            $users->select('users.*, auth_identities.secret as email')
                ->join('auth_identities', 'auth_identities.user_id = users.id')
                ->where(['users.deleted_at' => null])
                ->whereNotIn('users.id', function ($builder) {
                    return $builder->select('user_id')->from('auth_groups_users')->join('auth_groups', 'auth_groups.group = auth_groups_users.group')->where('auth_groups.group', 'superadmin');
                });
            if (!empty($like)) {
                $like = ['firstname' => $like, 'surname' => $like, 'secret' => $like];
                $users->groupStart();
                foreach ($like as $field => $value) {
                    $users->orLike($field, $parsed['searchString']);
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
                                            data-id="' . $result->id . '" data-status="' . $result->status . '" data-note="' . esc($result->status_message) . '"><i
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
                'draw' => $parsed['draw'],
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => array_values($results)
            ];
            return $this->respond($data, 200);
        }
        $subquerySuperAdmin = function ($builder) {
            return $builder->select('user_id')->from('auth_groups_users')->join('auth_groups', 'auth_groups.group = auth_groups_users.group')->where('auth_groups.group', 'superadmin');
        };

        $this->defData['stats'] = [
            'total' => auth()->getProvider()->whereNotIn('users.id', $subquerySuperAdmin)->countAllResults(),
            'active' => auth()->getProvider()->where('status', null)->whereNotIn('users.id', $subquerySuperAdmin)->countAllResults(),
            'banned' => auth()->getProvider()->where('status', 'banned')->whereNotIn('users.id', $subquerySuperAdmin)->countAllResults()
        ];
        return view('Modules\Users\Views\usersCrud\list', $this->defData);
    }

    /**
     * Creates a new user from POST data and assigns the posted group(s), or
     * renders the create form on GET.
     *
     * Subject to the same delegation ceiling as update_user(): syncGroups()
     * only checks that the posted group names exist, not their permission
     * level relative to the actor's own, so actorMayAssignGroup() must run
     * before the user row is created.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|string Redirect on POST, rendered view on GET
     */
    public function create_user()
    {
        $this->defData['languages'] = $this->commonModel->lists('languages', 'code,name');
        $languages = array_map(function ($language) {
            return $language->code;
        }, $this->defData['languages']);
        if ($this->request->is('post')) {
            $valData = ([
                'username' => 'required|regex_match[/\A[a-zA-Z0-9\.]+\z/]|min_length[3]|max_length[30]|is_unique[users.username]',
                'firstname' => ['label' => lang('Backend.firstName'), 'rules' => 'required|regex_match[/^[^\x3c\x3e\x7b\x7d\x3d]+$/u]'],
                'surname' => ['label' => lang('Backend.lastName'), 'rules' => 'required|regex_match[/^[^\x3c\x3e\x7b\x7d\x3d]+$/u]'],
                'email' => ['label' => lang('Auth.email'), 'rules' => 'required|valid_email|is_unique[auth_identities.secret]'],
                'group.*' => ['label' => lang('Users.authority'), 'rules' => 'required|is_natural_no_zero'],
                'password' => ['label' => lang('Auth.password'), 'rules' => 'required|min_length[8]'],
                'own_language' => ['label' => lang('Backend.ownLanguage'), 'rules' => 'required|in_list[' . implode(',', $languages) . ']'],
            ]);

            if ($this->validate($valData) === false) return redirect()->route('create_user')->withInput()->with('errors', $this->validator->getErrors());

            $users = auth()->getProvider();
            try {
                $groups = $this->commonModel->lists('auth_groups', 'group', ['group!=' => 'superadmin'], 'id ASC', 0, 0, [], [], [], ['isReset' => false,], ['key' => 'id', 'where' => $this->request->getPost('group')]);
                $groupNames = array_column($groups, 'group');

                // Delegation ceiling: checked before the user row is created
                // so a rejected request never leaves an orphaned, groupless
                // account behind.
                if (!auth()->user()->inGroup('superadmin') && !$this->actorMayAssignGroup($groupNames))
                    return $this->failForbidden(lang('Users.groupExceedsOwnGrant'));

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

                if (!$users->save($user)) return redirect()->route('create_user')->withInput()->with('errors', $users->errors());
                $new_user = $users->findById($users->getInsertID());

                $new_user->syncGroups(...$groupNames);


                $activator = new EmailActivator();

                $code = $activator->createIdentity($new_user);

                $emailSent = $this->sendActivationEmail($new_user, $code);
                if (!$emailSent) {
                    throw new \Exception($emailSent->printDebugger(['headers']));
                }

                return redirect()->route('users')->with('message', lang('Auth.activationSuccess'));
            } catch (\Exception $e) {
                return redirect()->route('create_user')->withInput()->with('error', $e->getMessage());
            }
        }
        $this->defData['groups'] = $this->commonModel->lists('auth_groups', '*', ['group!=' => 'superadmin']);
        $this->defData['authLib'] = $this->authLib;
        return view('Modules\Users\Views\usersCrud\form', $this->defData);
    }

    /**
     * Enforces a delegation ceiling for group assignment: a non-superadmin
     * actor may only assign groups whose entire permission matrix is covered
     * by the actor's own effective permissions.
     *
     * Mirrors the subset logic in
     * PermgroupController::actorGrantsSubsetOfOwnPermissions(), kept as a
     * separate implementation here because auth_groups.permissions is
     * decoded per-group rather than compared against a single POSTed
     * perms[] payload. Without this check, Shield's
     * Authorizable::syncGroups() only verifies the group exists
     * (GroupModel::isValidGroup()) -- it never compares the group's
     * permission level to the actor's own, so a non-superadmin holding only
     * users.create_user.create / users.update_user.update could otherwise
     * assign themselves or a peer into a group more powerful than their
     * own. Callers must skip this check for superadmin actors themselves
     * (superadmin may assign any group); this method does not check
     * inGroup('superadmin') itself.
     *
     * @param array<int, string> $targetGroupNames auth_groups.group names being assigned to the target user
     *
     * @return bool True if every permission granted by every target group is one the actor already holds
     */
    private function actorMayAssignGroup(array $targetGroupNames): bool
    {
        if ($targetGroupNames === []) {
            return true;
        }

        $pageMap = [];
        foreach ($this->commonModel->lists('auth_permissions_pages') as $page) {
            $pageMap[$page->id] = strtolower($page->pagename);
        }

        $roleActionMap = ['create_r' => 'create', 'read_r' => 'read', 'update_r' => 'update', 'delete_r' => 'delete'];

        $groups = $this->commonModel->lists('auth_groups', 'permissions', [], 'id ASC', 0, 0, [], [], [], ['isReset' => false,], ['key' => 'group', 'where' => $targetGroupNames]);
        foreach ($groups as $group) {
            $groupPerms = json_decode($group->permissions ?? '', true) ?? [];
            foreach ($groupPerms as $perm) {
                $pageId = $perm['page_id'] ?? null;
                if (!isset($pageMap[$pageId]))
                    continue;

                foreach ($roleActionMap as $roleKey => $action) {
                    if (!empty($perm[$roleKey]) && !auth()->user()->can(permission_string($pageMap[$pageId], $action)))
                        return false;
                }
            }
        }

        return true;
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
     * Updates an existing user's profile, group memberships, and (optionally)
     * password from POST data, or renders the edit form on GET.
     *
     * Three privilege-escalation guards were missing before this fix: (1) the
     * actor could target their own account id and change their own group
     * membership (self-escalation) -- self-targeting is now rejected
     * outright, since self-service editing already has a dedicated, safer
     * path (profile(), which additionally verifies the current password
     * before accepting a new one). (2) syncGroups() (Shield's Authorizable
     * trait) only checks that the posted group names exist -- it never
     * compares the target group's permission level to the actor's own -- so
     * a non-superadmin actor could otherwise promote a peer into a group
     * more powerful than the actor's own. actorMayAssignGroup() closes that
     * gap and, like the superadmin/self-target guards, must run before the
     * write. (3) a POSTed password was written to the target account with no
     * current-password verification -- the only such check exists in
     * profile(), and this route's authorization is a delegable granular
     * permission (users.update), not superadmin-only. A non-superadmin actor
     * holding that permission could therefore reset any peer's password and
     * take over the account without ever knowing the original password. The
     * password field is now rejected outright for non-superadmin actors
     * targeting another account; superadmin actors are unaffected.
     *
     * @param int $id users.id of the account being updated
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|string Redirect on POST, rendered view on GET
     */
    public function update_user(int $id)
    {
        $this->defData['languages'] = $this->commonModel->lists('languages', 'code,name');
        $languages = array_map(function ($language) {
            return $language->code;
        }, $this->defData['languages']);
        if ($this->request->is('post')) {
            $valData = ([
                'username' => 'required|regex_match[/\A[a-zA-Z0-9\.]+\z/]|min_length[3]|max_length[30]',
                'firstname' => ['label' => lang('Backend.firstName'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'surname' => ['label' => lang('Backend.lastName'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'email' => ['label' => lang('Auth.email'), 'rules' => 'required|valid_email'],
                'group.*' => ['label' => lang('Users.authority'), 'rules' => 'required|is_natural_no_zero'],
                'own_language' => ['label' => lang('Backend.ownLanguage'), 'rules' => 'required|in_list[' . implode(',', $languages) . ']'],
            ]);

            if ($this->request->getPost('password')) $valData['password'] = ['label' => lang('Auth.password'), 'rules' => 'required|min_length[8]'];

            if ($this->validate($valData) === false) return redirect()->route('update_user', [$id])->withInput()->with('errors', $this->validator->getErrors());

            $user = auth()->getProvider();
            $u = $user->withGroups()->findById($id);
            if ($u->inGroup('superadmin')) return $this->failForbidden();
            if ((int) $id === (int) auth()->id()) return $this->failForbidden(lang('Users.cannotEditOwnAccount'));

            // By this point the target is guaranteed to be a peer account,
            // not the actor's own (self-target already rejected above). This
            // route is protected only by the delegable granular users.update
            // permission, not superadmin-only, and unlike profile() it never
            // verifies the target's current password. Reject the password
            // field outright for non-superadmin actors rather than silently
            // dropping it, so the actor gets clear feedback that no reset
            // happened instead of assuming it succeeded.
            if ($this->request->getPost('password') && !auth()->user()->inGroup('superadmin'))
                return $this->failForbidden(lang('Users.cannotResetPeerPassword'));

            $groups = $this->commonModel->lists('auth_groups', 'group', ['group!=' => 'superadmin'], 'id ASC', 0, 0, [], [], [], ['isReset' => false,], ['key' => 'id', 'where' => $this->request->getPost('group')]);
            $groupNames = array_column($groups, 'group');

            // Delegation ceiling: must run before any write below.
            if (!auth()->user()->inGroup('superadmin') && !$this->actorMayAssignGroup($groupNames))
                return $this->failForbidden(lang('Users.groupExceedsOwnGrant'));

            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => esc($this->request->getPost('firstname')),
                'surname' => esc($this->request->getPost('surname')),
                'update_at' => date('Y-m-d H:i:s'),
                'username' => esc($this->request->getPost('username')),
                'who_created' => user_id(),
            ];
            if ($this->request->getPost('password')) {
                $data['password'] = $this->request->getPost('password');
                if ($u->requiresPasswordReset()) $u->undoForcePasswordReset();
            }

            $u->fill($data);
            if ($user->save($u)) {
                $u->syncGroups(...$groupNames);
                cache()->delete("{$id}_permissions");
                return redirect()->route('users')->with('message', lang('Backend.updated', [$data['username']]));
            } else return redirect()->route('update_user', [$id])->withInput()->with('error', lang('Backend.notUpdated', [$data['username']]));
        }
        $user = auth()->getProvider();
        $this->defData['groups'] = $this->commonModel->lists('auth_groups', '*', ['group!=' => 'superadmin']);
        $this->defData['userInfo'] = $user->withGroups()->findById($id);

        return view('Modules\Users\Views\usersCrud\form', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function user_del()
    {
        if (!$this->request->isAJAX() || !auth()->user()->inGroup('superadmin')) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) === false) return $this->fail($this->validator->getErrors());
        $user = auth()->getProvider();
        $targetUser = $user->withGroups()->findById($this->request->getPost('id'));
        if ($targetUser?->inGroup('superadmin')) {
            return $this->failForbidden(lang('Users.cannotDeleteSuperadmin'));
        }
        if ($user->delete($this->request->getPost('id'), true)) {
            return  $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$user->username])]);
        }
        return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$user->username])]);
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface|string
     */
    public function profile()
    {
        $this->defData['languages'] = $this->commonModel->lists('languages', 'code,name');
        $languages = array_map(function ($language) {
            return $language->code;
        }, $this->defData['languages']);
        if ($this->request->is('post')) {
            $valData = [
                'firstname' => ['label' => lang('Backend.firstName'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'surname' => ['label' => lang('Backend.lastName'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'email' => ['label' => lang('Auth.email'), 'rules' => 'required|valid_email'],
                'profileIMG' => [
                    'label' => lang('Users.profileIMG'),
                    'rules' => 'is_image[profileIMG]|mime_in[profileIMG,image/jpg,image/jpeg,image/png,image/webp]|max_size[profileIMG,2048]|max_dims[profileIMG,150,150]'
                ],
                'own_language' => ['label' => lang('Backend.ownLanguage'), 'rules' => 'required|in_list[' . implode(',', $languages) . ']'],
            ];

            if ($this->request->getPost('password')) {
                $valData['password'] = ['label' => lang('Auth.password'), 'rules' => 'required|min_length[8]'];
                $valData['current_password'] = ['label' => lang('Users.currentPassword'), 'rules' => 'required'];
            }

            if ($this->validate($valData) === false) {
                return redirect()->route('profile')->withInput()->with('errors', $this->validator->getErrors());
            }

            $users = auth()->getProvider();

            $user = $users->findById(user_id());

            $data = [
                'email' => $this->request->getPost('email'),
                'firstname' => esc($this->request->getPost('firstname')),
                'surname' => esc($this->request->getPost('surname')),
                'own_language' => $this->request->getPost('own_language'),
            ];

            // Image Upload Handling
            $file = $this->request->getFile('profileIMG');
            if ($file->isValid() && !$file->hasMoved()) {
                $newName = $file->getRandomName();
                if ($file->move(FCPATH . 'media/avatars', $newName)) {
                    $data['profileIMG'] = '/media/avatars/' . $newName;
                    if (!empty($user->profileIMG) && file_exists(FCPATH . 'media/avatars/' . $user->profileIMG)) {
                        @unlink(FCPATH . 'media/avatars/' . $user->profileIMG);
                    }
                }
            }

            if (!empty($this->request->getPost('password'))) {
                // Defense-in-depth: require and verify the current password before changing it.
                $currentPassword = (string) $this->request->getPost('current_password');
                if (!service('passwords')->verify($currentPassword, $user->password_hash)) {
                    return redirect()->route('profile')->withInput()->with('error', lang('Users.currentPasswordWrong'));
                }
                $data['password'] = $this->request->getPost('password');
                if ($user->requiresPasswordReset()) $user->undoForcePasswordReset();
            }

            if ($user->email != $data['email']) {
                $existingUser = $users->findByCredentials(['email' => $data['email']]);
                if ($existingUser && $existingUser->id != $user->id) {
                    return redirect()->route('profile')->withInput()->with('error', lang('Users.alreadyTakenEmail'));
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

            if ((bool)$result === false) return redirect()->route('profile')->withInput()->with('error', lang('Backend.notUpdated', [esc($user->firstname . ' ' . $user->surname)]));
            else return redirect()->route('profile')->with('message', lang('Backend.updated', [esc($user->firstname . ' ' . $user->surname)]));
        }
        $this->defData['user'] = auth()->getProvider()->findById(user_id());
        $currentSessId = session()->get('ci4ms_session_tracker_id') ?? '';
        $this->defData['activeSessions']  = $this->sessionModel->getActiveSessions(auth()->id(), $currentSessId);
        $this->defData['allSessions']     = $this->sessionModel->getUserSessions(auth()->id(), $currentSessId);
        $this->defData['activeCount']     = $this->sessionModel->getActiveCount(auth()->id());
        return view('Modules\Users\Views\usersCrud\profile', $this->defData);
    }


    /**
     * AJAX Request: Returns the user's device (session) history in JSON format.
     * Can be used to dynamically update the session list without reloading the page.
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function sessionsJson(): \CodeIgniter\HTTP\Response
    {
        $userId = (int) auth()->id();
        $sessions = $this->sessionModel->getUserSessions($userId, session()->get('ci4ms_session_tracker_id') ?? '');

        return $this->respond([
            'status'   => 'ok',
            'sessions' => $sessions,
        ]);
    }

    /**
     * Terminates a specifically identified single device session.
     * If the terminated session is the user's current session, they will be logged out of the system.
     *
     * @param string $sessionId Device identifier (Tracker ID) to be terminated
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function terminateSession(string $sessionId)
    {
        $userId    = (int) auth()->id();
        $isCurrent = ($sessionId === session()->get('ci4ms_session_tracker_id'));
        $success = $this->sessionModel->terminateSession($userId, $sessionId, $isCurrent);
        if (! $success) {
            return redirect()->back()->with('error', lang('Users.sessionTerminateError'));
        }
        if ($isCurrent) {
            session()->destroy();
            return redirect()->route('login')->with('message', lang('Users.currentSessionTerminated'));
        }
        return redirect()->back()->with('success', lang('Users.sessionTerminated'));
    }

    /**
     * Simultaneously terminates all active remote sessions (phone, other computers, etc.)
     * except for the device the user is currently connected from.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function terminateOtherSessions()
    {
        $userId        = (int) auth()->id();
        $currentSessId = session()->get('ci4ms_session_tracker_id') ?? '';
        $count = $this->sessionModel->terminateAllExcept($userId, $currentSessId);
        return redirect()->back()->with('success', lang('Users.sessionTerminatedCount', [$count]));
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function ajax_blackList_post(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['note' => ['label' => lang('Backend.notes'), 'rules' => 'required'], 'uid' => ['label' => 'uid', 'rules' => 'required|is_natural_no_zero']]);
        if ($this->validate($valData) === false) return $this->fail($this->validator->getErrors());

        $user = auth()->getProvider()->findById($this->request->getPost('uid'));
        if ($user->inGroup('superadmin')) return $this->failForbidden();
        $user->ban($this->request->getPost('note'));
        $this->commonModel->edit('auth_identities', ['who_banned' => user_id()], ['user_id' => $this->request->getPost('uid')],);
        $result = [];

        if ($user->isBanned()) {
            $result = ['result' => true, 'error' => ['type' => 'success', 'message' => lang('Users.addedToBlacklist')]];
            $user->forcePasswordReset();
        } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => lang('Users.couldNotAddToBlacklist')]];

        return $this->respond($result, 200);
    }

    public function ajax_remove_from_blackList_post(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['uid' => ['label' => lang('Backend.id'), 'rules' => 'required|is_natural_no_zero']]);

        if ($this->validate($valData) === false) return $this->fail($this->validator->getErrors());
        $user = auth()->getProvider()->findById($this->request->getPost('uid'));
        if ($user->inGroup('superadmin')) return $this->failForbidden();

        $user->unBan();
        $this->commonModel->edit('auth_identities', ['who_banned' => null], ['user_id' => $this->request->getPost('uid')],);
        $result = [];
        if (!$user->isBanned()) {
            $result = ['result' => true, 'error' => ['type' => 'success', 'message' => lang('Users.removedFromBlacklist', [$user->email])]];
            $user->undoForcePasswordReset();
        } else $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => lang('Users.couldNotRemoveFromBlacklist')]];

        return $this->response->setJSON($result);
    }

    public function ajax_force_reset_password(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = (['uid' => ['label' => lang('Backend.id'), 'rules' => 'required|is_natural_no_zero']]);

        if ($this->validate($valData) === false) return $this->fail($this->validator->getErrors());

        $user = auth()->getProvider()->findById($this->request->getPost('uid'));
        if ($user->inGroup('superadmin')) return $this->failForbidden();
        $result = [];
        if ($user->requiresPasswordReset()) {
            $result = ['result' => true, 'error' => ['type' => 'warning', 'message' => lang('Users.passwordResetStep', [$user->email])]];
        } else {
            $user->forcePasswordReset();
            if ($user->requiresPasswordReset())
                $result = ['result' => true, 'error' => ['type' => 'success', 'message' => lang('Users.passwordResetSuccess', [$user->email])]];
            else
                $result = ['result' => false, 'error' => ['type' => 'danger', 'message' => lang('Users.passwordResetError', [$user->email])]];
        }

        return $this->response->setJSON($result);
    }
}
