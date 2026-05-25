<?php

namespace Modules\Settings\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sanitize App.templateInfos.path values stored in the settings table.
 *
 * Older releases accepted arbitrary strings for the active theme slug,
 * including path-traversal payloads. Such values flow into require() in
 * app/Config/Routes.php, dynamic class instantiation in app/Config/Filters.php,
 * and view() in modules/Settings/Controllers/Settings.php — making any
 * non-slug value an RCE / LFI primitive.
 *
 * This migration rewrites any persisted path that is not a [a-z0-9_-]+ slug
 * (or doesn't resolve to an installed theme directory) back to 'default'.
 */
class SanitizeTemplateInfosPath extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('settings')) {
            return;
        }

        $row = $this->db->table('settings')
            ->where('class', 'Config\\App')
            ->where('key', 'templateInfos')
            ->get()
            ->getRow();

        if (!$row || empty($row->value)) {
            return;
        }

        $data = json_decode($row->value, true);
        if (!is_array($data)) {
            return;
        }

        $currentPath = $data['path'] ?? null;
        if (function_exists('resolve_template_path')
            && resolve_template_path(APPPATH . 'Config/templates/', $currentPath) !== null) {
            return;
        }

        $data['path'] = 'default';
        if (!isset($data['name']) || $data['name'] === null) {
            $data['name'] = 'default';
        }

        $this->db->table('settings')
            ->where('class', 'Config\\App')
            ->where('key', 'templateInfos')
            ->update(['value' => json_encode($data, JSON_UNESCAPED_UNICODE)]);

        if (function_exists('cache')) {
            cache()->delete('settings');
        }

        log_message('warning', 'SanitizeTemplateInfosPath: reset App.templateInfos.path from '
            . var_export($currentPath, true) . " to 'default'.");
    }

    public function down()
    {
        // No-op: this migration only sanitizes existing bad data; reverting would re-introduce the vulnerability.
    }
}
