<?php

namespace Modules\Backend\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddForeignKeys extends Migration
{
    public function up()
    {
        $fks = [
            'ALTER TABLE `auth_groups` ADD CONSTRAINT `ci4ms_auth_groups_ibfk_1` FOREIGN KEY (`who_created`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            'ALTER TABLE `auth_groups_users` ADD CONSTRAINT `ci4ms_auth_groups_users_ibfk_1` FOREIGN KEY (`who_created`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            'ALTER TABLE `auth_identities` ADD CONSTRAINT `ci4ms_auth_identities_ibfk_1` FOREIGN KEY (`who_banned`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            'ALTER TABLE `auth_permissions_pages` ADD CONSTRAINT `ci4ms_auth_permissions_pages_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `auth_permissions_users` ADD CONSTRAINT `ci4ms_auth_permissions_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `auth_remember_tokens` ADD CONSTRAINT `ci4ms_auth_remember_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `blog` ADD CONSTRAINT `blog_users_id_fk` FOREIGN KEY (`author`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `blog_categories_pivot` ADD CONSTRAINT `ci4ms_blog_categories_pivot_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `categories` ADD CONSTRAINT `ci4ms_categories_parent_foreign` FOREIGN KEY (`parent`) REFERENCES `categories`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            'ALTER TABLE `comments` ADD CONSTRAINT `ci4ms_comments_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `db_backups` ADD CONSTRAINT `ci4ms_db_backups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE SET NULL',
            'ALTER TABLE `menu` ADD CONSTRAINT `ci4ms_menu_ibfk_2` FOREIGN KEY (`parent`) REFERENCES `menu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `tags_pivot` ADD CONSTRAINT `ci4ms_tags_pivot_piv_id_foreign` FOREIGN KEY (`piv_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            'ALTER TABLE `users` ADD CONSTRAINT `ci4ms_users_ibfk_1` FOREIGN KEY (`who_created`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE',
        ];

        foreach ($fks as $sql) {
            try {
                $this->db->query($sql);
            } catch (\Exception $e) {
                // FK already exists — skip silently
            }
        }
    }

    public function down()
    {
        $dropFks = [
            ['auth_groups', 'ci4ms_auth_groups_ibfk_1'],
            ['auth_groups_users', 'ci4ms_auth_groups_users_ibfk_1'],
            ['auth_identities', 'ci4ms_auth_identities_ibfk_1'],
            ['auth_permissions_pages', 'ci4ms_auth_permissions_pages_module_id_foreign'],
            ['auth_permissions_users', 'ci4ms_auth_permissions_users_user_id_foreign'],
            ['auth_remember_tokens', 'ci4ms_auth_remember_tokens_user_id_foreign'],
            ['blog', 'blog_users_id_fk'],
            ['blog_categories_pivot', 'ci4ms_blog_categories_pivot_blog_id_foreign'],
            ['categories', 'ci4ms_categories_parent_foreign'],
            ['comments', 'ci4ms_comments_blog_id_foreign'],
            ['db_backups', 'ci4ms_db_backups_created_by_foreign'],
            ['menu', 'ci4ms_menu_ibfk_2'],
            ['tags_pivot', 'ci4ms_tags_pivot_piv_id_foreign'],
            ['users', 'ci4ms_users_ibfk_1'],
        ];

        foreach ($dropFks as [$table, $fk]) {
            try {
                $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
            } catch (\Exception $e) {
                // FK doesn't exist — skip silently
            }
        }
    }
}
