<?php

declare(strict_types=1);

namespace Modules\LanguageManager\Libraries;

use ci4commonmodel\CommonModel;

class TranslationService
{
    protected CommonModel $commonModel;

    public function __construct()
    {
        $this->commonModel = new CommonModel();
    }

    /**
     * Get all active languages ordered by sort_order.
     *
     * @return array
     */
    public function getActiveLanguages(): array
    {
        return $this->commonModel->lists('languages', '*', ['is_active' => 1], 'sort_order ASC');
    }

    /**
     * Get all translation keys with their translations for a specific group.
     *
     * @param string      $group
     * @param string|null $search
     * @param int         $page
     * @param int         $perPage
     * @return array
     */
    public function getTranslations(string $group, ?string $search = null, int $page = 1, int $perPage = 50): array
    {
        $db = db_connect();
        $prefix = $db->DBPrefix;

        $builder = $db->table("{$prefix}translation_keys k");
        $builder->where('k.group_name', $group);
        $builder->where('k.key_name !=', '___initial_sync___');

        if (!empty($search)) {
            $builder->groupStart()
                ->like('k.key_name', $search)
                ->orWhereIn('k.id', function ($sub) use ($prefix, $search) {
                    return $sub->select('key_id')->from("{$prefix}translations")->like('value', $search);
                })
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);
        $keys = $builder->orderBy('k.key_name', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResult();

        // Fetch translations for these keys
        $keyIds = array_column($keys, 'id');
        $translations = [];
        if (!empty($keyIds)) {
            $rows = $db->table("{$prefix}translations")
                ->whereIn('key_id', $keyIds)
                ->get()->getResult();
            foreach ($rows as $r) {
                $translations[$r->key_id][$r->language_code] = $r->value;
            }
        }

        return [
            'keys'         => $keys,
            'translations' => $translations,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get unique groups from database.
     *
     * @return array
     */
    public function getGroups(): array
    {
        $db = db_connect();
        $groups = $db->table($db->prefixTable('translation_keys'))
            ->select('group_name')
            ->distinct()
            ->orderBy('group_name')
            ->get()->getResult();

        // If no groups in DB, try to scan system for initial groups
        if (empty($groups)) {
            $this->scanGroups();
            $groups = $db->table($db->prefixTable('translation_keys'))
                ->select('group_name')
                ->distinct()
                ->orderBy('group_name')
                ->get()->getResult();
        }

        return $groups;
    }

    /**
     * Scan Language directories and sync with database to avoid empty groups.
     *
     * @return void
     */
    public function scanGroups(): void
    {
        $locales = ['tr', 'en']; // Basic locales to scan
        $paths = [
            APPPATH . 'Language',
        ];

        // Also scan modules
        if (is_dir(ROOTPATH . 'modules')) {
            $modules = array_filter(glob(ROOTPATH . 'modules/*'), 'is_dir');
            foreach ($modules as $module) {
                if (is_dir($module . '/Language')) {
                    $paths[] = $module . '/Language';
                }
            }
        }

        foreach ($paths as $path) {
            foreach ($locales as $locale) {
                $localePath = $path . DIRECTORY_SEPARATOR . $locale;
                if (!is_dir($localePath)) continue;

                $files = glob($localePath . DIRECTORY_SEPARATOR . '*.php');
                foreach ($files as $file) {
                    $groupName = basename($file, '.php');
                    // Add grouping to DB if not exists
                    $this->addKey($groupName, '___initial_sync___');
                }
            }
        }
    }

    /**
     * Save a single translation value.
     *
     * @param int    $keyId
     * @param string $langCode
     * @param string $value
     * @return void
     */
    public function saveTranslation(int $keyId, string $langCode, string $value): void
    {
        $db = db_connect();
        $table = $db->prefixTable('translations');

        $existing = $db->table($table)
            ->where(['key_id' => $keyId, 'language_code' => $langCode])
            ->get()->getRow();

        if ($existing) {
            $db->table($table)->where('id', $existing->id)->update(['value' => $value]);
        } else {
            $db->table($table)->insert([
                'key_id'        => $keyId,
                'language_code' => $langCode,
                'value'         => $value,
            ]);
        }
    }

    /**
     * Add a new translation key.
     *
     * @param string $group
     * @param string $keyName
     * @return int|null
     */
    public function addKey(string $group, string $keyName): ?int
    {
        $db = db_connect();
        $existing = $db->table($db->prefixTable('translation_keys'))
            ->where(['group_name' => $group, 'key_name' => $keyName])
            ->get()->getRow();
        if ($existing) return (int) $existing->id;

        return (int) $this->commonModel->create('translation_keys', [
            'group_name' => $group,
            'key_name'   => $keyName,
        ]);
    }

    /**
     * Delete a key and all its translations (cascade).
     *
     * @param int $keyId
     * @return bool
     */
    public function deleteKey(int $keyId): bool
    {
        return (bool) $this->commonModel->remove('translation_keys', ['id' => $keyId]);
    }

    /**
     * Export translations for a language as JSON.
     *
     * @param string $langCode
     * @return array
     */
    public function export(string $langCode): array
    {
        $db = db_connect();
        $prefix = $db->DBPrefix;

        $rows = $db->query("
            SELECT k.group_name, k.key_name, t.value
            FROM {$prefix}translation_keys k
            LEFT JOIN {$prefix}translations t ON k.id = t.key_id AND t.language_code = ?
            WHERE k.key_name != '___initial_sync___'
            ORDER BY k.group_name, k.key_name
        ", [$langCode])->getResult();

        $result = [];
        foreach ($rows as $r) {
            $result[$r->group_name][$r->key_name] = $r->value ?? '';
        }
        return $result;
    }

    /**
     * Import translations from JSON data.
     *
     * @param string $langCode
     * @param array  $data
     * @return int
     */
    public function import(string $langCode, array $data): int
    {
        $count = 0;
        foreach ($data as $group => $keys) {
            if (!is_array($keys)) continue;
            foreach ($keys as $keyName => $value) {
                if ($keyName === '___initial_sync___') continue;
                $keyId = $this->addKey($group, $keyName);
                if ($keyId) {
                    $this->saveTranslation($keyId, $langCode, (string) $value);
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Set a language as default (only one can be default).
     *
     * @param int $id
     * @return void
     */
    public function setDefault(int $id): void
    {
        $db = db_connect();
        $db->table($db->prefixTable('languages'))->update(['is_default' => 0]);
        $db->table($db->prefixTable('languages'))->where('id', $id)->update(['is_default' => 1]);
        cache()->delete('default_frontend_language');
    }

    /**
     * Get all active frontend languages.
     *
     * @return array
     */
    public function getFrontendLanguages(): array
    {
        return $this->commonModel->lists('languages', '*', [
            'is_active'   => 1,
            'is_frontend' => 1,
        ], 'sort_order ASC');
    }
}
