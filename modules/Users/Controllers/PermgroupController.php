<?php

namespace Modules\Users\Controllers;

use CodeIgniter\I18n\Time;
use Exception;

class PermgroupController extends \Modules\Backend\Controllers\BaseController
{
    public function groupList($num = 1)
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $like = [];
            $postData = ['group!=' => 'superadmin'];
            if (!empty($like))
                $like = ['title' => $like];
            $results = $this->commonModel->lists('auth_groups', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int) $data['length'], ($data['length'] == '-1') ? 0 : (int) $data['start'], $like);
            $totalRecords = $this->commonModel->count('auth_groups', $postData, $like);
            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('group_update', $result->id) . '" class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Users\Views\permGroup\list', $this->defData);
    }

    /**
     * Creates a new permission group from POST data, or renders the create form on GET.
     *
     * `is_unique[auth_groups.group]` in $valData only validates the raw POST
     * value; the persisted value is esc(seflink($groupName)), so a raw value
     * that does not collide can still collide once transformed. Both the
     * "superadmin" name and post-transform uniqueness are re-checked here
     * against the transformed value before the row is written.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|string Redirect on POST, rendered view on GET
     */
    public function group_create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]|is_unique[auth_groups.group]'],
                'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required|regex_match[/^[^<>{}=]*$/u]'],
                'seflink' => ['label' => 'Seflink', 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'perms' => ['label' => 'İzinler', 'rules' => 'required']
            ]);

            if ($this->validate($valData) === false)
                return redirect()->route('group_create')->withInput()->with('errors', $this->validator->getErrors());

            $newGroup = esc(seflink($this->request->getPost('groupName')));

            if ($newGroup === 'superadmin')
                return redirect()->route('group_create')->withInput()->with('errors', lang('Users.superadminGroupProtected'));

            if ($this->commonModel->count('auth_groups', ['group' => $newGroup]) > 0)
                return redirect()->route('group_create')->withInput()->with('errors', lang('Users.groupNameConflict'));

            $data = [
                'group' => $newGroup,
                'description' => esc($this->request->getPost('description')),
                'redirect' => esc($this->request->getPost('seflink')),
                'who_created' => user_id()
            ];

            $pageMap = $this->getPageNameMap();

            if (!auth()->user()->inGroup('superadmin') && !$this->actorGrantsSubsetOfOwnPermissions($pageMap, $this->request->getPost('perms')))
                return redirect()->route('group_create')->withInput()->with('errors', lang('Users.permsExceedOwnGrant'));

            $permissions = [];
            foreach ($this->request->getPost('perms') as $key => $perm) {
                if (!isset($pageMap[$key]))
                    continue;

                $roles = explode('|', $perm['roles']);
                $permissions[] = [
                    'page_id' => $key,
                    'create_r' => in_array('create_r', $roles),
                    'update_r' => in_array('update_r', $roles),
                    'read_r' => in_array('read_r', $roles),
                    'delete_r' => in_array('delete_r', $roles),
                    'who_perm' => user_id(),
                    'created_at' => new Time('now')
                ];
            }
            $data['permissions'] = json_encode($permissions, JSON_UNESCAPED_UNICODE);
            $result = $this->commonModel->create('auth_groups', $data);
            if (empty($result))
                return redirect()->route('group_create')->withInput()->with('errors', lang('Backend.notCreated', [$this->request->getPost('groupName')]));
            else
                return redirect()->to(route_to('groupList'))->with('message', lang('Backend.created', [$this->request->getPost('groupName')]));
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getActiveModules();
        return view('Modules\Users\Views\permGroup\create', $this->defData);

    }

    /**
     * Updates an existing permission group from POST data, or renders the update form on GET.
     *
     * Guards (in order) close BLOKER-1 (privilege escalation via group
     * rename): the target group must exist (G1), neither the target group
     * nor the transformed new name may be/become "superadmin" (G2/G3), a
     * genuine rename may not collide with another group's transformed name
     * (G4), and an actor may not rename a group they currently belong to
     * (G5). G4/G5 are gated on the name actually changing so that editing a
     * group's description/permissions without renaming it never trips them.
     *
     * @param int $id auth_groups.id of the group being updated
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|string Redirect on POST, rendered view on GET
     */
    public function group_update($id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required'],
                'seflink' => ['label' => 'Seflink', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'perms' => ['label' => 'İzinler', 'rules' => 'required']
            ]);

            if ($this->validate($valData) === false)
                return redirect()->route('group_update', [$id])->withInput()->with('errors', $this->validator->getErrors());

            $group = $this->commonModel->selectOne('auth_groups', ['id' => $id]);
            if ($group === null)
                return $this->showError(404);

            if ($group->group === 'superadmin')
                return $this->failForbidden(lang('Users.superadminGroupProtected'));

            $oldGroupName = $group->group;
            $newGroup = esc(seflink($this->request->getPost('groupName')));

            if ($newGroup === 'superadmin')
                return $this->failForbidden(lang('Users.superadminGroupProtected'));

            if ($newGroup !== $oldGroupName && $this->commonModel->count('auth_groups', ['group' => $newGroup]) > 0)
                return $this->failForbidden(lang('Users.groupNameConflict'));

            if ($newGroup !== $oldGroupName && auth()->user()->inGroup($oldGroupName))
                return $this->failForbidden(lang('Users.cannotEditOwnGroupName'));

            $pageMap = $this->getPageNameMap();

            if (!auth()->user()->inGroup('superadmin') && !$this->actorGrantsSubsetOfOwnPermissions($pageMap, $this->request->getPost('perms')))
                return $this->failForbidden(lang('Users.permsExceedOwnGrant'));

            $permissions = [];
            foreach ($this->request->getPost('perms') as $key => $perm) {
                if (!isset($pageMap[$key]))
                    continue;

                $roles = explode('|', $perm['roles']);
                $permissions[] = [
                    'page_id' => $key,
                    'create_r' => in_array('create_r', $roles),
                    'update_r' => in_array('update_r', $roles),
                    'read_r' => in_array('read_r', $roles),
                    'delete_r' => in_array('delete_r', $roles),
                    'who_perm' => user_id(),
                    'created_at' => new Time('now')
                ];
            }

            $data = [
                'group' => $newGroup,
                'description' => esc($this->request->getPost('description')),
                'redirect' => esc($this->request->getPost('seflink')),
                'who_created' => user_id(),
                'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE)
            ];

            // BLOKER-B: the perms[] subset guard above must run before any
            // write -- it used to run after the auth_groups_users rename
            // below, so a request it went on to reject had already relinked
            // real members of this group to a name auth_groups never got
            // (see context.md BLOKER-B). The rename and the auth_groups
            // update are wrapped in one transaction so a mid-way failure
            // can't leave members pointed at a group row that was never
            // written either.
            $this->commonModel->db->transStart();

            if ($newGroup !== $oldGroupName) {
                $this->commonModel->edit('auth_groups_users', ['group' => $newGroup], ['group' => $oldGroupName]);
            }
            $editResult = $this->commonModel->edit('auth_groups', $data, ['id' => $id]);

            $this->commonModel->db->transComplete();

            if ($editResult && $this->commonModel->db->transStatus()) {
                cache()->delete("shield_auth_dynamic_config");
                cache()->deleteMatching('backend_page_info_*');
                return redirect()->route('groupList')->with('message', lang('Backend.updated', [$this->request->getPost('groupName')]));
            } else
                return redirect()->route('group_update', [$id])->withInput()->with('error', lang('Backend.notUpdated', [$this->request->getPost('groupName')]));
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getActiveModules();
        $this->defData['group_name'] = $this->commonModel->selectOne('auth_groups', ['id' => $id]);
        $this->defData['perms'] = json_decode($this->defData['group_name']->permissions ?? '', true) ?? [];
        return view('Modules\Users\Views\permGroup\update', $this->defData);

    }

    /**
     * Builds an auth_permissions_pages.id => lowercase pagename map.
     *
     * Single source of truth for "which page_ids currently exist", shared by
     * actorGrantsSubsetOfOwnPermissions() and the perms[] write loops in
     * group_create()/group_update(). Before this method existed, the write
     * loops persisted every posted page_id unconditionally regardless of
     * whether actorGrantsSubsetOfOwnPermissions() recognized it, so an
     * unknown (not-yet-existing) page_id sailed through the subset guard
     * (nothing to check against) and was written to auth_groups.permissions
     * anyway -- see context.md F-1. Callers must call this once per request
     * and pass the result to both the guard and the write loop; it must not
     * be queried again inside a loop (N+1).
     *
     * @return array<int, string> auth_permissions_pages.id => lowercase pagename
     */
    private function getPageNameMap(): array
    {
        $pageMap = [];
        foreach ($this->commonModel->lists('auth_permissions_pages') as $page) {
            $pageMap[$page->id] = strtolower($page->pagename);
        }

        return $pageMap;
    }

    /**
     * Checks that every page-permission the actor posted in perms[] is a
     * subset of the actor's own effective permission set.
     *
     * Closes the HIGH-severity gap left after BLOKER-1 (superadmin rename
     * guard, see FAZ2-K2 in context.md): without this, an actor holding only
     * users.group_create.create / users.group_update.update could grant any
     * page permission -- including ones they do not themselves hold -- to
     * any group. Callers must skip this check for superadmin (superadmin is
     * exempt from the subset rule and may grant anything); this method does
     * not check inGroup('superadmin') itself. Uses auth()->user()->can(),
     * not getPermissions(), because most actors' access comes from their
     * group's permission matrix rather than a direct per-user grant --
     * getPermissions() alone would reject legitimate subset submissions.
     * Unknown page_id keys (not present in $pageMap) are skipped silently,
     * mirroring user_perms():~256-257 -- the perms[] write loops in
     * group_create()/group_update() apply the identical skip against the
     * same $pageMap so an unknown page_id can never be persisted either
     * (see context.md F-1; getPageNameMap()'s docblock).
     *
     * @param array<int, string>                      $pageMap     auth_permissions_pages.id => lowercase pagename, from getPageNameMap()
     * @param array<int|string, array{roles: string}> $postedPerms Raw perms[] POST payload (page_id => ['roles' => 'role_r|role_r'])
     *
     * @return bool True if every requested action maps to a permission string the actor already has
     */
    private function actorGrantsSubsetOfOwnPermissions(array $pageMap, array $postedPerms): bool
    {
        $roleActionMap = ['create_r' => 'create', 'read_r' => 'read', 'update_r' => 'update', 'delete_r' => 'delete'];

        foreach ($postedPerms as $key => $perm) {
            if (!isset($pageMap[$key]))
                continue;

            $roles = explode('|', $perm['roles']);
            foreach ($roleActionMap as $roleKey => $action) {
                if (in_array($roleKey, $roles, true) && !auth()->user()->can($pageMap[$key] . '.' . $action))
                    return false;
            }
        }

        return true;
    }

    /**
     * Updates a single user's direct (non-group) permissions from POST data,
     * or renders the special-permission form on GET.
     *
     * Closes the user-level counterpart of BLOKER-1/FAZ2-K2 (see
     * actorGrantsSubsetOfOwnPermissions()'s docblock): the only guard used to
     * be on the *target* being superadmin, so any actor reaching this method
     * could self-target to grant themselves permissions they did not
     * otherwise hold, or grant a peer more than the actor's own effective
     * permission set. The self-target and subset guards below must run
     * before any write, including the empty-perms path that wipes all of the
     * target's direct permissions via syncPermissions() with no arguments.
     *
     * @param int $id users.id of the account whose direct permissions are being edited
     *
     * @return \CodeIgniter\HTTP\ResponseInterface|string Redirect on POST, rendered view on GET
     */
    public function user_perms(int $id)
    {
        if ($this->request->is('post')) {
            // An actor may not edit their own direct permissions -- doing so
            // from this screen would let them self-escalate outside of the
            // subset guard below (which compares against the actor's *own*
            // current permissions, not a snapshot from before the write).
            if ($id === (int) auth()->id())
                return $this->failForbidden(lang('Users.cannotEditOwnPermissions'));

            $user = auth()->getProvider()->findById($id);
            if ($user->inGroup('superadmin'))
                return $this->failForbidden();

            try {
                $pageMap = $this->getPageNameMap();
                $postedPerms = $this->request->getPost('perms') ?? [];

                // Delegation ceiling: a non-superadmin actor may only grant
                // permissions that are a subset of their own effective
                // permissions. Must run before any write, including the
                // empty-perms wipe path below.
                if (!auth()->user()->inGroup('superadmin') && !$this->actorGrantsSubsetOfOwnPermissions($pageMap, $postedPerms))
                    return $this->failForbidden(lang('Users.permsExceedOwnGrant'));

                if (empty($postedPerms)) {
                    $user->syncPermissions();
                    cache()->delete("{$id}_permissions");
                    return redirect()->route('users')->with('message', lang('Backend.updated', [$user->username]));
                }

                $perms = [];
                foreach ($postedPerms as $key => $perm) {
                    if (!isset($pageMap[$key]))
                        continue;

                    $roles = explode('|', $perm['roles']);
                    $pagename = $pageMap[$key];

                    if (in_array('create_r', $roles)) $perms[] = $pagename . '.create';
                    if (in_array('read_r', $roles)) $perms[] = $pagename . '.read';
                    if (in_array('update_r', $roles)) $perms[] = $pagename . '.update';
                    if (in_array('delete_r', $roles)) $perms[] = $pagename . '.delete';
                }
                $user->syncPermissions(...$perms);
                cache()->delete("{$id}_permissions");

                return redirect()->route('users')->with('message', lang('Backend.updated', [$user->username]));
            } catch (\Exception $e) {
                return redirect()->route('user_perms', [$id])->withInput()->with('error', lang('Backend.notUpdated', [$e->getMessage()]));
            }
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $user = auth()->getProvider()->findById($id);

        $allModules = $methodsModel->getActiveModules();
        $groupRecord = $this->commonModel->selectOne('auth_groups', ['group' => $user->getGroups()[0] ?? '']);
        $groupPerms = $groupRecord ? (json_decode($groupRecord->permissions, true) ?? []) : [];
        $groupPermPageIds = array_column($groupPerms, 'page_id');
        $userPerms = $user->getPermissions();

        $filteredModules = [];
        foreach ($allModules as $module) {
            $filteredPages = [];
            foreach ($module->pages as $page) {
                if (in_array($page->id, $groupPermPageIds)) {
                    continue;
                }
                $filteredPages[] = $page;
            }

            if (!empty($filteredPages)) {
                $module->pages = $filteredPages;
                $filteredModules[] = $module;
            }
        }

        $this->defData['modules'] = $filteredModules;
        $this->defData['userInfos'] = $user;
        $this->defData['perms'] = $userPerms;
        $this->defData['groupPerms'] = $groupPerms;
        return view('Modules\Users\Views\permGroup\userPerms', $this->defData);
    }
}
