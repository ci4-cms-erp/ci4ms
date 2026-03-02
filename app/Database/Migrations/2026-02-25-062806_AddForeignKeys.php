<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class AddForeignKeys extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `auth_groups` ADD CONSTRAINT `ci4ms_auth_groups_ibfk_1` FOREIGN KEY (`who_created`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `auth_groups_users` ADD CONSTRAINT `ci4ms_auth_groups_users_ibfk_1` FOREIGN KEY (`who_created`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `auth_identities` ADD CONSTRAINT `ci4ms_auth_identities_ibfk_1` FOREIGN KEY (`who_banned`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `auth_permissions_pages` ADD CONSTRAINT `ci4ms_auth_permissions_pages_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `auth_permissions_users` ADD CONSTRAINT `ci4ms_auth_permissions_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `auth_remember_tokens` ADD CONSTRAINT `ci4ms_auth_remember_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `blog` ADD CONSTRAINT `blog_users_id_fk` FOREIGN KEY (`author`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `blog_categories_pivot` ADD CONSTRAINT `ci4ms_blog_categories_pivot_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `categories` ADD CONSTRAINT `ci4ms_categories_parent_foreign` FOREIGN KEY (`parent`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `comments` ADD CONSTRAINT `ci4ms_comments_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `db_backups` ADD CONSTRAINT `ci4ms_db_backups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE SET NULL');
        $this->db->query('ALTER TABLE `menu` ADD CONSTRAINT `ci4ms_menu_ibfk_2` FOREIGN KEY (`parent`) REFERENCES `menu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `tags_pivot` ADD CONSTRAINT `ci4ms_tags_pivot_piv_id_foreign` FOREIGN KEY (`piv_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `users` ADD CONSTRAINT `ci4ms_users_ibfk_1` FOREIGN KEY (`who_created`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `auth_groups` DROP FOREIGN KEY `ci4ms_auth_groups_ibfk_1`');
        $this->forge->dropForeignKey('auth_groups', 'ci4ms_auth_groups_ibfk_1');
        $this->db->query('ALTER TABLE `auth_groups_users` DROP FOREIGN KEY `ci4ms_auth_groups_users_ibfk_1`');
        $this->forge->dropForeignKey('auth_groups_users', 'ci4ms_auth_groups_users_ibfk_1');
        $this->db->query('ALTER TABLE `auth_identities` DROP FOREIGN KEY `ci4ms_auth_identities_ibfk_1`');
        $this->forge->dropForeignKey('auth_identities', 'ci4ms_auth_identities_ibfk_1');
        $this->db->query('ALTER TABLE `auth_permissions_pages` DROP FOREIGN KEY `ci4ms_auth_permissions_pages_module_id_foreign`');
        $this->forge->dropForeignKey('auth_permissions_pages', 'ci4ms_auth_permissions_pages_module_id_foreign');
        $this->db->query('ALTER TABLE `auth_permissions_users` DROP FOREIGN KEY `ci4ms_auth_permissions_users_user_id_foreign`');
        $this->forge->dropForeignKey('auth_permissions_users', 'ci4ms_auth_permissions_users_user_id_foreign');
        $this->db->query('ALTER TABLE `auth_remember_tokens` DROP FOREIGN KEY `ci4ms_auth_remember_tokens_user_id_foreign`');
        $this->forge->dropForeignKey('auth_remember_tokens', 'ci4ms_auth_remember_tokens_user_id_foreign');
        $this->db->query('ALTER TABLE `blog` DROP FOREIGN KEY `blog_users_id_fk`');
        $this->forge->dropForeignKey('blog', 'blog_users_id_fk');
        $this->db->query('ALTER TABLE `blog_categories_pivot` DROP FOREIGN KEY `ci4ms_blog_categories_pivot_blog_id_foreign`');
        $this->forge->dropForeignKey('blog_categories_pivot', 'ci4ms_blog_categories_pivot_blog_id_foreign');
        $this->db->query('ALTER TABLE `categories` DROP FOREIGN KEY `ci4ms_categories_parent_foreign`');
        $this->forge->dropForeignKey('categories', 'ci4ms_categories_parent_foreign');
        $this->db->query('ALTER TABLE `comments` DROP FOREIGN KEY `ci4ms_comments_blog_id_foreign`');
        $this->forge->dropForeignKey('comments', 'ci4ms_comments_blog_id_foreign');
        $this->db->query('ALTER TABLE `db_backups` DROP FOREIGN KEY `ci4ms_db_backups_created_by_foreign`');
        $this->forge->dropForeignKey('db_backups', 'ci4ms_db_backups_created_by_foreign');
        $this->db->query('ALTER TABLE `menu` DROP FOREIGN KEY `ci4ms_menu_ibfk_2`');
        $this->forge->dropForeignKey('menu', 'ci4ms_menu_ibfk_2');
        $this->db->query('ALTER TABLE `tags_pivot` DROP FOREIGN KEY `ci4ms_tags_pivot_piv_id_foreign`');
        $this->forge->dropForeignKey('tags_pivot', 'ci4ms_tags_pivot_piv_id_foreign');
        $this->db->query('ALTER TABLE `users` DROP FOREIGN KEY `ci4ms_users_ibfk_1`');
        $this->forge->dropForeignKey('users', 'ci4ms_users_ibfk_1');
    }
}
