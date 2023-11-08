<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;

class Ci4msDefaultsSeeder extends Seeder
{
    public function run()
    {
        $fname = CLI::prompt('Please enter your name');
        $sname = CLI::prompt('Please enter your sirname');
        $username = CLI::prompt('Please enter your username');
        $email = CLI::prompt('Please enter your E-mail');
        $password = CLI::prompt('Please enter your password');
        $commonModel = new \ci4commonmodel\Models\CommonModel();
        $authLib = new \Modules\Backend\Libraries\AuthLibrary();
        $commonModel->createMany(getenv('database.default.DBPrefix') . 'auth_permissions_pages', json_decode('[
  {
      "id": 1,
    "pagename": "homepage",
    "description": "Yönetim Paneli Anasayfası",
    "className": "-Modules-Backend-Controllers-Backend",
    "methodName": "index",
    "sefLink": "backend",
    "hasChild": 0,
    "pageSort": 1,
    "parent_pk": null,
    "symbol": "fas fa-home",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": \"1\"}"
  },
  {
      "id": 2,
    "pagename": "usersCrud",
    "description": "Kullanıcı İşlemleri",
    "className": "",
    "methodName": "",
    "sefLink": "#",
    "hasChild": 1,
    "pageSort": 9,
    "parent_pk": null,
    "symbol": "fas fa-users",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": true}"
  },
  {
      "id": 3,
    "pagename": "userList",
    "description": "",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "officeWorker",
    "sefLink": "officeWorker/1",
    "hasChild": 0,
    "pageSort": 1,
    "parent_pk": 2,
    "symbol": "fas fa-user-friends",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true, \"read_r\": true, \"update_r\": true, \"delete_r\": true}"
  },
  {
      "id": 4,
    "pagename": "addUser",
    "description": "",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "create_user",
    "sefLink": "create_user",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 5,
    "pagename": "Kullanıcı Ekleme POST",
    "description": "",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "create_user_post",
    "sefLink": "create_user",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 6,
    "pagename": "permGroupList",
    "description": "",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "groupList",
    "sefLink": "groupList/1",
    "hasChild": 0,
    "pageSort": 3,
    "parent_pk": 2,
    "symbol": "fas fa-sitemap",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 7,
    "pagename": "addGroupPerms",
    "description": "",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "group_create",
    "sefLink": "group_create",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 8,
    "pagename": "Grup Yetkisi Ekleme POST",
    "description": "",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "group_create_post",
    "sefLink": "group_create",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 9,
    "pagename": "profile",
    "description": "kullanıcnın kendi profili",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "profile",
    "sefLink": "profile",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true, \"read_r\": true}"
  },
  {
      "id": 10,
    "pagename": "Profil POST",
    "description": "kullanıcnın kendi profilinin güncellendiği adım",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "profile_post",
    "sefLink": "profile",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 11,
    "pagename": "updateGroupPerms",
    "description": "Kullanıcı Grup Yetkisi Güncelleme",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "group_update",
    "sefLink": "group_update",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 12,
    "pagename": "Grup Yetkisi Güncelleme POST",
    "description": "Kullanıcı Grup Yetkisi Güncelleme POST",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "group_update_post",
    "sefLink": "group_update",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 13,
    "pagename": "specialAuthUser",
    "description": "Kullanıcıya özel yetki verme",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "user_perms",
    "sefLink": "user_perms",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 14,
    "pagename": "Kullanıcıya özel yetki verme POST",
    "description": "Kullanıcıya özel yetki verme POST",
    "className": "-Modules-Backend-Controllers-PermgroupController",
    "methodName": "user_perms_post",
    "sefLink": "user_perms",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 15,
    "pagename": "updateUser",
    "description": "Kullanıcının güncellendiği form sayfası",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "update_user",
    "sefLink": "update_user",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 16,
    "pagename": "Kullanıcı Gündelleme POST",
    "description": "Kullanıcının güncellendiği form sayfası POST",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "update_user_post",
    "sefLink": "update_user",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 17,
    "pagename": "Karalisteye Alma AJAX",
    "description": "",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "ajax_blackList_post",
    "sefLink": "blackList",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 18,
    "pagename": "Karalisteden Çıkarma AJAX",
    "description": "",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "ajax_remove_from_blackList_post",
    "sefLink": "removeFromBlacklist",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 19,
    "pagename": "Yetkili tarafından kullanıcın şifresi sıfırlanma AJAX",
    "description": "Yetkili tarafından kullanıcının şifresini sıfırlama yapıldı",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "ajax_force_reset_password",
    "sefLink": "forceResetPassword",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 20,
    "pagename": "settings",
    "description": "Şirket bilgileri ve site içi mail ayarlarının tutulduğu alan",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "index",
    "sefLink": "settings",
    "hasChild": 0,
    "pageSort": 10,
    "parent_pk": null,
    "symbol": "fas fa-cogs",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 21,
    "pagename": "Şirket Bilgilerini Güncelle",
    "description": "Şirket Bilgilerinin güncellendiği adım",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "compInfosPost",
    "sefLink": "compInfosPost",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 22,
    "pagename": "Şirket Bilgilerini Güncelle",
    "description": "Şirket sosyal medyasının güncellendiği adım",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "socialMediaPost",
    "sefLink": "socialMediaPost",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 23,
    "pagename": "Şirket Bilgilerini Güncelle",
    "description": "Şirket mail bilgilerinin güncellendiği adım",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "mailSettingsPost",
    "sefLink": "mailSettingsPost",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 24,
    "pagename": "user_del",
    "description": "",
    "className": "-Modules-Backend-Controllers-UserController",
    "methodName": "user_del",
    "sefLink": "user_del",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 25,
    "pagename": "pages",
    "description": "Site Sayflarının Ayarları",
    "className": "-Modules-Backend-Controllers-Pages",
    "methodName": "index",
    "sefLink": "pages/1",
    "hasChild": 0,
    "pageSort": 2,
    "parent_pk": null,
    "symbol": "far fa-copy",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 26,
    "pagename": "pageAdd",
    "description": "sayfa ekleme view",
    "className": "-Modules-Backend-Controllers-Pages",
    "methodName": "create",
    "sefLink": "pageCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 27,
    "pagename": "Sayfa Ekleme Post",
    "description": "sayfa ekleme post",
    "className": "-Modules-Backend-Controllers-Pages",
    "methodName": "create_post",
    "sefLink": "pageCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 28,
    "pagename": "pageUpdate",
    "description": "sayfa güncelleme view",
    "className": "-Modules-Backend-Controllers-Pages",
    "methodName": "update",
    "sefLink": "pageUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 29,
    "pagename": "Sayfa güncelleme post",
    "description": "sayfa güncelleme post",
    "className": "-Modules-Backend-Controllers-Pages",
    "methodName": "update_post",
    "sefLink": "pageUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 30,
    "pagename": "sayfa silme",
    "description": "sayfa silme",
    "className": "-Modules-Backend-Controllers-Pages",
    "methodName": "delete_post",
    "sefLink": "pageDelete",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 31,
    "pagename": "limitli etiket listesi ajax",
    "description": "sayfa blog kısımlarında keywordleri ortak kullanabilmek için oluşturulmuş link",
    "className": "-Modules-Backend-Controllers-AJAX",
    "methodName": "limitTags_ajax",
    "sefLink": "tagify",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 32,
    "pagename": "seflink kontrol ajax",
    "description": "sayfa blog kısımlarında seflink oluşturmak için kullanılır",
    "className": "-Modules-Backend-Controllers-AJAX",
    "methodName": "autoLookSeflinks",
    "sefLink": "checkSeflink",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 33,
    "pagename": "Ayarlar kısmındaki giriş ayarları postu.",
    "description": "Ayarlar kısmın da giriş ayarlarının kaydeder.",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "loginSettingsPost",
    "sefLink": "loginSettingsPost",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 34,
    "pagename": "aktifmi kontrolü ajax",
    "description": "aktifmi kontrolü ajax",
    "className": "-Modules-Backend-Controllers-AJAX",
    "methodName": "isActive",
    "sefLink": "isActive",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 35,
    "pagename": "media",
    "description": "Media",
    "className": "-Modules-Backend-Controllers-Media",
    "methodName": "index",
    "sefLink": "media",
    "hasChild": 0,
    "pageSort": 6,
    "parent_pk": null,
    "symbol": "fas fa-photo-video",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 36,
    "pagename": "menu",
    "description": "menu",
    "className": "-Modules-Backend-Controllers-Menu",
    "methodName": "index",
    "sefLink": "menu",
    "hasChild": 0,
    "pageSort": 4,
    "parent_pk": null,
    "symbol": "fas fa-bars",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": true}"
  },
  {
      "id": 37,
    "pagename": "menuye link ekleme",
    "description": "menuye link ekleme",
    "className": "-Modules-Backend-Controllers-Menu",
    "methodName": "create",
    "sefLink": "createMenu",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 38,
    "pagename": "menü listesi ajax",
    "description": "menü listesi ajax",
    "className": "-Modules-Backend-Controllers-Menu",
    "methodName": "listURLs",
    "sefLink": "menuList",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": true}"
  },
  {
      "id": 39,
    "pagename": "menüden link silme",
    "description": "menüden link silme",
    "className": "-Modules-Backend-Controllers-Menu",
    "methodName": "delete_ajax",
    "sefLink": "deleteMenuAjax",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 40,
    "pagename": "menüyü sıralama",
    "description": "menüyü sıralama",
    "className": "-Modules-Backend-Controllers-Menu",
    "methodName": "queue_ajax",
    "sefLink": "queueMenuAjax",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 41,
    "pagename": "çoklu menü ekle",
    "description": "çoklu menü ekle",
    "className": "-Modules-Backend-Controllers-Menu",
    "methodName": "addMultipleMenu",
    "sefLink": "addMultipleMenu",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 42,
    "pagename": "blogs",
    "description": "Blog Listesi",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "index",
    "sefLink": "blogs/1",
    "hasChild": 0,
    "pageSort": 1,
    "parent_pk": null,
    "symbol": "far fa-file-alt",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 43,
    "pagename": "blogCreate",
    "description": "Blog Oluşturma",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "new",
    "sefLink": "blogCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 44,
    "pagename": "blogCreate",
    "description": "Blog Oluşturma post",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "create",
    "sefLink": "blogCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 45,
    "pagename": "blogUpdate",
    "description": "Blog Güncelleme",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "edit",
    "sefLink": "blogUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 46,
    "pagename": "blogUpdate",
    "description": "Blog Güncelleme post",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "update",
    "sefLink": "blogUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 47,
    "pagename": "blogDelete",
    "description": "Blog Silme",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "delete",
    "sefLink": "blogDelete",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 48,
    "pagename": "categories",
    "description": "Kategori listesi",
    "className": "-Modules-Backend-Controllers-Categories",
    "methodName": "index",
    "sefLink": "categories/1",
    "hasChild": 0,
    "pageSort": 2,
    "parent_pk": null,
    "symbol": "fas fa-project-diagram",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 49,
    "pagename": "categoryCreate",
    "description": "Kategori Oluşturma",
    "className": "-Modules-Backend-Controllers-Categories",
    "methodName": "new",
    "sefLink": "categoryCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 50,
    "pagename": "categoryCreate",
    "description": "Kategori Oluşturma post",
    "className": "-Modules-Backend-Controllers-Categories",
    "methodName": "create",
    "sefLink": "categoryCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 51,
    "pagename": "categoryUpdate",
    "description": "Kategori Güncelleme",
    "className": "-Modules-Backend-Controllers-Categories",
    "methodName": "edit",
    "sefLink": "categoryUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 52,
    "pagename": "categoryUpdate",
    "description": "Kategori Güncelleme post",
    "className": "-Modules-Backend-Controllers-Categories",
    "methodName": "update",
    "sefLink": "categoryUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 53,
    "pagename": "categoryDelete",
    "description": "Kategori Silme",
    "className": "-Modules-Backend-Controllers-Categories",
    "methodName": "delete",
    "sefLink": "categoryDelete",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 54,
    "pagename": "tags",
    "description": "Etiket listesi",
    "className": "-Modules-Backend-Controllers-Tags",
    "methodName": "index",
    "sefLink": "tags/1",
    "hasChild": 0,
    "pageSort": 3,
    "parent_pk": null,
    "symbol": "fas fa-tags",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true, \"read_r\": true, \"update_r\": true, \"delete_r\": true}"
  },
  {
      "id": 55,
    "pagename": "tagCreate",
    "description": "Etiket Oluşturma post",
    "className": "-Modules-Backend-Controllers-Tags",
    "methodName": "create",
    "sefLink": "tagCreate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": "null",
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 56,
    "pagename": "tagUpdate",
    "description": "Etiket Güncelleme",
    "className": "-Modules-Backend-Controllers-Tags",
    "methodName": "edit",
    "sefLink": "tagUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": "null",
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 57,
    "pagename": "tagUpdate",
    "description": "Etiket Güncelleme post",
    "className": "-Modules-Backend-Controllers-Tags",
    "methodName": "update",
    "sefLink": "tagUpdate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": "null",
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 58,
    "pagename": "tagDelete",
    "description": "Etiket Silme",
    "className": "-Modules-Backend-Controllers-Tags",
    "methodName": "delete",
    "sefLink": "tagDelete",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": "null",
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 59,
    "pagename": "blog",
    "description": "Yazılar parent",
    "className": "",
    "methodName": "",
    "sefLink": "#",
    "hasChild": 1,
    "pageSort": 3,
    "parent_pk": null,
    "symbol": "fas fa-align-center",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": true}"
  },
  {
      "id": 60,
    "pagename": "logs",
    "description": "Günlükler",
    "className": "",
    "methodName": "",
    "sefLink": "#",
    "hasChild": 1,
    "pageSort": 7,
    "parent_pk": null,
    "symbol": "fas fa-fingerprint",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": true}"
  },
  {
      "id": 61,
    "pagename": "setTemplate",
    "description": "Tema Ayarlama",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "templateSelectPost",
    "sefLink": "setTemplate",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 62,
    "pagename": "maintenance",
    "description": "maintenance mode",
    "className": "-Modules-Backend-Controllers-AJAX",
    "methodName": "maintenance",
    "sefLink": "maintenance",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 63,
    "pagename": "saveAllowedFiles",
    "description": "Media allowed files",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "saveAllowedFiles",
    "sefLink": "saveAllowedFiles",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 64,
    "pagename": "locked_accounts",
    "description": "Kaba güç saldıraları ayarları",
    "className": "-Modules-Backend-Controllers-Locked",
    "methodName": "index",
    "sefLink": "locked/1",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": 60,
    "symbol": "fas fa-user-shield",
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true, \"read_r\": true, \"update_r\": true, \"delete_r\": true}"
  },
  {
      "id": 65,
    "pagename": "templateSettings",
    "description": "Tema Ayarları",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "templateSettings",
    "sefLink": "templateSettings",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 66,
    "pagename": "templateSettings_post",
    "description": "Tema Ayarları post",
    "className": "-Modules-Backend-Controllers-Settings",
    "methodName": "templateSettings_post",
    "sefLink": "templateSettings_post",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 67,
    "pagename": "commentList",
    "description": "Yorum Listesi",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "commentList",
    "sefLink": "comments",
    "hasChild": 0,
    "pageSort": 4,
    "parent_pk": 59,
    "symbol": null,
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true, \"read_r\": true, \"update_r\": true, \"delete_r\": true}"
  },
  {
      "id": 68,
    "pagename": "commentRemove",
    "description": "Yorum silme",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "commentRemove",
    "sefLink": "commentRemove",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"delete_r\": true}"
  },
  {
      "id": 69,
    "pagename": "confirmComment",
    "description": "Yorum Onayla POST",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "confirmComment",
    "sefLink": "confirmComment",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 70,
    "pagename": "badwords",
    "description": "Bad words listesi",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "badwordList",
    "sefLink": "badwords",
    "hasChild": 0,
    "pageSort": 5,
    "parent_pk": 59,
    "symbol": null,
    "inNavigation": 1,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true, \"read_r\": true, \"update_r\": true, \"delete_r\": true}"
  },
  {
      "id": 71,
    "pagename": "badwordsAdd",
    "description": "badwords oluştur POST",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "badwordsAdd",
    "sefLink": "badwordsAdd",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  },
  {
      "id": 72,
    "pagename": "commentResponse",
    "description": "comment datatablejs ajax",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "commentResponse",
    "sefLink": "commentResponse",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"read_r\": true}"
  },
  {
      "id": 73,
    "pagename": "displayComment",
    "description": "Yorumu Görüntüle",
    "className": "-Modules-Backend-Controllers-Blog",
    "methodName": "displayComment",
    "sefLink": "displayComment",
    "hasChild": 0,
    "pageSort": 0,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 74,
    "pagename": "elfinderConvertWebp",
    "description": "elfinderConvertWebp",
    "className": "-Modules-Backend-Controllers-AJAX",
    "methodName": "elfinderConvertWebp",
    "sefLink": "elfinderConvertWebp",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"update_r\": true}"
  },
  {
      "id": 75,
    "pagename": "elfinderConnection",
    "description": "elfinderConnection",
    "className": "-Modules-Backend-Controllers-Media",
    "methodName": "elfinderConnection",
    "sefLink": "elfinderConnection",
    "hasChild": 0,
    "pageSort": null,
    "parent_pk": null,
    "symbol": null,
    "inNavigation": 0,
    "isBackoffice": 1,
    "typeOfPermissions": "{\"create_r\": true}"
  }
]', true));
        $commonModel->edit(getenv('database.default.DBPrefix') . 'auth_permissions_pages', ['parent_pk' => 59], ['id' => 42]);
        $commonModel->edit(getenv('database.default.DBPrefix') . 'auth_permissions_pages', ['parent_pk' => 59], ['id' => 48]);
        $commonModel->edit(getenv('database.default.DBPrefix') . 'auth_permissions_pages', ['parent_pk' => 59], ['id' => 54]);
        $commonModel->create(getenv('database.default.DBPrefix') . 'auth_groups', [
            "id" => 1,
            "name" => "super user",
            "updated_at" => date('Y-m-d H:i:s'),
            "description" => "Sistemi Yazan Teknik Personel",
            "seflink" => "backend",
            "created_at" => date('Y-m-d H:i:s'),
            "who_created" => null
        ]);
        $commonModel->create(getenv('database.default.DBPrefix') . 'users', [
            'firstname' => $fname,
            'sirname' => $sname,
            'username' => $username,
            'email' => $email,
            'status' => 'active',
            'group_id' => 1,
            'password_hash' => $authLib->setPassword($password)
        ]);
        $commonModel->createMany(getenv('database.default.DBPrefix') . 'auth_groups_permissions', json_decode('[
  {
    "group_id": 1,
    "page_id": 1,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 2,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 3,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 4,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 5,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 6,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 7,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 8,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 9,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 10,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 11,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 12,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 13,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 14,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 15,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 16,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 17,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 18,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 19,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 20,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 21,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 22,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 23,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 24,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 25,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 26,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 27,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 28,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 29,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 30,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 31,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 32,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 33,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 34,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 35,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 36,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 37,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 38,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 39,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 40,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 41,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 42,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 43,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 44,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 45,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 46,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 47,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 48,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 49,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 50,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 51,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 52,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 53,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 54,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 55,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 56,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 57,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 58,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 59,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 60,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 61,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 62,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 63,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 64,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 65,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 66,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 67,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 68,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 69,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 70,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 71,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 72,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 73,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 74,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  },
  {
    "group_id": 1,
    "page_id": 75,
    "create_r": 1,
    "update_r": 1,
    "read_r": 1,
    "delete_r": 1
  }
]', true));
        $commonModel->createMany(getenv('database.default.DBPrefix') . 'settings', json_decode('[
  {
    "id": 1,
    "option": "siteName",
    "content": "Ci4MS"
  },
  {
    "id": 7,
    "option": "logo",
    "content": "https://ci4ms/uploads/media/logo.png"
  },
  {
    "id": 8,
    "option": "siteURL",
    "content": "https://ci4ms/"
  },
  {
    "id": 9,
    "option": "slogan",
    "content": "My website using Ci4MS"
  },
  {
    "id": 10,
    "option": "socialNetwork",
    "content": "[{\"smName\":\"facebook\",\"link\":\"https:\\/\\/facebook.com\\/bertugfahriozer\"},{\"smName\":\"twitter\",\"link\":\"https:\\/\\/twitter.com\\/bertugfahriozer\"},{\"smName\":\"github\",\"link\":\"https:\\/\\/github.com\\/bertugfahriozer.com\"}]"
  },
  {
    "id": 11,
    "option": "company",
    "content": "{\"address\": \"Balıkesir / Turkey\",\"phone\": \"+905469612939\",\"email\": \"info@ci4ms.com\"}"
  },
  {
    "id": 12,
    "option": "mail",
    "content": "{\"protocol\": \"smtp\",\"server\": \"ssl://smtp.gmail.com\",\"port\": \"465\",\"address\": \"bertugfahriozer@gmail.com\",\"password\": \"123456789\",\"tls\": \"0\"}"
  },
  {
    "id": 13,
    "option": "map_iframe",
    "content": null
  },
  {
    "id": 14,
    "option": "locked",
    "content": "{\"isActive\": 1,\"userNotification\": 0,\"adminNotification\": 0,\"min\": 15,\"try\": 4,\"record\": 3,\"notificationLimitLoginAttempts\": \"\"}"
  },
  {
    "id": 15,
    "option": "templateInfos",
    "content": "{\"path\":\"default\",\"name\":null,\"widgets\":{\"sidebar\":{\"searchWidget\":\"true\",\"categoriesWidget\":\"true\"}}}"
  },
  {
    "id": 16,
    "option": "maintenanceMode",
    "content": "0"
  },
  {
    "id": 17,
    "option": "isActive",
    "content": "1"
  },
  {
    "id": 18,
    "option": "allowedFiles",
    "content": "[\"image\\/x-ms-bmp\",\"image\\/gif\",\"image\\/jpeg\",\"image\\/png\",\"image\\/x-icon\",\"text\\/plain\"]"
  },
  {
    "id": 34,
    "option": "badwords",
    "content": "{\"status\":1,\"autoReject\":0,\"autoAccept\":1,\"list\":[\"abaza\",\"abazan\",\"ag\",\"ağzına sıçayım\",\"ahmak\",\"allah\",\"allahsız\",\"am\",\"amarım\",\"ambiti\",\"am biti\",\"amcığı\",\"amcığın\",\"amcığını\",\"amcığınızı\",\"amcık\",\"amcık hoşafı\",\"amcıklama\",\"amcıklandı\",\"amcik\",\"amck\",\"amckl\",\"amcklama\",\"amcklaryla\",\"amckta\",\"amcktan\",\"amcuk\",\"amık\",\"amına\",\"amınako\",\"amına koy\",\"amına koyarım\",\"amına koyayım\",\"amınakoyim\",\"amına koyyim\",\"amına s\",\"amına sikem\",\"amına sokam\",\"amın feryadı\",\"amını\",\"amını s\",\"amın oglu\",\"amınoğlu\",\"amın oğlu\",\"amısına\",\"amısını\",\"amina\",\"amina g\",\"amina k\",\"aminako\",\"aminakoyarim\",\"amina koyarim\",\"amina koyayım\",\"amina koyayim\",\"aminakoyim\",\"aminda\",\"amindan\",\"amindayken\",\"amini\",\"aminiyarraaniskiim\",\"aminoglu\",\"amin oglu\",\"amiyum\",\"amk\",\"amkafa\",\"amk çocuğu\",\"amlarnzn\",\"amlı\",\"amm\",\"ammak\",\"ammna\",\"amn\",\"amna\",\"amnda\",\"amndaki\",\"amngtn\",\"amnn\",\"amona\",\"amq\",\"amsız\",\"amsiz\",\"amsz\",\"amteri\",\"amugaa\",\"amuğa\",\"amuna\",\"ana\",\"anaaann\",\"anal\",\"analarn\",\"anam\",\"anamla\",\"anan\",\"anana\",\"anandan\",\"ananı\",\"ananı\",\"ananın\",\"ananın am\",\"ananın amı\",\"ananın dölü\",\"ananınki\",\"ananısikerim\",\"ananı sikerim\",\"ananısikeyim\",\"ananı sikeyim\",\"ananızın\",\"ananızın am\",\"anani\",\"ananin\",\"ananisikerim\",\"anani sikerim\",\"ananisikeyim\",\"anani sikeyim\",\"anann\",\"ananz\",\"anas\",\"anasını\",\"anasının am\",\"anası orospu\",\"anasi\",\"anasinin\",\"anay\",\"anayin\",\"angut\",\"anneni\",\"annenin\",\"annesiz\",\"anuna\",\"aptal\",\"aq\",\"a.q\",\"a.q.\",\"aq.\",\"ass\",\"atkafası\",\"atmık\",\"attırdığım\",\"attrrm\",\"auzlu\",\"avrat\",\"ayklarmalrmsikerim\",\"azdım\",\"azdır\",\"azdırıcı\",\"babaannesi kaşar\",\"babanı\",\"babanın\",\"babani\",\"babası pezevenk\",\"bacağına sıçayım\",\"bacına\",\"bacını\",\"bacının\",\"bacini\",\"bacn\",\"bacndan\",\"bacy\",\"bastard\",\"basur\",\"beyinsiz\",\"bızır\",\"bitch\",\"biting\",\"bok\",\"boka\",\"bokbok\",\"bokça\",\"bokhu\",\"bokkkumu\",\"boklar\",\"boktan\",\"boku\",\"bokubokuna\",\"bokum\",\"bombok\",\"boner\",\"bosalmak\",\"boşalmak\",\"cenabet\",\"cibiliyetsiz\",\"cibilliyetini\",\"cibilliyetsiz\",\"cif\",\"cikar\",\"cim\",\"çük\",\"dalaksız\",\"dallama\",\"daltassak\",\"dalyarak\",\"dalyarrak\",\"dangalak\",\"dassagi\",\"diktim\",\"dildo\",\"dingil\",\"dingilini\",\"dinsiz\",\"dkerim\",\"domal\",\"domalan\",\"domaldı\",\"domaldın\",\"domalık\",\"domalıyor\",\"domalmak\",\"domalmış\",\"domalsın\",\"domalt\",\"domaltarak\",\"domaltıp\",\"domaltır\",\"domaltırım\",\"domaltip\",\"domaltmak\",\"dölü\",\"dönek\",\"düdük\",\"eben\",\"ebeni\",\"ebenin\",\"ebeninki\",\"ebleh\",\"ecdadını\",\"ecdadini\",\"embesil\",\"emi\",\"fahise\",\"fahişe\",\"feriştah\",\"ferre\",\"fuck\",\"fucker\",\"fuckin\",\"fucking\",\"gavad\",\"gavat\",\"geber\",\"geberik\",\"gebermek\",\"gebermiş\",\"gebertir\",\"gerızekalı\",\"gerizekalı\",\"gerizekali\",\"gerzek\",\"giberim\",\"giberler\",\"gibis\",\"gibiş\",\"gibmek\",\"gibtiler\",\"goddamn\",\"godoş\",\"godumun\",\"gotelek\",\"gotlalesi\",\"gotlu\",\"gotten\",\"gotundeki\",\"gotunden\",\"gotune\",\"gotunu\",\"gotveren\",\"goyiim\",\"goyum\",\"goyuyim\",\"goyyim\",\"göt\",\"göt deliği\",\"götelek\",\"göt herif\",\"götlalesi\",\"götlek\",\"götoğlanı\",\"göt oğlanı\",\"götoş\",\"götten\",\"götü\",\"götün\",\"götüne\",\"götünekoyim\",\"götüne koyim\",\"götünü\",\"götveren\",\"göt veren\",\"göt verir\",\"gtelek\",\"gtn\",\"gtnde\",\"gtnden\",\"gtne\",\"gtten\",\"gtveren\",\"hasiktir\",\"hassikome\",\"hassiktir\",\"has siktir\",\"hassittir\",\"haysiyetsiz\",\"hayvan herif\",\"hoşafı\",\"hödük\",\"hsktr\",\"huur\",\"ıbnelık\",\"ibina\",\"ibine\",\"ibinenin\",\"ibne\",\"ibnedir\",\"ibneleri\",\"ibnelik\",\"ibnelri\",\"ibneni\",\"ibnenin\",\"ibnerator\",\"ibnesi\",\"idiot\",\"idiyot\",\"imansz\",\"ipne\",\"iserim\",\"işerim\",\"itoğlu it\",\"kafam girsin\",\"kafasız\",\"kafasiz\",\"kahpe\",\"kahpenin\",\"kahpenin feryadı\",\"kaka\",\"kaltak\",\"kancık\",\"kancik\",\"kappe\",\"karhane\",\"kaşar\",\"kavat\",\"kavatn\",\"kaypak\",\"kayyum\",\"kerane\",\"kerhane\",\"kerhanelerde\",\"kevase\",\"kevaşe\",\"kevvase\",\"koca göt\",\"koduğmun\",\"koduğmunun\",\"kodumun\",\"kodumunun\",\"koduumun\",\"koyarm\",\"koyayım\",\"koyiim\",\"koyiiym\",\"koyim\",\"koyum\",\"koyyim\",\"krar\",\"kukudaym\",\"laciye boyadım\",\"lavuk\",\"liboş\",\"madafaka\",\"mal\",\"malafat\",\"malak\",\"manyak\",\"mcik\",\"meme\",\"memelerini\",\"mezveleli\",\"minaamcık\",\"mincikliyim\",\"mna\",\"monakkoluyum\",\"motherfucker\",\"mudik\",\"oc\",\"ocuu\",\"ocuun\",\"OÇ\",\"oç\",\"o. çocuğu\",\"oğlan\",\"oğlancı\",\"oğlu it\",\"orosbucocuu\",\"orospu\",\"orospucocugu\",\"orospu cocugu\",\"orospu çoc\",\"orospuçocuğu\",\"orospu çocuğu\",\"orospu çocuğudur\",\"orospu çocukları\",\"orospudur\",\"orospular\",\"orospunun\",\"orospunun evladı\",\"orospuydu\",\"orospuyuz\",\"orostoban\",\"orostopol\",\"orrospu\",\"oruspu\",\"oruspuçocuğu\",\"oruspu çocuğu\",\"osbir\",\"ossurduum\",\"ossurmak\",\"ossuruk\",\"osur\",\"osurduu\",\"osuruk\",\"osururum\",\"otuzbir\",\"öküz\",\"öşex\",\"patlak zar\",\"penis\",\"pezevek\",\"pezeven\",\"pezeveng\",\"pezevengi\",\"pezevengin evladı\",\"pezevenk\",\"pezo\",\"pic\",\"pici\",\"picler\",\"piç\",\"piçin oğlu\",\"piç kurusu\",\"piçler\",\"pipi\",\"pipiş\",\"pisliktir\",\"porno\",\"pussy\",\"puşt\",\"puşttur\",\"rahminde\",\"revizyonist\",\"s1kerim\",\"s1kerm\",\"s1krm\",\"sakso\",\"saksofon\",\"salaak\",\"salak\",\"saxo\",\"sekis\",\"serefsiz\",\"sevgi koyarım\",\"sevişelim\",\"sexs\",\"sıçarım\",\"sıçtığım\",\"sıecem\",\"sicarsin\",\"sie\",\"sik\",\"sikdi\",\"sikdiğim\",\"sike\",\"sikecem\",\"sikem\",\"siken\",\"sikenin\",\"siker\",\"sikerim\",\"sikerler\",\"sikersin\",\"sikertir\",\"sikertmek\",\"sikesen\",\"sikesicenin\",\"sikey\",\"sikeydim\",\"sikeyim\",\"sikeym\",\"siki\",\"sikicem\",\"sikici\",\"sikien\",\"sikienler\",\"sikiiim\",\"sikiiimmm\",\"sikiim\",\"sikiir\",\"sikiirken\",\"sikik\",\"sikil\",\"sikildiini\",\"sikilesice\",\"sikilmi\",\"sikilmie\",\"sikilmis\",\"sikilmiş\",\"sikilsin\",\"sikim\",\"sikimde\",\"sikimden\",\"sikime\",\"sikimi\",\"sikimiin\",\"sikimin\",\"sikimle\",\"sikimsonik\",\"sikimtrak\",\"sikin\",\"sikinde\",\"sikinden\",\"sikine\",\"sikini\",\"sikip\",\"sikis\",\"sikisek\",\"sikisen\",\"sikish\",\"sikismis\",\"sikiş\",\"sikişen\",\"sikişme\",\"sikitiin\",\"sikiyim\",\"sikiym\",\"sikiyorum\",\"sikkim\",\"sikko\",\"sikleri\",\"sikleriii\",\"sikli\",\"sikm\",\"sikmek\",\"sikmem\",\"sikmiler\",\"sikmisligim\",\"siksem\",\"sikseydin\",\"sikseyidin\",\"siksin\",\"siksinbaya\",\"siksinler\",\"siksiz\",\"siksok\",\"siksz\",\"sikt\",\"sikti\",\"siktigimin\",\"siktigiminin\",\"siktiğim\",\"siktiğimin\",\"siktiğiminin\",\"siktii\",\"siktiim\",\"siktiimin\",\"siktiiminin\",\"siktiler\",\"siktim\",\"siktim\",\"siktimin\",\"siktiminin\",\"siktir\",\"siktir et\",\"siktirgit\",\"siktir git\",\"siktirir\",\"siktiririm\",\"siktiriyor\",\"siktir lan\",\"siktirolgit\",\"siktir ol git\",\"sittimin\",\"sittir\",\"skcem\",\"skecem\",\"skem\",\"sker\",\"skerim\",\"skerm\",\"skeyim\",\"skiim\",\"skik\",\"skim\",\"skime\",\"skmek\",\"sksin\",\"sksn\",\"sksz\",\"sktiimin\",\"sktrr\",\"skyim\",\"slaleni\",\"sokam\",\"sokarım\",\"sokarim\",\"sokarm\",\"sokarmkoduumun\",\"sokayım\",\"sokaym\",\"sokiim\",\"soktuğumunun\",\"sokuk\",\"sokum\",\"sokuş\",\"sokuyum\",\"soxum\",\"sulaleni\",\"sülaleni\",\"sülalenizi\",\"sürtük\",\"şerefsiz\",\"şıllık\",\"taaklarn\",\"taaklarna\",\"tarrakimin\",\"tasak\",\"tassak\",\"taşak\",\"taşşak\",\"tipini s.k\",\"tipinizi s.keyim\",\"tiyniyat\",\"toplarm\",\"topsun\",\"totoş\",\"vajina\",\"vajinanı\",\"veled\",\"veledizina\",\"veled i zina\",\"verdiimin\",\"weled\",\"weledizina\",\"whore\",\"xikeyim\",\"yaaraaa\",\"yalama\",\"yalarım\",\"yalarun\",\"yaraaam\",\"yarak\",\"yaraksız\",\"yaraktr\",\"yaram\",\"yaraminbasi\",\"yaramn\",\"yararmorospunun\",\"yarra\",\"yarraaaa\",\"yarraak\",\"yarraam\",\"yarraamı\",\"yarragi\",\"yarragimi\",\"yarragina\",\"yarragindan\",\"yarragm\",\"yarrağ\",\"yarrağım\",\"yarrağımı\",\"yarraimin\",\"yarrak\",\"yarram\",\"yarramin\",\"yarraminbaşı\",\"yarramn\",\"yarran\",\"yarrana\",\"yarrrak\",\"yavak\",\"yavş\",\"yavşak\",\"yavşaktır\",\"yavuşak\",\"yılışık\",\"yilisik\",\"yogurtlayam\",\"yoğurtlayam\",\"yrrak\",\"zıkkımım\",\"zibidi\",\"zigsin\",\"zikeyim\",\"zikiiim\",\"zikiim\",\"zikik\",\"zikim\",\"ziksiiin\",\"ziksiin\",\"zulliyetini\",\"zviyetini\"]}"
  },
  {
    "id": 35,
    "option": "elfinderConvertWebp",
    "content": "1"
  }
]', true));
        $commonModel->create(getenv('database.default.DBPrefix').'pages',[
            'title' => 'Hakkımızda',
            'content' => '<!-- About section one-->
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
            </section>',
            'seflink' => 'hakkimizda',
            'creationDate' => date('Y-m-d H:i:s'),
            'isActive' => 1,
            'seo' => '{"coverImage":"https://kun-cms/uploads/media/main-vector.png","IMGWidth":"398","IMGHeight":"249","description":"Ci4MS hakkında","keywords":[{"value":"hakkımızda"}]}',
            'inMenu' => 1
        ]);
        $commonModel->create(getenv('database.default.DBPrefix').'pages',[
                "id" => 2,
                "title" => "İletişim",
                "content" => '<section class="py-5">
                <div class="container px-5">
{\App\Libraries\templates\default\Ci4mstemplateLib|contactForm/}
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
                            <p class="text-muted mb-0">Browse FAQs and support articles to find solutions.</p>
                        </div>
                        <div class="col">
                            <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-telephone"></i></div>
                            <div class="h5">Call us</div>
                            <p class="text-muted mb-0">Call us during normal business hours at (555) 892-9403.</p>
                        </div>
                    </div>
                </div>
{\App\Libraries\templates\default\Ci4mstemplateLib|gmapiframe/}
            </section>',
                "seflink" => "iletisim",
                'creationDate' => date('Y-m-d H:i:s'),
                'isActive' => 1,
                "seo"=>'{"description":"Ci4MS iletişim"}',
                'inMenu' => 1]);
        $commonModel->create(getenv('database.default.DBPrefix').'pages',["id" => 3,
                "title" => "Anasayfa",
                "content" => '<!-- Header-->
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
</div>',
                "seflink" => "/",
                'creationDate' => date('Y-m-d H:i:s'),
                'isActive' => 1,
                "seo" => '{"description":"Ci4MS anasayfa","keywords":[{"value":"anasayfa"}]}',
                'inMenu' => 1]);
        $commonModel->createMany(getenv('database.default.DBPrefix') . 'menu', json_decode('[
  {
    "id": 1,
    "pages_id": 3,
    "parent": null,
    "queue": 1,
    "urlType": "pages",
    "title": "Anasayfa",
    "seflink": "/",
    "target": null,
    "hasChildren": 0
  },
  {
    "id": 2,
    "pages_id": 1,
    "parent": null,
    "queue": 2,
    "urlType": "pages",
    "title": "Hakkımızda",
    "seflink": "hakkimizda",
    "target": null,
    "hasChildren": 0
  },
  {
    "id": 3,
    "pages_id": 2,
    "parent": null,
    "queue": 4,
    "urlType": "pages",
    "title": "İletişim",
    "seflink": "iletisim",
    "target": null,
    "hasChildren": 0
  },
  {
    "id": 4,
    "pages_id": null,
    "parent": null,
    "queue": 3,
    "urlType": "url",
    "title": "Blog",
    "seflink": "/blog/1",
    "target": null,
    "hasChildren": 0
  }
]', true));
    }
}
