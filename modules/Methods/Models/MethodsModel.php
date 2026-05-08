<?php

namespace Modules\Methods\Models;

use CodeIgniter\Model;

class MethodsModel extends Model
{
    public function getModules()
    {
        $modules = $this->db->table('modules')->select('modules.id AS module_id, modules.name AS module_name, modules.create_time AS module_created, modules.icon AS module_icon,
        modules.isActive AS module_active,
         GROUP_CONCAT(
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.id, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.pagename, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.description, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.className, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.methodName, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.sefLink, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.hasChild, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.pageSort, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.inNavigation, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.isBackoffice, \'\'), \'|||\',
            COALESCE(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.isActive, \'\'), \'|||\',
            COALESCE(
            NULLIF(
                TRIM(BOTH \'|\' FROM CONCAT_WS(\'|\',
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.typeOfPermissions, \'$.create_r\')) = \'true\', \'create_r\', NULL),
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.typeOfPermissions, \'$.read_r\'))   = \'true\', \'read_r\',   NULL),
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.typeOfPermissions, \'$.update_r\')) = \'true\', \'update_r\', NULL),
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . getenv('database.default.DBPrefix') . 'auth_permissions_pages.typeOfPermissions, \'$.delete_r\')) = \'true\', \'delete_r\', NULL)
                )),
            \'\'),
            \'\'
            )
        ) AS pages_data')->join('auth_permissions_pages', 'modules.id = auth_permissions_pages.module_id', 'left')->orderBy('modules.id', 'ASC')->groupBy('modules.id')->get()->getResult();
        $modulesList = [];
        foreach ($modules as $module) {
            $pages = [];
            if (!empty($module->pages_data)) {
                $pageItems = explode(',', $module->pages_data);
                foreach ($pageItems as $pageStr) {
                    $pageData = explode("|||", $pageStr);
                    $pages[] = (object)[
                        'id' => $pageData[0],
                        'pagename' => $pageData[1],
                        'description' => $pageData[2],
                        'className' => $pageData[3],
                        'methodName' => $pageData[4],
                        'sefLink' => $pageData[5],
                        'hasChild' => $pageData[6],
                        'pageSort' => $pageData[7],
                        'inNavigation' => $pageData[8],
                        'isBackoffice' => $pageData[9],
                        'isActive' => $pageData[10],
                        'typeOfPermissions' => $pageData[11]
                    ];
                }
            }

            $modulesList[] = (object)[
                'id' => $module->module_id,
                'name' => $module->module_name,
                'created' => $module->module_created,
                'active' => $module->module_active,
                'icon' => $module->module_icon,
                'pages' => $pages
            ];
        }
        return $modulesList;
    }

    /**
     * Sadece isActive=1 olan modülleri ve sayfaları döndürür.
     * Filtreleme SQL katmanında yapılır — view'da array_filter / foreach yük oluşturmaz.
     */
    public function getActiveModules(): array
    {
        $prefix  = getenv('database.default.DBPrefix');

        $modules = $this->db->table('modules')
            ->select('modules.id AS module_id, modules.name AS module_name, modules.create_time AS module_created, modules.icon AS module_icon,
        modules.isActive AS module_active,
         GROUP_CONCAT(
            COALESCE(' . $prefix . 'auth_permissions_pages.id, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.pagename, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.description, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.className, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.methodName, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.sefLink, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.hasChild, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.pageSort, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.inNavigation, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.isBackoffice, \'\'), \'|||\',
            COALESCE(' . $prefix . 'auth_permissions_pages.isActive, \'\'), \'|||\',
            COALESCE(
            NULLIF(
                TRIM(BOTH \'|\' FROM CONCAT_WS(\'|\',
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . $prefix . 'auth_permissions_pages.typeOfPermissions, \'$.create_r\')) = \'true\', \'create_r\', NULL),
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . $prefix . 'auth_permissions_pages.typeOfPermissions, \'$.read_r\'))   = \'true\', \'read_r\',   NULL),
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . $prefix . 'auth_permissions_pages.typeOfPermissions, \'$.update_r\')) = \'true\', \'update_r\', NULL),
                IF(JSON_UNQUOTE(JSON_EXTRACT(' . $prefix . 'auth_permissions_pages.typeOfPermissions, \'$.delete_r\')) = \'true\', \'delete_r\', NULL)
                )),
            \'\'),
            \'\'
            )
        ) AS pages_data')
            // Sadece aktif modüller
            ->where('modules.isActive', 1)
            // JOIN koşuluna isActive filtresi: pasif sayfalar GROUP_CONCAT'e girmez
            ->join(
                'auth_permissions_pages',
                'modules.id = ' . $prefix . 'auth_permissions_pages.module_id AND ' . $prefix . 'auth_permissions_pages.isActive = 1',
                'left'
            )
            ->orderBy('modules.id', 'ASC')
            ->groupBy('modules.id')
            ->get()
            ->getResult();

        $modulesList = [];
        foreach ($modules as $module) {
            $pages = [];
            if (!empty($module->pages_data)) {
                $pageItems = explode(',', $module->pages_data);
                foreach ($pageItems as $pageStr) {
                    $pageData = explode('|||', $pageStr);
                    // LEFT JOIN NULL satırı → ID boş gelir, atla
                    if (empty(trim($pageData[0]))) {
                        continue;
                    }
                    $pages[] = (object) [
                        'id'                => $pageData[0],
                        'pagename'          => $pageData[1],
                        'description'       => $pageData[2],
                        'className'         => $pageData[3],
                        'methodName'        => $pageData[4],
                        'sefLink'           => $pageData[5],
                        'hasChild'          => $pageData[6],
                        'pageSort'          => $pageData[7],
                        'inNavigation'      => $pageData[8],
                        'isBackoffice'      => $pageData[9],
                        'isActive'          => $pageData[10],
                        'typeOfPermissions' => $pageData[11],
                    ];
                }
            }

            // Aktif sayfası olmayan modülü döndürme
            if (empty($pages)) {
                continue;
            }

            $modulesList[] = (object) [
                'id'      => $module->module_id,
                'name'    => $module->module_name,
                'created' => $module->module_created,
                'active'  => $module->module_active,
                'icon'    => $module->module_icon,
                'pages'   => $pages,
            ];
        }

        return $modulesList;
    }
}
