<?php

namespace Modules\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a composite index on
 * auth_permissions_pages(inNavigation, isBackoffice, isActive, pageSort) --
 * the exact WHERE + ORDER BY of BaseController::generateSidebar()
 * (modules/Backend/Controllers/BaseController.php:104-106). That result is
 * cached for 24h, so the index only helps on a cache miss (right after a
 * permission/menu change or `php spark cache:clear`); before this the table
 * carried only its primary key. Purely additive -- no column or row is
 * touched, so it is always safe to apply and to reverse.
 *
 * Raw ALTER guarded against getIndexData() (rather than Forge::addKey) so the
 * add/drop are idempotent, matching this module's other migrations.
 */
class AddSidebarIndexToAuthPermissionsPages extends Migration
{
    private const TABLE = 'auth_permissions_pages';
    private const INDEX = 'idx_perm_pages_sidebar';

    public function up()
    {
        $this->db->resetDataCache();

        if ($this->indexExists()) {
            return;
        }

        $prefixed = $this->db->prefixTable(self::TABLE);
        $this->db->query(
            "ALTER TABLE `{$prefixed}` "
            . 'ADD INDEX `' . self::INDEX . '` (`inNavigation`, `isBackoffice`, `isActive`, `pageSort`)',
        );

        $this->db->resetDataCache();
    }

    public function down()
    {
        $this->db->resetDataCache();

        if (! $this->indexExists()) {
            return;
        }

        $prefixed = $this->db->prefixTable(self::TABLE);
        $this->db->query("ALTER TABLE `{$prefixed}` DROP INDEX `" . self::INDEX . '`');

        $this->db->resetDataCache();
    }

    private function indexExists(): bool
    {
        foreach ($this->db->getIndexData(self::TABLE) as $index) {
            if ($index->name === self::INDEX) {
                return true;
            }
        }

        return false;
    }
}
