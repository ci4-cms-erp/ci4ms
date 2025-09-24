<?php

namespace Modules\Install\Services;

class InstallService
{
    public function createDefaultData(array $args)
    {
        $commonModel = new \ci4commonmodel\Models\CommonModel();
        $authLib = new \Modules\Auth\Libraries\AuthLibrary();
        $commonModel->create('auth_groups', [
            "id" => 1,
            "name" => "super user",
            "updated_at" => null,
            "description" => "Sistemi Yazan Teknik Personel",
            "seflink" => "backend",
            "created_at" => date('Y-m-d H:i:s'),
            "who_created" => null
        ]);

        $commonModel->create('users', [
            'id' => 1,
            'firstname' => $args['fname'],
            'sirname' => $args['sname'],
            'username' => $args['username'],
            'email' => $args['email'],
            'status' => 'active',
            'group_id' => 1,
            'password_hash' => $authLib->setPassword($args['password'])
        ]);

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
                array('id' => '1', 'pagename' => 'Backend.homepage', 'description' => 'Yönetim Paneli Anasayfası', 'className' => '-Modules-Backend-Controllers-Backend', 'methodName' => 'index', 'sefLink' => 'backend', 'hasChild' => '0', 'pageSort' => '1', 'parent_pk' => NULL, 'symbol' => 'fas fa-home', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '2', 'pagename' => 'Users.usersCrud', 'description' => 'Kullanıcı İşlemleri', 'className' => '', 'methodName' => '', 'sefLink' => '#', 'hasChild' => '1', 'pageSort' => '10', 'parent_pk' => NULL, 'symbol' => 'fas fa-users', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '3', 'pagename' => 'Users.userList', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'users', 'sefLink' => 'users/1', 'hasChild' => '0', 'pageSort' => '1', 'parent_pk' => '2', 'symbol' => 'fas fa-user-friends', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true, "update_r": true, "delete_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '4', 'pagename' => 'Users.addUser', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'create_user', 'sefLink' => 'create_user', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '6', 'pagename' => 'Users.permGroupList', 'description' => '', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'groupList', 'sefLink' => 'groupList/1', 'hasChild' => '0', 'pageSort' => '3', 'parent_pk' => '2', 'symbol' => 'fas fa-sitemap', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true, "update_r": true, "delete_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '7', 'pagename' => 'Users.addGroupPerms', 'description' => '', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'group_create', 'sefLink' => 'group_create', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '8', 'pagename' => 'Methods.methodList', 'description' => 'methodList', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'index', 'sefLink' => 'list', 'hasChild' => '0', 'pageSort' => '8', 'parent_pk' => NULL, 'symbol' => 'fas fa-memory', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '9', 'pagename' => 'Users.profile', 'description' => 'kullanıcnın kendi profili', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'profile', 'sefLink' => 'profile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '10', 'pagename' => 'Users.updateGroupPerms', 'description' => 'Kullanıcı Grup Yetkisi Güncelleme', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'group_update', 'sefLink' => 'group_update', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '11', 'pagename' => 'Users.specialAuthUser', 'description' => 'Kullanıcıya özel yetki verme', 'className' => '-Modules-Users-Controllers-PermgroupController', 'methodName' => 'user_perms', 'sefLink' => 'user_perms', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '12', 'pagename' => 'Users.updateUser', 'description' => 'Kullanıcının güncellendiği form sayfası', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'update_user', 'sefLink' => 'update_user', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '13', 'pagename' => 'Karalisteye Alma AJAX', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'ajax_blackList_post', 'sefLink' => 'blackList', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '14', 'pagename' => 'Karalisteden Çıkarma AJAX', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'ajax_remove_from_blackList_post', 'sefLink' => 'removeFromBlacklist', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '15', 'pagename' => 'Yetkili tarafından kullanıcın şifresi sıfırlanma AJAX', 'description' => 'Yetkili tarafından kullanıcının şifresini sıfırlama yapıldı', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'ajax_force_reset_password', 'sefLink' => 'forceResetPassword', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '16', 'pagename' => 'Settings.settings', 'description' => 'Şirket bilgileri ve site içi mail ayarlarının tutulduğu alan', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'index', 'sefLink' => 'settings', 'hasChild' => '0', 'pageSort' => '100', 'parent_pk' => NULL, 'symbol' => 'fas fa-cogs', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '17', 'pagename' => 'Şirket Bilgilerini Güncelle', 'description' => 'Şirket Bilgilerinin güncellendiği adım', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'compInfosPost', 'sefLink' => 'compInfosPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '18', 'pagename' => 'Şirket Bilgilerini Güncelle', 'description' => 'Şirket sosyal medyasının güncellendiği adım', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'socialMediaPost', 'sefLink' => 'socialMediaPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '19', 'pagename' => 'Şirket Bilgilerini Güncelle', 'description' => 'Şirket mail bilgilerinin güncellendiği adım', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'mailSettingsPost', 'sefLink' => 'mailSettingsPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '20', 'pagename' => 'Users.user_del', 'description' => '', 'className' => '-Modules-Users-Controllers-UserController', 'methodName' => 'user_del', 'sefLink' => 'user_del', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '11', 'isActive' => '1'),
                array('id' => '21', 'pagename' => 'Pages.pages', 'description' => 'Site Sayflarının Ayarları', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'index', 'sefLink' => 'pages/1', 'hasChild' => '0', 'pageSort' => '2', 'parent_pk' => NULL, 'symbol' => 'far fa-copy', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true, "read_r": true, "update_r": true, "delete_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '22', 'pagename' => 'Pages.pageAdd', 'description' => 'sayfa ekleme view', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'create', 'sefLink' => 'pageCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '23', 'pagename' => 'Pages.pageUpdate', 'description' => 'sayfa güncelleme view', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'update', 'sefLink' => 'pageUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '24', 'pagename' => 'sayfa silme', 'description' => 'sayfa silme', 'className' => '-Modules-Pages-Controllers-Pages', 'methodName' => 'delete_post', 'sefLink' => 'pageDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '8', 'isActive' => '1'),
                array('id' => '25', 'pagename' => 'limitli etiket listesi ajax', 'description' => 'sayfa blog kısımlarında keywordleri ortak kullanabilmek için oluşturulmuş link', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'limitTags_ajax', 'sefLink' => 'tagify', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '26', 'pagename' => 'seflink kontrol ajax', 'description' => 'sayfa blog kısımlarında seflink oluşturmak için kullanılır', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'autoLookSeflinks', 'sefLink' => 'checkSeflink', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '27', 'pagename' => 'Ayarlar kısmındaki giriş ayarları postu.', 'description' => 'Ayarlar kısmın da giriş ayarlarının kaydeder.', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'loginSettingsPost', 'sefLink' => 'loginSettingsPost', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '28', 'pagename' => 'aktifmi kontrolü ajax', 'description' => 'aktifmi kontrolü ajax', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'isActive', 'sefLink' => 'isActive', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r": true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '29', 'pagename' => 'Media.media', 'description' => 'Media', 'className' => '-Modules-Media-Controllers-Media', 'methodName' => 'index', 'sefLink' => 'media', 'hasChild' => '0', 'pageSort' => '6', 'parent_pk' => NULL, 'symbol' => 'fas fa-photo-video', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r": true}', 'module_id' => '4', 'isActive' => '1'),
                array('id' => '30', 'pagename' => 'Menu.menu', 'description' => 'menu', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'index', 'sefLink' => 'menu', 'hasChild' => '0', 'pageSort' => '4', 'parent_pk' => NULL, 'symbol' => 'fas fa-bars', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r": true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '31', 'pagename' => 'menuye link ekleme', 'description' => 'menuye link ekleme', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'create', 'sefLink' => 'createMenu', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '32', 'pagename' => 'menü listesi ajax', 'description' => 'menü listesi ajax', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'listURLs', 'sefLink' => 'menuList', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '33', 'pagename' => 'menüden link silme', 'description' => 'menüden link silme', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'delete_ajax', 'sefLink' => 'deleteMenuAjax', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '34', 'pagename' => 'menüyü sıralama', 'description' => 'menüyü sıralama', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'queue_ajax', 'sefLink' => 'queueMenuAjax', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '35', 'pagename' => 'çoklu menü ekle', 'description' => 'çoklu menü ekle', 'className' => '-Modules-Menu-Controllers-Menu', 'methodName' => 'addMultipleMenu', 'sefLink' => 'addMultipleMenu', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '5', 'isActive' => '1'),
                array('id' => '36', 'pagename' => 'Blog.blogCreate', 'description' => 'Blog Oluşturma', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'new', 'sefLink' => 'blogCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '37', 'pagename' => 'Blog.blogUpdate', 'description' => 'Blog Güncelleme', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'edit', 'sefLink' => 'blogUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '38', 'pagename' => 'Blog.blogUpdate', 'description' => 'Blog Güncelleme post', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'update', 'sefLink' => 'blogUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '39', 'pagename' => 'Blog.blogDelete', 'description' => 'Blog Silme', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'delete', 'sefLink' => 'blogDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '40', 'pagename' => 'Blog.categoryCreate', 'description' => 'Kategori Oluşturma', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'new', 'sefLink' => 'categoryCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '41', 'pagename' => 'Blog.categoryUpdate', 'description' => 'Kategori Güncelleme', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'edit', 'sefLink' => 'categoryUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '42', 'pagename' => 'Blog.categoryDelete', 'description' => 'Kategori Silme', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'delete', 'sefLink' => 'categoryDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '43', 'pagename' => 'Blog.tagCreate', 'description' => 'Etiket Oluşturma post', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'create', 'sefLink' => 'tagCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => 'null', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '44', 'pagename' => 'Blog.tagUpdate', 'description' => 'Etiket Güncelleme', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'edit', 'sefLink' => 'tagUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => 'null', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '45', 'pagename' => 'Blog.tagDelete', 'description' => 'Etiket Silme', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'delete', 'sefLink' => 'tagDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => 'null', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '46', 'pagename' => 'Settings.setTemplate', 'description' => 'Tema Ayarlama', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'templateSelectPost', 'sefLink' => 'setTemplate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '47', 'pagename' => 'maintenance', 'description' => 'maintenance mode', 'className' => '-Modules-Backend-Controllers-AJAX', 'methodName' => 'maintenance', 'sefLink' => 'maintenance', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '48', 'pagename' => 'Settings.saveAllowedFiles', 'description' => 'Media allowed files', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'saveAllowedFiles', 'sefLink' => 'saveAllowedFiles', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '49', 'pagename' => 'Blog.blog', 'description' => 'Yazılar parent', 'className' => '', 'methodName' => '', 'sefLink' => '#', 'hasChild' => '1', 'pageSort' => '3', 'parent_pk' => NULL, 'symbol' => 'fas fa-align-center', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '50', 'pagename' => 'Settings.templateSettings', 'description' => 'Tema Ayarları', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'templateSettings', 'sefLink' => 'templateSettings', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '51', 'pagename' => 'Settings.templateSettings_post', 'description' => 'Tema Ayarları post', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'templateSettings_post', 'sefLink' => 'templateSettings_post', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '52', 'pagename' => 'Blog.commentList', 'description' => 'Yorum Listesi', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'commentList', 'sefLink' => 'comments', 'hasChild' => '0', 'pageSort' => '4', 'parent_pk' => '49', 'symbol' => 'fas fa-comments', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '53', 'pagename' => 'Blog.commentRemove', 'description' => 'Yorum silme', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'commentRemove', 'sefLink' => 'commentRemove', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '54', 'pagename' => 'Blog.confirmComment', 'description' => 'Yorum Onayla POST', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'confirmComment', 'sefLink' => 'confirmComment', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '55', 'pagename' => 'Blog.badwords', 'description' => 'Bad words listesi', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'badwordList', 'sefLink' => 'badwords', 'hasChild' => '0', 'pageSort' => '5', 'parent_pk' => '49', 'symbol' => 'fas fa-otter', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '56', 'pagename' => 'Blog.badwordsAdd', 'description' => 'badwords oluştur POST', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'badwordsAdd', 'sefLink' => 'badwordsAdd', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '57', 'pagename' => 'Blog.commentResponse', 'description' => 'comment datatablejs ajax', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'commentResponse', 'sefLink' => 'commentResponse', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '58', 'pagename' => 'Blog.displayComment', 'description' => 'Yorumu Görüntüle', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'displayComment', 'sefLink' => 'displayComment', 'hasChild' => '0', 'pageSort' => '0', 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '59', 'pagename' => 'Methods.methodCreate', 'description' => 'methodCreate', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'create', 'sefLink' => 'methodCreate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '60', 'pagename' => 'Backend.logs', 'description' => 'Günlükler', 'className' => '', 'methodName' => '', 'sefLink' => '#', 'hasChild' => '1', 'pageSort' => '7', 'parent_pk' => NULL, 'symbol' => 'fas fa-fingerprint', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '61', 'pagename' => 'Backend.locked_accounts', 'description' => 'Kaba güç saldıraları ayarları', 'className' => '-Modules-Backend-Controllers-Locked', 'methodName' => 'index', 'sefLink' => 'locked/1', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => '60', 'symbol' => 'fas fa-user-shield', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '1', 'isActive' => '1'),
                array('id' => '62', 'pagename' => 'Backend.update', 'description' => 'update', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'update', 'sefLink' => 'methodUpdate', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '63', 'pagename' => 'Backend.delete', 'description' => 'delete', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'delete', 'sefLink' => 'methodDelete', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '64', 'pagename' => 'Blog.blogs', 'description' => 'Blog Listesi', 'className' => '-Modules-Blog-Controllers-Blog', 'methodName' => 'index', 'sefLink' => 'blogs/1', 'hasChild' => '0', 'pageSort' => '1', 'parent_pk' => '49', 'symbol' => 'far fa-file-alt', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '65', 'pagename' => 'Blog.categories', 'description' => 'Kategori listesi', 'className' => '-Modules-Blog-Controllers-Categories', 'methodName' => 'index', 'sefLink' => 'categories/1', 'hasChild' => '0', 'pageSort' => '2', 'parent_pk' => '49', 'symbol' => 'fas fa-project-diagram', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '66', 'pagename' => 'Blog.tags', 'description' => 'Etiket listesi', 'className' => '-Modules-Blog-Controllers-Tags', 'methodName' => 'index', 'sefLink' => 'tags/1', 'hasChild' => '0', 'pageSort' => '3', 'parent_pk' => '49', 'symbol' => 'fas fa-tags', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '2', 'isActive' => '1'),
                array('id' => '67', 'pagename' => 'Backend.elfinderConvertWebp', 'description' => 'elfinderConvertWebp', 'className' => '-Modules-Settings-Controllers-AJAX', 'methodName' => 'elfinderConvertWebp', 'sefLink' => 'elfinderConvertWebp', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '68', 'pagename' => 'Media.elfinderConnection', 'description' => 'elfinderConnection', 'className' => '-Modules-Media-Controllers-Media', 'methodName' => 'elfinderConnection', 'sefLink' => 'elfinderConnection', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '4', 'isActive' => '1'),
                array('id' => '69', 'pagename' => 'Fileeditor.fileEditor', 'description' => 'fileEditor', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'index', 'sefLink' => 'fileEditor', 'hasChild' => '0', 'pageSort' => '9', 'parent_pk' => NULL, 'symbol' => 'far fa-folder', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true,"read_r":true,"update_r":true,"delete_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '70', 'pagename' => 'Fileeditor.listFiles', 'description' => 'listFiles', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'listFiles', 'sefLink' => 'listFiles', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '71', 'pagename' => 'Fileeditor.readFile', 'description' => 'readFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'readFile', 'sefLink' => 'readFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '72', 'pagename' => 'Fileeditor.saveFile', 'description' => 'saveFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'saveFile', 'sefLink' => 'saveFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true,"update_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '73', 'pagename' => 'Fileeditor.renameFile', 'description' => 'renameFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'renameFile', 'sefLink' => 'renameFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '74', 'pagename' => 'Fileeditor.createFile', 'description' => 'createFile', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'createFile', 'sefLink' => 'createFile', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '75', 'pagename' => 'Fileeditor.createFolder', 'description' => 'createFolder', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'createFolder', 'sefLink' => 'createFolder', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '76', 'pagename' => 'Fileeditor.moveFileOrFolder', 'description' => 'moveFileOrFolder', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'moveFileOrFolder', 'sefLink' => 'moveFileOrFolder', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"update_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '77', 'pagename' => 'Fileeditor.deleteFileOrFolder', 'description' => 'deleteFileOrFolder', 'className' => '-Modules-Fileeditor-Controllers-Fileeditor', 'methodName' => 'deleteFileOrFolder', 'sefLink' => 'deleteFileOrFolder', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"delete_r":true}', 'module_id' => '3', 'isActive' => '1'),
                array('id' => '78', 'pagename' => 'Theme.backendThemes', 'description' => 'backendThemes', 'className' => '-Modules-Theme-Controllers-Theme', 'methodName' => 'index', 'sefLink' => 'backendThemes', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '10', 'isActive' => '1'),
                array('id' => '79', 'pagename' => 'Theme.themesUpload', 'description' => '', 'className' => '-Modules-Theme-Controllers-Theme', 'methodName' => 'upload', 'sefLink' => 'themesUpload', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '10', 'isActive' => '1'),
                array('id' => '80', 'pagename' => 'Modules.modulesInstaller', 'description' => '', 'className' => '-Modules-ModulesInstaller-Controllers-ModulesInstaller', 'methodName' => 'index', 'sefLink' => 'modulesInstaller', 'hasChild' => '0', 'pageSort' => '8', 'parent_pk' => NULL, 'symbol' => 'fas fa-plug', 'inNavigation' => '1', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '7', 'isActive' => '1'),
                array('id' => '81', 'pagename' => 'moduleUpload', 'description' => '', 'className' => '-Modules-ModulesInstaller-Controllers-ModulesInstaller', 'methodName' => 'moduleUpload', 'sefLink' => 'moduleUpload', 'hasChild' => '0', 'pageSort' => '0', 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":true}', 'module_id' => '7', 'isActive' => '1'),
                array('id' => '82', 'pagename' => 'testMail', 'description' => '', 'className' => '-Modules-Settings-Controllers-Settings', 'methodName' => 'testMail', 'sefLink' => 'testMail', 'hasChild' => '0', 'pageSort' => '0', 'parent_pk' => NULL, 'symbol' => '', 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true}', 'module_id' => '9', 'isActive' => '1'),
                array('id' => '83', 'pagename' => 'moduleScan', 'description' => '', 'className' => '-Modules-Methods-Controllers-Methods', 'methodName' => 'moduleScan', 'sefLink' => 'moduleScan', 'hasChild' => '0', 'pageSort' => NULL, 'parent_pk' => NULL, 'symbol' => NULL, 'inNavigation' => '0', 'isBackoffice' => '1', 'typeOfPermissions' => '{"read_r":true,"create_r":true}', 'module_id' => '6', 'isActive' => '1'),
                array('id' => '84', 'pagename' => 'Logs.logs', 'description' => '', 'className' => '-Modules-Logs-Controllers-Logs', 'methodName' => 'index', 'sefLink' => 'logs', 'hasChild' => '0', 'pageSort' => 2, 'parent_pk' => 60, 'symbol' => 'fas fa-file-alt', 'inNavigation' => 1, 'isBackoffice' => '1', 'typeOfPermissions' => '{"create_r":false,"update_r":false,"read_r":true,"delete_r":false}', 'module_id' => '12', 'isActive' => '1')
            )
        );

        $commonModel->createMany('pages', [
            ['id' => '1', 'title' => 'Hakkımızda', 'content' => '<!-- About section one-->
            <section class="py-5 bg-light" id="scroll-target">
                <div class="container px-5 my-5">
                    <div class="row gx-5 align-items-center">
                        <div class="col-lg-6"><img class="img-fluid rounded mb-5 mb-lg-0" src="https://dummyimage.com/600x400/343a40/6c757d" alt="..."></div>
                        <div class="col-lg-6">
                            <h2 class="fw-bolder">Our founding</h2>
                            <p class="lead fw-normal text-muted mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Iusto est, ut esse a labore aliquam beatae expedita. Blanditiis impedit numquam libero molestiae et fugit cupiditate, quibusdam expedita, maiores eaque quisquam.</p>
                        </div>
                    </div>
                </div>
            </section>
            <!-- About section two-->
            <section class="py-5">
                <div class="container px-5 my-5">
                    <div class="row gx-5 align-items-center">
                        <div class="col-lg-6 order-first order-lg-last"><img class="img-fluid rounded mb-5 mb-lg-0" src="https://dummyimage.com/600x400/343a40/6c757d" alt="..."></div>
                        <div class="col-lg-6">
                            <h2 class="fw-bolder">Growth & beyond</h2>
                            <p class="lead fw-normal text-muted mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Iusto est, ut esse a labore aliquam beatae expedita. Blanditiis impedit numquam libero molestiae et fugit cupiditate, quibusdam expedita, maiores eaque quisquam.</p>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Team members section-->
            <section class="py-5 bg-light">
                <div class="container px-5 my-5">
                    <div class="text-center">
                        <h2 class="fw-bolder">Our team</h2>
                        <p class="lead fw-normal text-muted mb-5">Dedicated to quality and your success</p>
                    </div>
                    <div class="row gx-5 row-cols-1 row-cols-sm-2 row-cols-xl-4 justify-content-center">
                        <div class="col mb-5 mb-5 mb-xl-0">
                            <div class="text-center">
                                <img class="img-fluid rounded-circle mb-4 px-4" src="https://dummyimage.com/150x150/ced4da/6c757d" alt="...">
                                <h5 class="fw-bolder">Ibbie Eckart</h5>
                                <div class="fst-italic text-muted">Founder & CEO</div>
                            </div>
                        </div>
                        <div class="col mb-5 mb-5 mb-xl-0">
                            <div class="text-center">
                                <img class="img-fluid rounded-circle mb-4 px-4" src="https://dummyimage.com/150x150/ced4da/6c757d" alt="...">
                                <h5 class="fw-bolder">Arden Vasek</h5>
                                <div class="fst-italic text-muted">CFO</div>
                            </div>
                        </div>
                        <div class="col mb-5 mb-5 mb-sm-0">
                            <div class="text-center">
                                <img class="img-fluid rounded-circle mb-4 px-4" src="https://dummyimage.com/150x150/ced4da/6c757d" alt="...">
                                <h5 class="fw-bolder">Toribio Nerthus</h5>
                                <div class="fst-italic text-muted">Operations Manager</div>
                            </div>
                        </div>
                        <div class="col mb-5">
                            <div class="text-center">
                                <img class="img-fluid rounded-circle mb-4 px-4" src="https://dummyimage.com/150x150/ced4da/6c757d" alt="...">
                                <h5 class="fw-bolder">Malvina Cilla</h5>
                                <div class="fst-italic text-muted">CTO</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>', 'seflink' => 'hakkimizda', 'isActive' => '1', 'seo' => '{"coverImage":"https:\\/\\/kun-cms\\/uploads\\/media\\/main-vector.png","IMGWidth":"398","IMGHeight":"249","description":"Ci4MS hakkında","keywords":[{"value":"hakkımızda"}]}', 'inMenu' => '1'],
            ['id' => '2', 'title' => 'İletişim', 'content' => '<section class="py-5">
                <div class="container px-5">
{\\App\\Libraries\\templates\\default\\Ci4mstemplateLib|contactForm/}
<!-- Contact cards-->
                    <div class="row gx-5 row-cols-2 row-cols-lg-4 py-5">
                        <div class="col">
                            <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-chat-dots"></i></div>
                            <div class="h5 mb-2">Chat with us</div>
                            <p class="text-muted mb-0">Chat live with one of our support specialists.</p>
                        </div>
                        <div class="col">
                            <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-people"></i></div>
                            <div class="h5">Ask the community</div>
                            <p class="text-muted mb-0">Explore our community forums and communicate with other users.</p>
                        </div>
                        <div class="col">
                            <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-question-circle"></i></div>
                            <div class="h5">Support center</div>
                            <p class="text-muted mb-0">Browse FAQ\'s and support articles to find solutions.</p>
                        </div>
                        <div class="col">
                            <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-telephone"></i></div>
                            <div class="h5">Call us</div>
                            <p class="text-muted mb-0">Call us during normal business hours at (555) 892-9403.</p>
                        </div>
                    </div>
                </div>
{\\App\\Libraries\\templates\\default\\Ci4mstemplateLib|gmapiframe/}
            </section>', 'seflink' => 'iletisim', 'isActive' => '1', 'seo' => '{"description":"Ci4MS iletişim"}', 'inMenu' => '1'],
            ['id' => '3', 'title' => 'Anasayfa', 'content' => '<!-- Header-->
<header class="bg-dark py-5">
    <div class="container px-5">
        <div class="row gx-5 align-items-center justify-content-center">
            <div class="col-lg-8 col-xl-7 col-xxl-6">
                <div class="my-5 text-center text-xl-start">
                    <h1 class="display-5 fw-bolder text-white mb-2">A Bootstrap 5 template for modern businesses</h1>
                    <p class="lead fw-normal text-white-50 mb-4">Quickly design and customize responsive mobile-first sites with Bootstrap, the world’s most popular front-end open source toolkit!</p>
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center justify-content-xl-start">
                        <a class="btn btn-primary btn-lg px-4 me-sm-3" href="#features">Get Started</a>
                        <a class="btn btn-outline-light btn-lg px-4" href="#!">Learn More</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 col-xxl-6 d-none d-xl-block text-center"><img class="img-fluid rounded-3 my-5" src="https://dummyimage.com/600x400/343a40/6c757d" alt="..." /></div>
        </div>
    </div>
</header>
<!-- Features section-->
<section class="py-5" id="features">
    <div class="container px-5 my-5">
        <div class="row gx-5">
            <div class="col-lg-4 mb-5 mb-lg-0"><h2 class="fw-bolder mb-0">A better way to start building.</h2></div>
            <div class="col-lg-8">
                <div class="row gx-5 row-cols-1 row-cols-md-2">
                    <div class="col mb-5 h-100">
                        <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-collection"></i></div>
                        <h2 class="h5">Featured title</h2>
                        <p class="mb-0">Paragraph of text beneath the heading to explain the heading. Here is just a bit more text.</p>
                    </div>
                    <div class="col mb-5 h-100">
                        <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-building"></i></div>
                        <h2 class="h5">Featured title</h2>
                        <p class="mb-0">Paragraph of text beneath the heading to explain the heading. Here is just a bit more text.</p>
                    </div>
                    <div class="col mb-5 mb-md-0 h-100">
                        <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-toggles2"></i></div>
                        <h2 class="h5">Featured title</h2>
                        <p class="mb-0">Paragraph of text beneath the heading to explain the heading. Here is just a bit more text.</p>
                    </div>
                    <div class="col h-100">
                        <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-toggles2"></i></div>
                        <h2 class="h5">Featured title</h2>
                        <p class="mb-0">Paragraph of text beneath the heading to explain the heading. Here is just a bit more text.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Testimonial section-->
<div class="py-5 bg-light">
    <div class="container px-5 my-5">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-10 col-xl-7">
                <div class="text-center">
                    <div class="fs-4 mb-4 fst-italic">"Working with Start Bootstrap templates has saved me tons of development time when building new projects! Starting with a Bootstrap template just makes things easier!"</div>
                    <div class="d-flex align-items-center justify-content-center">
                        <img class="rounded-circle me-3" src="https://dummyimage.com/40x40/ced4da/6c757d" alt="..." />
                        <div class="fw-bold">
                            Tom Ato
                            <span class="fw-bold text-primary mx-1">/</span>
                            CEO, Pomodoro
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>', 'seflink' => '/', 'isActive' => '1', 'seo' => '{"description":"Ci4MS anasayfa","keywords":[{"value":"anasayfa"}]}', 'inMenu' => '1']
        ]);

        $commonModel->createMany('menu', [
            ['pages_id' => 1, 'parent' => NULL, 'queue' => 2, 'urlType' => 'pages', 'title' => 'Hakkımızda', 'seflink' => 'hakkimizda', 'target' => NULL, 'hasChildren' => 0],
            ['pages_id' => 2, 'parent' => NULL, 'queue' => 4, 'urlType' => 'pages', 'title' => 'İletişim', 'seflink' => 'iletisim', 'target' => NULL, 'hasChildren' => 0],
            ['pages_id' => 3, 'parent' => NULL, 'queue' => 1, 'urlType' => 'pages', 'title' => 'Anasayfa', 'seflink' => '/', 'target' => NULL, 'hasChildren' => 0],
            ['pages_id' => NULL, 'parent' => NULL, 'queue' => 3, 'urlType' => 'url', 'title' => 'Blog', 'seflink' => '/blog/1', 'target' => NULL, 'hasChildren' => 0]
        ]);
        $encrypter = \Config\Services::encrypter();
        $commonModel->createMany('settings', [
            ['option' => 'siteName', 'content' => $args['siteName'] ?? 'Ci4MS'],
            ['option' => 'logo', 'content' => '/uploads/media/logo.png'],
            ['option' => 'siteURL', 'content' => $args['baseUrl'] ?? 'https://ci4ms.loc/'],
            ['option' => 'slogan', 'content' => $args['slogan'] ?? 'My First Ci4MS Project'],
            ['option' => 'socialNetwork', 'content' => '[{"smName":"facebook","link":"https:\\/\\/facebook.com\\/bertugfahriozer"},{"smName":"twitter","link":"https:\\/\\/twitter.com\\/bertugfahriozer"},{"smName":"github","link":"https:\\/\\/github.com\\/bertugfahriozer.com"}]'],
            ['option' => 'company', 'content' => '{"address":"Bal\\u0131kesir \\/ Turkey","phone":"+905000000000","email":"info@ci4ms.com"}'],
            ['option' => 'mail', 'content' => '{"protocol": "smtp","server": "ssl://smtp.gmail.com","port": "465","address": "simple@gmail.com","password": "' . base64_encode($encrypter->encrypt('123456789')) . '","tls": "0"}'],
            ['option' => 'map_iframe', 'content' => NULL],
            ['option' => 'locked', 'content' => '{"isActive": 1,"userNotification": 0,"adminNotification": 0,"min": 15,"try": 4,"record": 3,"notificationLimitLoginAttempts": ""}'],
            ['option' => 'templateInfos', 'content' => '{"path":"default","name":null,"widgets":{"sidebar":{"searchWidget":"true","categoriesWidget":"true"}}}'],
            ['option' => 'maintenanceMode', 'content' => '0'],
            ['option' => 'isActive', 'content' => '0'],
            ['option' => 'allowedFiles', 'content' => '["image\\/x-ms-bmp","image\\/gif","image\\/jpeg","image\\/png","image\\/x-icon","text\\/plain"]'],
            ['option' => 'badwords', 'content' => '{"status":1,"autoReject":0,"autoAccept":1,"list":["abaza","abazan","ag","ağzına sıçayım","ahmak","allah","allahsız","am","amarım","ambiti","am biti","amcığı","amcığın","amcığını","amcığınızı","amcık","amcık hoşafı","amcıklama","amcıklandı","amcik","amck","amckl","amcklama","amcklaryla","amckta","amcktan","amcuk","amık","amına","amınako","amına koy","amına koyarım","amına koyayım","amınakoyim","amına koyyim","amına s","amına sikem","amına sokam","amın feryadı","amını","amını s","amın oglu","amınoğlu","amın oğlu","amısına","amısını","amina","amina g","amina k","aminako","aminakoyarim","amina koyarim","amina koyayım","amina koyayim","aminakoyim","aminda","amindan","amindayken","amini","aminiyarraaniskiim","aminoglu","amin oglu","amiyum","amk","amkafa","amk çocuğu","amlarnzn","amlı","amm","ammak","ammna","amn","amna","amnda","amndaki","amngtn","amnn","amona","amq","amsız","amsiz","amsz","amteri","amugaa","amuğa","amuna","ana","anaaann","anal","analarn","anam","anamla","anan","anana","anandan","ananı","ananı","ananın","ananın am","ananın amı","ananın dölü","ananınki","ananısikerim","ananı sikerim","ananısikeyim","ananı sikeyim","ananızın","ananızın am","anani","ananin","ananisikerim","anani sikerim","ananisikeyim","anani sikeyim","anann","ananz","anas","anasını","anasının am","anası orospu","anasi","anasinin","anay","anayin","angut","anneni","annenin","annesiz","anuna","aptal","aq","a.q","a.q.","aq.","ass","atkafası","atmık","attırdığım","attrrm","auzlu","avrat","ayklarmalrmsikerim","azdım","azdır","azdırıcı","babaannesi kaşar","babanı","babanın","babani","babası pezevenk","bacağına sıçayım","bacına","bacını","bacının","bacini","bacn","bacndan","bacy","bastard","basur","beyinsiz","bızır","bitch","biting","bok","boka","bokbok","bokça","bokhu","bokkkumu","boklar","boktan","boku","bokubokuna","bokum","bombok","boner","bosalmak","boşalmak","cenabet","cibiliyetsiz","cibilliyetini","cibilliyetsiz","cif","cikar","cim","çük","dalaksız","dallama","daltassak","dalyarak","dalyarrak","dangalak","dassagi","diktim","dildo","dingil","dingilini","dinsiz","dkerim","domal","domalan","domaldı","domaldın","domalık","domalıyor","domalmak","domalmış","domalsın","domalt","domaltarak","domaltıp","domaltır","domaltırım","domaltip","domaltmak","dölü","dönek","düdük","eben","ebeni","ebenin","ebeninki","ebleh","ecdadını","ecdadini","embesil","emi","fahise","fahişe","feriştah","ferre","fuck","fucker","fuckin","fucking","gavad","gavat","geber","geberik","gebermek","gebermiş","gebertir","gerızekalı","gerizekalı","gerizekali","gerzek","giberim","giberler","gibis","gibiş","gibmek","gibtiler","goddamn","godoş","godumun","gotelek","gotlalesi","gotlu","gotten","gotundeki","gotunden","gotune","gotunu","gotveren","goyiim","goyum","goyuyim","goyyim","göt","göt deliği","götelek","göt herif","götlalesi","götlek","götoğlanı","göt oğlanı","götoş","götten","götü","götün","götüne","götünekoyim","götüne koyim","götünü","götveren","göt veren","göt verir","gtelek","gtn","gtnde","gtnden","gtne","gtten","gtveren","hasiktir","hassikome","hassiktir","has siktir","hassittir","haysiyetsiz","hayvan herif","hoşafı","hödük","hsktr","huur","ıbnelık","ibina","ibine","ibinenin","ibne","ibnedir","ibneleri","ibnelik","ibnelri","ibneni","ibnenin","ibnerator","ibnesi","idiot","idiyot","imansz","ipne","iserim","işerim","itoğlu it","kafam girsin","kafasız","kafasiz","kahpe","kahpenin","kahpenin feryadı","kaka","kaltak","kancık","kancik","kappe","karhane","kaşar","kavat","kavatn","kaypak","kayyum","kerane","kerhane","kerhanelerde","kevase","kevaşe","kevvase","koca göt","koduğmun","koduğmunun","kodumun","kodumunun","koduumun","koyarm","koyayım","koyiim","koyiiym","koyim","koyum","koyyim","krar","kukudaym","laciye boyadım","lavuk","liboş","madafaka","mal","malafat","malak","manyak","mcik","meme","memelerini","mezveleli","minaamcık","mincikliyim","mna","monakkoluyum","motherfucker","mudik","oc","ocuu","ocuun","OÇ","oç","o. çocuğu","oğlan","oğlancı","oğlu it","orosbucocuu","orospu","orospucocugu","orospu cocugu","orospu çoc","orospuçocuğu","orospu çocuğu","orospu çocuğudur","orospu çocukları","orospudur","orospular","orospunun","orospunun evladı","orospuydu","orospuyuz","orostoban","orostopol","orrospu","oruspu","oruspuçocuğu","oruspu çocuğu","osbir","ossurduum","ossurmak","ossuruk","osur","osurduu","osuruk","osururum","otuzbir","öküz","öşex","patlak zar","penis","pezevek","pezeven","pezeveng","pezevengi","pezevengin evladı","pezevenk","pezo","pic","pici","picler","piç","piçin oğlu","piç kurusu","piçler","pipi","pipiş","pisliktir","porno","pussy","puşt","puşttur","rahminde","revizyonist","s1kerim","s1kerm","s1krm","sakso","saksofon","salaak","salak","saxo","sekis","serefsiz","sevgi koyarım","sevişelim","sexs","sıçarım","sıçtığım","sıecem","sicarsin","sie","sik","sikdi","sikdiğim","sike","sikecem","sikem","siken","sikenin","siker","sikerim","sikerler","sikersin","sikertir","sikertmek","sikesen","sikesicenin","sikey","sikeydim","sikeyim","sikeym","siki","sikicem","sikici","sikien","sikienler","sikiiim","sikiiimmm","sikiim","sikiir","sikiirken","sikik","sikil","sikildiini","sikilesice","sikilmi","sikilmie","sikilmis","sikilmiş","sikilsin","sikim","sikimde","sikimden","sikime","sikimi","sikimiin","sikimin","sikimle","sikimsonik","sikimtrak","sikin","sikinde","sikinden","sikine","sikini","sikip","sikis","sikisek","sikisen","sikish","sikismis","sikiş","sikişen","sikişme","sikitiin","sikiyim","sikiym","sikiyorum","sikkim","sikko","sikleri","sikleriii","sikli","sikm","sikmek","sikmem","sikmiler","sikmisligim","siksem","sikseydin","sikseyidin","siksin","siksinbaya","siksinler","siksiz","siksok","siksz","sikt","sikti","siktigimin","siktigiminin","siktiğim","siktiğimin","siktiğiminin","siktii","siktiim","siktiimin","siktiiminin","siktiler","siktim","siktim","siktimin","siktiminin","siktir","siktir et","siktirgit","siktir git","siktirir","siktiririm","siktiriyor","siktir lan","siktirolgit","siktir ol git","sittimin","sittir","skcem","skecem","skem","sker","skerim","skerm","skeyim","skiim","skik","skim","skime","skmek","sksin","sksn","sksz","sktiimin","sktrr","skyim","slaleni","sokam","sokarım","sokarim","sokarm","sokarmkoduumun","sokayım","sokaym","sokiim","soktuğumunun","sokuk","sokum","sokuş","sokuyum","soxum","sulaleni","sülaleni","sülalenizi","sürtük","şerefsiz","şıllık","taaklarn","taaklarna","tarrakimin","tasak","tassak","taşak","taşşak","tipini s.k","tipinizi s.keyim","tiyniyat","toplarm","topsun","totoş","vajina","vajinanı","veled","veledizina","veled i zina","verdiimin","weled","weledizina","whore","xikeyim","yaaraaa","yalama","yalarım","yalarun","yaraaam","yarak","yaraksız","yaraktr","yaram","yaraminbasi","yaramn","yararmorospunun","yarra","yarraaaa","yarraak","yarraam","yarraamı","yarragi","yarragimi","yarragina","yarragindan","yarragm","yarrağ","yarrağım","yarrağımı","yarraimin","yarrak","yarram","yarramin","yarraminbaşı","yarramn","yarran","yarrana","yarrrak","yavak","yavş","yavşak","yavşaktır","yavuşak","yılışık","yilisik","yogurtlayam","yoğurtlayam","yrrak","zıkkımım","zibidi","zigsin","zikeyim","zikiiim","zikiim","zikik","zikim","ziksiiin","ziksiin","zulliyetini","zviyetini"]}'],
            ['option' => 'elfinderConvertWebp', 'content' => '1']
        ]);
    }
}
