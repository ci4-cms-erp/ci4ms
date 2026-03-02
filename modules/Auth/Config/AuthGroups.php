<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Modules\Auth\Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'Complete control of the site.',
        ],
        /* 'admin' => [
            'title'       => 'Admin',
            'description' => 'Day to day administrators of the site.',
        ],
        'developer' => [
            'title'       => 'Developer',
            'description' => 'Site programmers.',
        ], */
        'user' => [
            'title'       => 'User',
            'description' => 'General users of the site. Often customers.',
        ],
        /* 'beta' => [
            'title'       => 'Beta User',
            'description' => 'Has access to beta-level features.',
        ], */
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [];

    public function __construct()
    {
        parent::__construct();

        $cacheKey = 'shield_auth_dynamic_config';
        $cachedData = cache($cacheKey);
        if ($cachedData !== null) {
            // Eğer cache varsa, verileri oradan al
            $this->groups      = $cachedData['groups'];
            $this->permissions = $cachedData['permissions'];
            $this->matrix      = $cachedData['matrix'];
        } else {
            // Cache yoksa veritabanından çek
            $this->loadFromDatabase();

            // Verileri 24 saatliğine (86400 sn) cache'le
            cache()->save($cacheKey, [
                'groups'      => $this->groups,
                'permissions' => $this->permissions,
                'matrix'      => $this->matrix
            ], 86400);
        }
    }

    public function loadFromDatabase()
    {
        $commonModel = new \ci4commonmodel\Models\CommonModel();

        // 1. Grupları Yükle
        $groups = $commonModel->lists('auth_groups');
        foreach ($groups as $group) {
            $this->groups[$group->group] = [
                'title'       => $group->group,
                'description' => $group->description
            ];
        }

        // 2. İzin Tanımlarını Yükle (Sayfa isimleri izin anahtarı olacak)
        // Örn: 'users.read', 'users.create' gibi dinamik oluşturacağız
        $pages = $commonModel->lists('auth_permissions_pages');
        $pageMap = []; // ID => Pagename eşlemesi için

        foreach ($pages as $page) {
            $pagename = strtolower($page->pagename);
            $pageMap[$page->id] = $pagename;

            // Olası tüm aksiyonları tanımla
            $this->permissions[$pagename . '.create'] = $page->description . ' - Create';
            $this->permissions[$pagename . '.read']   = $page->description . ' - Read';
            $this->permissions[$pagename . '.update'] = $page->description . ' - Update';
            $this->permissions[$pagename . '.delete'] = $page->description . ' - Delete';
        }

        // 3. Matrix'i (Grup - İzin Eşleşmesini) Yükle
        // auth_groups tablosundaki 'permissions' JSON kolonunu okuyacağız
        foreach ($groups as $group) {
            $groupName = $group->group;
            $this->matrix[$groupName] = []; // Başlangıçta boş izin listesi

            if (!empty($group->permissions)) {
                $permsArray = json_decode($group->permissions, true);

                if (is_array($permsArray)) {
                    foreach ($permsArray as $permObj) {
                        // Page ID'den sayfa ismini bul
                        if (isset($pageMap[$permObj['page_id']])) {
                            $pagename = $pageMap[$permObj['page_id']];

                            // Hangi yetkiler true ise matrix'e ekle
                            if (!empty($permObj['create_r'])) $this->matrix[$groupName][] = $pagename . '.create';
                            if (!empty($permObj['read_r']))   $this->matrix[$groupName][] = $pagename . '.read';
                            if (!empty($permObj['update_r'])) $this->matrix[$groupName][] = $pagename . '.update';
                            if (!empty($permObj['delete_r'])) $this->matrix[$groupName][] = $pagename . '.delete';
                        }
                    }
                }
            }
        }
    }
}
