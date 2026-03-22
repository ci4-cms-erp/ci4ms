<?php

namespace Modules\Install\Services;

use CodeIgniter\Shield\Entities\User;

class InstallService
{
    public function createDefaultData(array $args)
    {
        $commonModel = new \ci4commonmodel\CommonModel();
        $commonModel->create('auth_groups', [
            "group" => "superadmin",
            "description" => "superadmin",
            "seflink" => "backend",
            "who_created" => null
        ]);

        $users = auth()->getProvider();
        $user = new User([
            'firstname' => $args['fname'],
            'surname'   => $args['sname'],
            'username'  => $args['username'],
            'email'     => $args['email'],
            'password'  => $args['password'],
            'active'    => 1
        ]);

        if ($users->save($user)) {
            $userId = $users->getInsertID();
            $commonModel->create('auth_groups_users', [
                'user_id'    => $userId,
                'group'      => 'superadmin',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        $commonModel->createMany('modules', array(
            array('id' => '1', 'name' => 'Backend', 'isActive' => '1', 'icon' => 'fas fa-server'),
            array('id' => '2', 'name' => 'Blog', 'isActive' => '1', 'icon' => 'fas fa-blog'),
            array('id' => '3', 'name' => 'Fileeditor', 'isActive' => '1', 'icon' => 'fas fa-file-code'),
            array('id' => '4', 'name' => 'Media', 'isActive' => '1', 'icon' => 'fas fa-images'),
            array('id' => '5', 'name' => 'Menu', 'isActive' => '1', 'icon' => 'fas fa-bars'),
            array('id' => '6', 'name' => 'Methods', 'isActive' => '1', 'icon' => 'fas fa-cube'),
            array('id' => '7', 'name' => 'ModulesInstaller', 'isActive' => '1', 'icon' => 'fas fa-upload'),
            array('id' => '8', 'name' => 'Pages', 'isActive' => '1', 'icon' => 'fas fa-file-alt'),
            array('id' => '9', 'name' => 'Settings', 'isActive' => '1', 'icon' => 'fas fa-cog'),
            array('id' => '10', 'name' => 'Theme', 'isActive' => '1', 'icon' => 'fas fa-palette'),
            array('id' => '11', 'name' => 'Users', 'isActive' => '1', 'icon' => 'fas fa-users'),
            array('id' => '12', 'name' => 'Logs', 'isActive' => '1', 'icon' => 'fas fa-file-alt')
        ));

        $commonModel->createMany(
            'auth_permissions_pages',
            array(
                array('id' => '1', 'pagename' => 'Backend.backend', 'description' => 'Yönetim Paneli Anasayfası', 'className' => '-Modules-Backend-Controllers-Backend', 'methodName' => 'index', 'sefLink' => 'backend', 'hasChild' => '0', 'pageSort' => '1', 'parent_pk' => NULL, 'symbol' => 'fas fa-home', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '2', 'pagename' => 'Users.usersCrud', 'description' => 'Kullanıcı İşlemleri', 'className' => '', 'methodName' => '', 'sefLink' => '#', 'hasChild' => '1', 'pageSort' => '10', 'parent_pk' => NULL, 'symbol' => 'fas fa-users', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '3', 'pagename' => 'Users.userList', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'users', 'sefLink' => 'users/1', 'hasChild' => '0', 'pageSort' => '1', 'parent_pk' => '2', 'symbol' => 'fas fa-user-friends', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true, "update_r": true, "delete_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '4', 'pagename' => 'Users.addUser', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'create_user', 'sefLink' => 'create_user', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '5', 'pagename' => 'Settings.elfinderConvertWebp', 'description' => '', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'elfinderConvertWebp', 'sefLink' => 'elfinderConvertWebp', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":false,"update_r":true,"read_r":false,"delete_r":false}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '6', 'pagename' => 'Users.permGroupList', 'description' => '', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'groupList', 'sefLink' => 'groupList/1', 'hasChild' => '0', 'pageSort' => '3', 'parent_pk' => '2', 'symbol' => 'fas fa-sitemap', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true, "update_r": true, "delete_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '7', 'pagename' => 'Users.addGroupPerms', 'description' => '', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'group_create', 'sefLink' => 'group_create', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '8', 'pagename' => 'Methods.methodList', 'description' => 'methodList', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'index', 'sefLink' => 'list', 'hasChild' => '0', 'pageSort' => '8', 'parent_pk' => NULL, 'symbol' => 'fas fa-memory', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '9', 'pagename' => 'Users.profile', 'description' => 'kullanıcnın kendi profili', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'profile', 'sefLink' => 'profile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '10', 'pagename' => 'Users.updateGroupPerms', 'description' => 'Kullanıcı Grup Yetkisi Güncelleme', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'group_update', 'sefLink' => 'group_update', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '11', 'pagename' => 'Users.specialAuthUser', 'description' => 'Kullanıcıya özel yetki verme', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'user_perms', 'sefLink' => 'user_perms', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '12', 'pagename' => 'Users.updateUser', 'description' => 'Kullanıcının güncellendiği form sayfası', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'update_user', 'sefLink' => 'update_user', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '13', 'pagename' => 'Karalisteye Alma AJAX', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'ajax_blackList_post', 'sefLink' => 'blackList', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '14', 'pagename' => 'Karalisteden Çıkarma AJAX', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'ajax_remove_from_blackList_post', 'sefLink' => 'removeFromBlacklist', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '15', 'pagename' => 'Yetkili tarafından kullanıcın şifresi sıfırlanma AJAX', 'description' => 'Yetkili tarafından kullanıcının şifresini sıfırlama yapıldı', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'ajax_force_reset_password', 'sefLink' => 'forceResetPassword', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '16', 'pagename' => 'Settings.settings', 'description' => 'Şirket bilgileri ve site içi mail ayarlarının tutulduğu alan', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'index', 'sefLink' => 'settings', 'hasChild' => '0', 'pageSort' => '100', 'parent_pk' => NULL, 'symbol' => 'fas fa-cogs', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '17', 'pagename' => 'Şirket Bilgilerini Güncelle', 'description' => 'Şirket Bilgilerinin güncellendiği adım', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'compInfosPost', 'sefLink' => 'compInfosPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '18', 'pagename' => 'Şirket Bilgilerini Güncelle', 'description' => 'Şirket sosyal medyasının güncellendiği adım', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'socialMediaPost', 'sefLink' => 'socialMediaPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '19', 'pagename' => 'Şirket Bilgilerini Güncelle', 'description' => 'Şirket mail bilgilerinin güncellendiği adım', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'mailSettingsPost', 'sefLink' => 'mailSettingsPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '20', 'pagename' => 'Users.user_del', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'user_del', 'sefLink' => 'user_del', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '21', 'pagename' => 'Pages.pages', 'description' => 'Site Sayflarının Ayarları', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'index', 'sefLink' => 'pages/1', 'hasChild' => '0', 'pageSort' => '2', 'parent_pk' => NULL, 'symbol' => 'far fa-copy', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true, "update_r": true, "delete_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '22', 'pagename' => 'Pages.pageAdd', 'description' => 'sayfa ekleme view', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'create', 'sefLink' => 'pageCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '23', 'pagename' => 'Pages.pageUpdate', 'description' => 'sayfa güncelleme view', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'update', 'sefLink' => 'pageUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '24', 'pagename' => 'sayfa silme', 'description' => 'sayfa silme', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'delete_post', 'sefLink' => 'pageDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '25', 'pagename' => 'limitli etiket listesi ajax', 'description' => 'sayfa blog kısımlarında keywordleri ortak kullanabilmek için oluşturulmuş link', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'limitTags_ajax', 'sefLink' => 'tagify', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '26', 'pagename' => 'seflink kontrol ajax', 'description' => 'sayfa blog kısımlarında seflink oluşturmak için kullanılır', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'autoLookSeflinks', 'sefLink' => 'checkSeflink', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '28', 'pagename' => 'aktifmi kontrolü ajax', 'description' => 'aktifmi kontrolü ajax', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'isActive', 'sefLink' => 'isActive', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '29', 'pagename' => 'Media.media', 'description' => 'Media', 'className' => '-Modules-Media-Controllers-Media', 'methodName' => 'index', 'sefLink' => 'media', 'hasChild' => '0', 'pageSort' => '6', 'parent_pk' => NULL, 'symbol' => 'fas fa-photo-video', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '4', 'isActive' => '1'),
                array('id' => '30', 'pagename' => 'Menu.menu', 'description' => 'menu', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'index', 'sefLink' => 'menu', 'hasChild' => '0', 'pageSort' => '4', 'parent_pk' => NULL, 'symbol' => 'fas fa-bars', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '31', 'pagename' => 'menuye link ekleme', 'description' => 'menuye link ekleme', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'create', 'sefLink' => 'createMenu', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '32', 'pagename' => 'menü listesi ajax', 'description' => 'menü listesi ajax', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'listURLs', 'sefLink' => 'menuList', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '33', 'pagename' => 'menüden link silme', 'description' => 'menüden link silme', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'delete_ajax', 'sefLink' => 'deleteMenuAjax', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '34', 'pagename' => 'menüyü sıralama', 'description' => 'menüyü sıralama', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'queue_ajax', 'sefLink' => 'queueMenuAjax', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '35', 'pagename' => 'çoklu menü ekle', 'description' => 'çoklu menü ekle', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'addMultipleMenu', 'sefLink' => 'addMultipleMenu', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '36', 'pagename' => 'Blog.blogCreate', 'description' => 'Blog Oluşturma', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'new', 'sefLink' => 'blogCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '37', 'pagename' => 'Blog.blogUpdate', 'description' => 'Blog Güncelleme', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'edit', 'sefLink' => 'blogUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '38', 'pagename' => 'Blog.blogUpdate', 'description' => 'Blog Güncelleme post', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'update', 'sefLink' => 'blogUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '39', 'pagename' => 'Blog.blogDelete', 'description' => 'Blog Silme', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'delete', 'sefLink' => 'blogDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '40', 'pagename' => 'Blog.categoryCreate', 'description' => 'Kategori Oluşturma', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'new', 'sefLink' => 'categoryCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '41', 'pagename' => 'Blog.categoryUpdate', 'description' => 'Kategori Güncelleme', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'edit', 'sefLink' => 'categoryUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '42', 'pagename' => 'Blog.categoryDelete', 'description' => 'Kategori Silme', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'delete', 'sefLink' => 'categoryDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '43', 'pagename' => 'Blog.tagCreate', 'description' => 'Etiket Oluşturma post', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'create', 'sefLink' => 'tagCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => 'null', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '44', 'pagename' => 'Blog.tagUpdate', 'description' => 'Etiket Güncelleme', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'edit', 'sefLink' => 'tagUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => 'null', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '45', 'pagename' => 'Blog.tagDelete', 'description' => 'Etiket Silme', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'delete', 'sefLink' => 'tagDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => 'null', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '46', 'pagename' => 'Settings.setTemplate', 'description' => 'Tema Ayarlama', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'templateSelectPost', 'sefLink' => 'setTemplate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '47', 'pagename' => 'maintenance', 'description' => 'maintenance mode', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'maintenance', 'sefLink' => 'maintenance', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '48', 'pagename' => 'Settings.saveAllowedFiles', 'description' => 'Media allowed files', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'saveAllowedFiles', 'sefLink' => 'saveAllowedFiles', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '49', 'pagename' => 'Blog.blog', 'description' => 'Yazılar parent', 'className' => '', 'methodName' => '', 'sefLink' => '#', 'hasChild' => '1', 'pageSort' => '3', 'parent_pk' => NULL, 'symbol' => 'fas fa-align-center', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '50', 'pagename' => 'Settings.templateSettings', 'description' => 'Tema Ayarları', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'templateSettings', 'sefLink' => 'templateSettings', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '51', 'pagename' => 'Settings.templateSettings_post', 'description' => 'Tema Ayarları post', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'templateSettings_post', 'sefLink' => 'templateSettings_post', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '52', 'pagename' => 'Blog.commentList', 'description' => 'Yorum Listesi', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'commentList', 'sefLink' => 'comments', 'hasChild' => '0', 'pageSort' => '4', 'parent_pk' => '49', 'symbol' => 'fas fa-comments', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '53', 'pagename' => 'Blog.commentRemove', 'description' => 'Yorum silme', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'commentRemove', 'sefLink' => 'commentRemove', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '54', 'pagename' => 'Blog.confirmComment', 'description' => 'Yorum Onayla POST', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'confirmComment', 'sefLink' => 'confirmComment', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '55', 'pagename' => 'Blog.badwords', 'description' => 'Bad words listesi', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'badwordList', 'sefLink' => 'badwords', 'hasChild' => '0', 'pageSort' => '5', 'parent_pk' => '49', 'symbol' => 'fas fa-otter', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '56', 'pagename' => 'Blog.badwordsAdd', 'description' => 'badwords oluştur POST', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'badwordsAdd', 'sefLink' => 'badwordsAdd', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '57', 'pagename' => 'Blog.commentResponse', 'description' => 'comment datatablejs ajax', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'commentResponse', 'sefLink' => 'commentResponse', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '58', 'pagename' => 'Blog.displayComment', 'description' => 'Yorumu Görüntüle', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'displayComment', 'sefLink' => 'displayComment', 'hasChild' => '0', 'pageSort' => '0', 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '59', 'pagename' => 'Methods.methodCreate', 'description' => 'methodCreate', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'create', 'sefLink' => 'methodCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '60', 'pagename' => 'Backend.logs', 'description' => 'Günlükler', 'className' => '', 'methodName' => '', 'sefLink' => '#', 'hasChild' => '1', 'pageSort' => '7', 'parent_pk' => NULL, 'symbol' => 'fas fa-fingerprint', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '62', 'pagename' => 'Backend.update', 'description' => 'update', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'update', 'sefLink' => 'methodUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '63', 'pagename' => 'Backend.delete', 'description' => 'delete', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'delete', 'sefLink' => 'methodDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '64', 'pagename' => 'Blog.blogs', 'description' => 'Blog Listesi', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'index', 'sefLink' => 'blogs/1', 'hasChild' => '0', 'pageSort' => '1', 'parent_pk' => '49', 'symbol' => 'far fa-file-alt', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '65', 'pagename' => 'Blog.categories', 'description' => 'Kategori listesi', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'index', 'sefLink' => 'categories/1', 'hasChild' => '0', 'pageSort' => '2', 'parent_pk' => '49', 'symbol' => 'fas fa-project-diagram', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":false,"update_r":false,"read_r":true,"delete_r":false}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '66', 'pagename' => 'Blog.tags', 'description' => 'Etiket listesi', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'index', 'sefLink' => 'tags/1', 'hasChild' => '0', 'pageSort' => '3', 'parent_pk' => '49', 'symbol' => 'fas fa-tags', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '67', 'pagename' => 'Backend.elfinderConvertWebp', 'description' => 'elfinderConvertWebp', 'className' => '-Modules-Settings-Controllers-AJAX', 'methodName' => 'elfinderConvertWebp', 'sefLink' => 'elfinderConvertWebp', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '68', 'pagename' => 'Media.elfinderConnection', 'description' => 'elfinderConnection', 'className' => '-Modules-Media-Controllers-Media', 'methodName' => 'elfinderConnection', 'sefLink' => 'elfinderConnection', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '4', 'isActive' => '1'),
                array('id' => '69', 'pagename' => 'Fileeditor.fileEditor', 'description' => 'fileEditor', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'index', 'sefLink' => 'fileEditor', 'hasChild' => '0', 'pageSort' => '9', 'parent_pk' => NULL, 'symbol' => 'far fa-folder', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '70', 'pagename' => 'Fileeditor.listFiles', 'description' => 'listFiles', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'listFiles', 'sefLink' => 'listFiles', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '71', 'pagename' => 'Fileeditor.readFile', 'description' => 'readFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'readFile', 'sefLink' => 'readFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '72', 'pagename' => 'Fileeditor.saveFile', 'description' => 'saveFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'saveFile', 'sefLink' => 'saveFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true,"update_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '73', 'pagename' => 'Fileeditor.renameFile', 'description' => 'renameFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'renameFile', 'sefLink' => 'renameFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '74', 'pagename' => 'Fileeditor.createFile', 'description' => 'createFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'createFile', 'sefLink' => 'createFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '75', 'pagename' => 'Fileeditor.createFolder', 'description' => 'createFolder', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'createFolder', 'sefLink' => 'createFolder', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '76', 'pagename' => 'Fileeditor.moveFileOrFolder', 'description' => 'moveFileOrFolder', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'moveFileOrFolder', 'sefLink' => 'moveFileOrFolder', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '77', 'pagename' => 'Fileeditor.deleteFileOrFolder', 'description' => 'deleteFileOrFolder', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'deleteFileOrFolder', 'sefLink' => 'deleteFileOrFolder', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '78', 'pagename' => 'Theme.backendThemes', 'description' => 'backendThemes', 'className' => '-Modules-Theme-Controllers-Theme', 'methodName' => 'index', 'sefLink' => 'backendThemes', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '10', 'isActive' => '1'),
                array('id' => '79', 'pagename' => 'Theme.themesUpload', 'description' => '', 'className' => '-Modules-Theme-Controllers-Theme', 'methodName' => 'upload', 'sefLink' => 'themesUpload', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '10', 'isActive' => '1'),
                array('id' => '80', 'pagename' => 'Modules.modulesInstaller', 'description' => '', 'className' => '-Modules-ModulesInstaller-Controllers-ModulesInstaller', 'methodName' => 'index', 'sefLink' => 'modulesInstaller', 'hasChild' => '0', 'pageSort' => '8', 'parent_pk' => NULL, 'symbol' => 'fas fa-plug', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '7', 'isActive' => '1'),
                array('id' => '81', 'pagename' => 'moduleUpload', 'description' => '', 'className' => '-Modules-ModulesInstaller-Controllers-ModulesInstaller', 'methodName' => 'moduleUpload', 'sefLink' => 'moduleUpload', 'hasChild' => '0', 'pageSort' => '0', 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '7', 'isActive' => '1'),
                array('id' => '82', 'pagename' => 'testMail', 'description' => '', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'testMail', 'sefLink' => 'testMail', 'hasChild' => '0', 'pageSort' => '0', 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '83', 'pagename' => 'moduleScan', 'description' => '', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'moduleScan', 'sefLink' => 'moduleScan', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true,"create_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '84', 'pagename' => 'Logs.logs', 'description' => '', 'className' => '-Modules-Logs-Controllers-Logs', 'methodName' => 'index', 'sefLink' => 'logs', 'hasChild' => '0', 'pageSort' => '2', 'parent_pk' => '60', 'symbol' => 'fas fa-file-alt', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":false,"update_r":false,"read_r":true,"delete_r":false}', 'module_id' => '12', 'isActive' => '1')
            )
        );

        $commonModel->createMany('languages', [
            [
                'code'        => 'tr',
                'name'        => 'Türkçe',
                'native_name' => 'tr',
                'flag'        => 'fi fi-tr', // ya da varsa uygun bir ikon
                'direction'   => 'ltr',
                'is_active'   => 1,
                'is_frontend' => 1,
                'sort_order'  => 1
            ],
            [
                'code'        => 'en',
                'name'        => 'English',
                'native_name' => 'gb',
                'flag'        => 'fi fi-gb', // ya da varsa uygun bir ikon
                'direction'   => 'ltr',
                'is_default'  => 1,
                'is_active'   => 1,
                'is_frontend' => 1,
                'sort_order'  => 0
            ],
        ]);

        $commonModel->createMany('pages', [
            ['id' => 1, 'isActive' => '1', 'inMenu' => '1'],
            ['id' => 2, 'isActive' => '1', 'inMenu' => '1'],
        ]);

        $commonModel->createMany('pages_langs', [
            [
                [
                    'pages_id' => 1,
                    'lang' => 'en',
                    'title' => 'The Future of Modular Management',
                    'seflink' => 'homepage',
                    'content' => '
                <section class="hero-section text-center">
                    <div class="container py-3">
                        <img src="/templates/default/assets/hero_banner.png" class="img-fluid rounded-4 shadow-lg mb-5" alt="CI4MS Hero">
                        <h1 class="display-3 mb-4">CodeIgniter 4 Power, Modular Freedom</h1>
                        <p class="lead mb-5 max-width-700 mx-auto">CI4MS is the ultimate hybrid engine for building CMS and ERP systems. Designed for PHP 8.1+, it gives you the modularity you need without the bloat.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="/en/blog" class="btn btn-primary btn-lg px-5">Read Our Blog</a>
                            <a href="/en/contact" class="btn btn-outline-light btn-lg px-5">Contact Sales</a>
                        </div>
                    </div>
                </section>
                <section id="features" class="container">
                    <div class="text-center mb-5"><h2 class="display-5">Core Capabilities</h2></div>
                    <div class="row g-4">
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-cpu fs-1 text-primary mb-3"></i><h3>Modular Core</h3><p>Every feature from Auth to SEO is a discrete module that can be swapped or customized.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-shield-check fs-1 text-primary mb-3"></i><h3>Shield Security</h3><p>Integrates natively with CodeIgniter Shield for multi-group RBAC and session security.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-globe fs-1 text-primary mb-3"></i><h3>Global Ready</h3><p>Full multi-language support with locale-prefixed URLs and translation management.</p>
                        </div></div>
                    </div>
                </section>',
                    'seo' => json_encode(['description' => 'CI4MS Homepage'])
                ],
                [
                    'pages_id' => 1,
                    'lang' => 'tr',
                    'title' => 'Modüler Yönetimin Geleceği',
                    'seflink' => 'anasayfa',
                    'content' => '
                <section class="hero-section text-center">
                    <div class="container py-3">
                        <img src="/templates/default/assets/hero_banner.png" class="img-fluid rounded-4 shadow-lg mb-5" alt="CI4MS Hero">
                        <h1 class="display-3 mb-4">CodeIgniter 4 Gücü, Modüler Özgürlük</h1>
                        <p class="lead mb-5 max-width-700 mx-auto">CI4MS, CMS ve ERP sistemleri oluşturmak için nihai hibrit motorudur. PHP 8.1+ için tasarlanmış olup ihtiyacınız olan modülerliği karmaşıklık olmadan sunar.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="/tr/blog" class="btn btn-primary btn-lg px-5">Blogumuzu Oku</a>
                            <a href="/tr/iletisim" class="btn btn-outline-light btn-lg px-5">Satışla İletişime Geç</a>
                        </div>
                    </div>
                </section>
                <section id="features" class="container">
                    <div class="text-center mb-5"><h2 class="display-5">Temel Yetenekler</h2></div>
                    <div class="row g-4">
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-cpu fs-1 text-primary mb-3"></i><h3>Modüler Çekirdek</h3><p>Auth\'tan SEO\'ya kadar her özellik, değiştirilebilen veya özelleştirilebilen ayrı bir modüldür.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-shield-check fs-1 text-primary mb-3"></i><h3>Shield Güvenliği</h3><p>Çok gruplu RBAC ve oturum güvenliği için CodeIgniter Shield ile yerel olarak entegre olur.</p>
                        </div></div>
                        <div class="col-md-4"><div class="card p-4 h-100 border-0 shadow-sm">
                            <i class="bi bi-globe fs-1 text-primary mb-3"></i><h3>Küresel Hazırlık</h3><p>Locale önekli URL\'ler ve çeviri yönetimi ile tam çoklu dil desteği sunar.</p>
                        </div></div>
                    </div>
                </section>',
                    'seo' => json_encode(['description' => 'CI4MS Anasayfa'])
                ],
                [
                    'pages_id' => 2,
                    'lang' => 'en',
                    'title' => 'Contact Us',
                    'seflink' => 'contact',
                    'content' => '
                <section class="container py-5">
                    <div class="row align-items-center">
                        <div class="col-md-6"><h2 class="display-5 mb-4">Connect with CI4MS Experts</h2><p class="lead mb-5">Have questions about integrating CI4MS into your infrastructure? Our team is ready to assist.</p>
                        <div class="mb-4 d-flex gap-3"><i class="bi bi-envelope-at text-primary fs-3"></i> <div><h5>Email</h5><p>experts@ci4ms.pro</p></div></div>
                        <div class="d-flex gap-3"><i class="bi bi-telephone text-primary fs-3"></i> <div><h5>Call Us</h5><p>+1 (800) CI4-CORE</p></div></div></div>
                        <div class="col-md-6"><div class="card p-5 border-0 shadow-lg"><h3>Inquiry Form</h3><p>Coming soon...</p></div></div>
                    </div>
                </section>',
                    'seo' => json_encode(['description' => 'Contact CI4MS'])
                ],
                [
                    'pages_id' => 2,
                    'lang' => 'tr',
                    'title' => 'İletişim',
                    'seflink' => 'iletisim',
                    'content' => '
                <section class="container py-5">
                    <div class="row align-items-center">
                        <div class="col-md-6"><h2 class="display-5 mb-4">CI4MS Uzmanlarıyla Bağlantı Kurun</h2><p class="lead mb-5">CI4MS\'i altyapınıza entegre etme konusunda sorularınız mı var? Ekibimiz yardıma hazır.</p>
                        <div class="mb-4 d-flex gap-3"><i class="bi bi-envelope-at text-primary fs-3"></i> <div><h5>E-posta</h5><p>uzman@ci4ms.pro</p></div></div>
                        <div class="d-flex gap-3"><i class="bi bi-telephone text-primary fs-3"></i> <div><h5>Bize Ulaşın</h5><p>+90 (850) CI4-MS00</p></div></div></div>
                        <div class="col-md-6"><div class="card p-5 border-0 shadow-lg"><h3>Talep Formu</h3><p>Yakında...</p></div></div>
                    </div>
                </section>',
                    'seo' => json_encode(['description' => 'CI4MS İletişim'])
                ]
            ]
        ]);

        // 5. Blog Posts
        $blogs = [
            [
                'en' => ['title' => 'Why Modular Architecture is the Future', 'slug' => 'modular-architecture-future', 'summary' => 'Explore the benefits of modular design in software development.'],
                'tr' => ['title' => 'Neden Modüler Mimari Gelecek?', 'slug' => 'moduler-mimari-gelecek', 'summary' => 'Yazılım geliştirmede modüler tasarımın avantajlarını keşfedin.']
            ],
            [
                'en' => ['title' => 'Optimizing CodeIgniter 4 for Scale', 'slug' => 'optimizing-ci4-scale', 'summary' => 'Advanced tips for high-traffic CI4 applications.'],
                'tr' => ['title' => 'Hız ve Ölçek için CodeIgniter 4 Optimizasyonu', 'slug' => 'ci4-optimizasyon', 'summary' => 'Yüksek trafikli CI4 uygulamaları için ileri düzey ipuçları.']
            ],
            [
                'en' => ['title' => 'Building Custom Modules for CI4MS', 'slug' => 'building-modules', 'summary' => 'A step-by-step guide to building your first module.'],
                'tr' => ['title' => 'CI4MS İçin Özel Modül Geliştirme', 'slug' => 'modul-gelistirme', 'summary' => 'İlk modülünüzü oluşturmak için adım adım kılavuz.']
            ]
        ];

        foreach ($blogs as $b) {
            $blogId = $commonModel->create('blog', ['isActive' => 1, 'author' => 1]);
            $commonModel->create('blog_langs', [
                'blog_id' => $blogId,
                'lang' => 'en',
                'title' => $b['en']['title'],
                'seflink' => $b['en']['slug'],
                'content' => '<p>Modular architecture allows developers to separate concerns effectively. In CI4MS, each module has its own routes, controllers, and views...</p>',
                'seo' => json_encode(['description' => $b['en']['summary']])
            ]);
            $commonModel->create('blog_langs', [
                'blog_id' => $blogId,
                'lang' => 'tr',
                'title' => $b['tr']['title'],
                'seflink' => $b['tr']['slug'],
                'content' => '<p>Modüler mimari, geliştiricilerin sorumlulukları etkili bir şekilde ayırmasını sağlar. CI4MS\'de her modülün kendi rotaları, denetleyicileri ve görünümleri vardır...</p>',
                'seo' => json_encode(['description' => $b['tr']['summary']])
            ]);
        }

        $commonModel->createMany('menu', [
            ['title' => 'Frontend.home', 'seflink' => '/', 'queue' => 1, 'urlType' => 'pages', 'pages_id' => 1],
            ['title' => 'Frontend.blog', 'seflink' => 'blog', 'queue' => 2, 'urlType' => 'custom'],
            ['title' => 'Frontend.contact', 'seflink' => 'contact', 'queue' => 3, 'urlType' => 'pages', 'pages_id' => 2]
        ]);

        $encrypter = \Config\Services::encrypter();
        $commonModel->createMany(
            'settings',
            array(
                array('class' => 'Config\\App', 'key' => 'templateInfos', 'value' => '{"path":"default","name":null,"widgets":{"sidebar":{"searchWidget":"true","categoriesWidget":"true"}}}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'siteName', 'value' => 'ci4ms', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'logo', 'value' => '/media/logo.png', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'socialNetwork', 'value' => '[{"smName":"facebook","link":"https:\\/\\/facebook.com\\/bertugfahriozer"},{"smName":"twitter","link":"https:\\/\\/twitter.com\\/bertugfahriozer"},{"smName":"github","link":"https:\\/\\/github.com\\/bertugfahriozer"}]', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'contact', 'value' => '{"address":"Bal\\u0131kesir \\/ Turkey","phone":"+905000000000","email":"info@ci4ms.com"}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'mail', 'value' => '{
    "server": "mail.ci4ms.com",
    "port": "26",
    "address": "simple@ci4ms.com",
    "password": "' . base64_encode($encrypter->encrypt('123456789')) . '",
    "protocol": "smtp",
    "tls": false
}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Gmap', 'key' => 'map_iframe', 'value' => NULL, 'type' => 'NULL', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'slogan', 'value' => 'My First Ci4MS Project', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'maintenanceMode', 'value' => '0', 'type' => 'boolean', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'homePage', 'value' => '3', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'siteLanguageMode', 'value' => 'single', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\Security', 'key' => 'allowedFiles', 'value' => '["image\\/x-ms-bmp","image\\/gif","image\\/jpeg","image\\/png","image\\/x-icon","text\\/plain","image\\/webp"]', 'type' => 'string', 'context' => NULL),
                array('class' => 'Config\\Security', 'key' => 'badwords', 'value' => '{"status": 1, "autoReject": 0, "autoAccept": 1, "list": []}', 'type' => 'string', 'context' => NULL),
                array('class' => 'Elfinder', 'key' => 'convertWebp', 'value' => '1', 'type' => 'boolean', 'context' => NULL),
                array('class' => 'Config\\App', 'key' => 'defaultLocale', 'value' => 'en')
            )
        );
    }
}
