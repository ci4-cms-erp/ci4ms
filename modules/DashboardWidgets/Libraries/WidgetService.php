<?php

namespace Modules\DashboardWidgets\Libraries;

use ci4commonmodel\CommonModel;

/**
 * WidgetService — provides built-in widget data + manages widget layout preferences.
 *
 * Optimised version: batch COUNT query, CI4 cache layer, batch upsert for layout.
 */
class WidgetService
{
    protected CommonModel $commonModel;

    /** @var int Cache Time-To-Live in seconds (default 5 minutes). */
    protected int $cacheTtl = 300;

    public function __construct()
    {
        $this->commonModel = new CommonModel();
    }

    // ══════════════════════════════════════════════════
    //  PUBLIC — Main entry point for dashboard
    // ══════════════════════════════════════════════════

    /**
     * Return all visible widgets with their data, using cache.
     * This is the **only** method the controller should call.
     *
     * @return array<object>
     */
    public function getUserWidgetsWithData(int $userId): array
    {
        $cache    = \Config\Services::cache();
        $cacheKey = "dashboard_widgets_user_{$userId}";

        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $widgets    = $this->getUserWidgets($userId);
        $statCounts = $this->getAllStatCounts($userId); // 1 query for all stat counts

        $result = [];
        foreach ($widgets as $w) {
            if (!$w->visible) {
                continue;
            }

            if ($w->type === 'stat' && isset($statCounts[$w->slug])) {
                $w->data = ['value' => $statCounts[$w->slug], 'label' => $w->title];
            } else {
                // table / chart / html — individual query (only 2-3 widgets)
                $w->data = $this->getWidgetData($w->slug);
            }
            $result[] = $w;
        }

        $cache->save($cacheKey, $result, $this->cacheTtl);

        return $result;
    }

    // ══════════════════════════════════════════════════
    //  Widget list query (with user preferences)
    // ══════════════════════════════════════════════════

    /**
     * Get all active widgets with user preferences overlaid.
     *
     * @return array<object>
     */
    public function getUserWidgets(int $userId): array
    {
        $widgets = $this->commonModel->lists('dashboard_widgets', 'dashboard_widgets.*,
        COALESCE(' . getenv('database.default.DBPrefix') . 'user_widget_preferences.position, ' . getenv('database.default.DBPrefix') . 'dashboard_widgets.id) as pos,
        COALESCE(' . getenv('database.default.DBPrefix') . 'user_widget_preferences.size, ' . getenv('database.default.DBPrefix') . 'dashboard_widgets.default_size) as display_size,
        COALESCE(' . getenv('database.default.DBPrefix') . 'user_widget_preferences.is_visible, 1) as visible,
        ' . getenv('database.default.DBPrefix') . 'user_widget_preferences.config_json', ['dashboard_widgets.is_active' => 1], 'pos ASC', 0, 0, [], [], [
            ['table' => 'user_widget_preferences', 'cond' => 'user_widget_preferences.widget_id=dashboard_widgets.id AND user_widget_preferences.user_id=' . $userId, 'type' => 'left']
        ]);

        $user = auth()->getProvider()->findById($userId);
        $userGroups = $user ? $user->getGroups() : [];
        $isSuperAdmin = $user ? $user->inGroup('superadmin') : false;

        $filtered = [];
        foreach ($widgets as $w) {
            $allowed = json_decode($w->allowed_groups ?? '[]', true);
            if (empty($allowed) || $isSuperAdmin || array_intersect($userGroups, $allowed)) {
                $filtered[] = $w;
            }
        }
        return $filtered;
    }

    /**
     * Return list of all active widgets with the user's visibility flag.
     * Used by the "Add Widget" modal so the user can toggle widgets on/off.
     *
     * @return array<object>
     */
    public function getAvailableWidgets(int $userId): array
    {
        $widgets = $this->commonModel->lists(
            'dashboard_widgets',
            'dashboard_widgets.id, dashboard_widgets.slug, dashboard_widgets.title, dashboard_widgets.icon, dashboard_widgets.color, dashboard_widgets.type, dashboard_widgets.default_size, dashboard_widgets.allowed_groups, COALESCE(user_widget_preferences.is_visible, 1) as visible',
            ['dashboard_widgets.is_active' => 1],
            'dashboard_widgets.title ASC',
            0,
            0,
            [],
            [],
            [
                ['table' => 'user_widget_preferences', 'cond' => 'user_widget_preferences.widget_id=dashboard_widgets.id AND user_widget_preferences.user_id=' . $userId, 'type' => 'left']
            ]
        );

        $user = auth()->getProvider()->findById($userId);
        $userGroups = $user ? $user->getGroups() : [];
        $isSuperAdmin = $user ? $user->inGroup('superadmin') : false;

        $filtered = [];
        foreach ($widgets as $w) {
            $allowed = json_decode($w->allowed_groups ?? '[]', true);
            if (empty($allowed) || $isSuperAdmin || array_intersect($userGroups, $allowed)) {
                $filtered[] = $w;
            }
        }
        return $filtered;
    }

    // ══════════════════════════════════════════════════
    //  Batch stat COUNT — single query for all stats
    // ══════════════════════════════════════════════════

    /**
     * Fetch all stat-type counters in a single query.
     *
     * @return array<string, int>  slug => count
     */
    protected function getAllStatCounts(int $userId): array
    {
        $select = '(SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'users) AS total_users,
                    (SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'pages) AS total_pages,
                    (SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'blog)  AS total_blogs,
                    (SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'comments) AS total_comments';
        if ($this->commonModel->db->tableExists('cronjobs')) {
            $select .= ',(SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'cronjobs WHERE is_active = 1) AS active_cronjobs,
        (SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'cronjobs WHERE last_status = \'failed\') AS failed_cronjobs';
        }
        if ($this->commonModel->db->tableExists('activity_logs')) {
            $select .= ',(SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'activity_logs WHERE DATE(created_at) = CURDATE()) AS today_logs';
        }
        if ($this->commonModel->db->tableExists('notifications')) {
            $select .= ',(SELECT COUNT(*) FROM ' . getenv('database.default.DBPrefix') . 'notifications WHERE user_id = ' . $userId . ' AND is_read = 0) AS unread_notifs';
        }
        $row = $this->commonModel->lists('users', $select, [], 'id ASC', 0, 0, [], [], [], ['isReset' => true]);

        if (!$row) {
            return [];
        }
        $return = [
            'total-users'     => (int) $row->total_users,
            'total-pages'     => (int) $row->total_pages,
            'total-blogs'     => (int) $row->total_blogs,
            'total-comments'  => (int) $row->total_comments
        ];
        if ($this->commonModel->db->tableExists('cronjobs')) {
            $return['active-cronjobs'] = (int) $row->active_cronjobs;
            $return['failed-cronjobs'] = (int) $row->failed_cronjobs;
        }
        if ($this->commonModel->db->tableExists('activity_logs')) {
            $return['today-logs'] = (int) $row->today_logs;
        }
        if ($this->commonModel->db->tableExists('notifications')) {
            $return['unread-notifs'] = (int) $row->unread_notifs;
        }

        return $return;
    }

    // ══════════════════════════════════════════════════
    //  Layout persistence (batch upsert)
    // ══════════════════════════════════════════════════

    /**
     * Save user layout preferences (positions & sizes from drag-drop).
     * Uses CommonModel upsert pattern — preserves is_visible for hidden widgets.
     */
    public function saveLayout(int $userId, array $layout): void
    {
        foreach ($layout as $item) {
            $widgetId = (int) ($item['widget_id'] ?? 0);
            if ($widgetId <= 0) {
                continue;
            }

            $existing = $this->commonModel->selectOne('user_widget_preferences', [
                'user_id'   => $userId,
                'widget_id' => $widgetId,
            ]);

            $posData = [
                'position' => (int) ($item['position'] ?? 0),
                'size'     => $item['size'] ?? 'col-lg-3',
            ];

            if ($existing) {
                // Only update position & size — keep is_visible intact
                $this->commonModel->edit('user_widget_preferences', $posData, ['id' => $existing->id]);
            } else {
                $posData['user_id']    = $userId;
                $posData['widget_id']  = $widgetId;
                $posData['is_visible'] = 1;
                $this->commonModel->create('user_widget_preferences', $posData);
            }
        }

        $this->clearDashboardCache($userId);
    }

    /**
     * Toggle a single widget's visibility for a user.
     */
    public function toggleWidgetVisibility(int $userId, int $widgetId): bool
    {
        $existing = $this->commonModel->selectOne('user_widget_preferences', [
            'user_id'   => $userId,
            'widget_id' => $widgetId,
        ]);

        if ($existing) {
            $newVisible = $existing->is_visible ? 0 : 1;
            $this->commonModel->edit('user_widget_preferences', [
                'is_visible' => $newVisible,
            ], ['id' => $existing->id]);
        } else {
            // First time — default is visible, so toggling makes it hidden
            $this->commonModel->create('user_widget_preferences', [
                'user_id'    => $userId,
                'widget_id'  => $widgetId,
                'is_visible' => 0,
                'position'   => 999,
                'size'       => 'col-lg-3',
            ]);
            $newVisible = 0;
        }

        $this->clearDashboardCache($userId);

        return (bool) $newVisible;
    }

    // ══════════════════════════════════════════════════
    //  Cache management
    // ══════════════════════════════════════════════════

    /**
     * Clear dashboard-related caches.
     */
    public function clearDashboardCache(?int $userId = null): void
    {
        $cache = \Config\Services::cache();

        if ($userId) {
            $cache->delete("dashboard_widgets_user_{$userId}");
        }
    }

    // ══════════════════════════════════════════════════
    //  Seeder (one-time, not every page load)
    // ══════════════════════════════════════════════════

    /**
     * Seed default built-in widgets.
     * Should be called ONCE (via migration / CLI command), not on every request.
     */
    public function seedDefaults(): int
    {
        $defaults = [
            ['slug' => 'total-users',     'title' => 'Toplam Kullanıcı',    'type' => 'stat',  'icon' => 'fas fa-users',           'color' => 'info',      'default_size' => 'col-lg-3', 'is_system' => 1],
            ['slug' => 'total-pages',     'title' => 'Toplam Sayfa',        'type' => 'stat',  'icon' => 'fas fa-file-alt',        'color' => 'success',   'default_size' => 'col-lg-3', 'is_system' => 1],
            ['slug' => 'total-blogs',     'title' => 'Toplam Blog',         'type' => 'stat',  'icon' => 'fas fa-newspaper',       'color' => 'primary',   'default_size' => 'col-lg-3', 'is_system' => 1],
            ['slug' => 'total-comments',  'title' => 'Toplam Yorum',        'type' => 'stat',  'icon' => 'fas fa-comments',        'color' => 'warning',   'default_size' => 'col-lg-3', 'is_system' => 1],
            ['slug' => 'recent-activity', 'title' => 'Son Aktiviteler',     'type' => 'table', 'icon' => 'fas fa-stream',          'color' => 'primary',   'default_size' => 'col-lg-6', 'is_system' => 1],
            ['slug' => 'recent-logins',   'title' => 'Son Girişler',        'type' => 'table', 'icon' => 'fas fa-sign-in-alt',     'color' => 'info',      'default_size' => 'col-lg-6', 'is_system' => 1],
        ];
        if ($this->commonModel->db->tableExists('cronjobs')) {
            $defaults[] = ['slug' => 'active-cronjobs', 'title' => 'Aktif CronJob',       'type' => 'stat',  'icon' => 'fas fa-clock',           'color' => 'success',   'default_size' => 'col-lg-3', 'is_system' => 1];
            $defaults[] = ['slug' => 'failed-cronjobs', 'title' => 'Başarısız CronJob',   'type' => 'stat',  'icon' => 'fas fa-exclamation',     'color' => 'danger',    'default_size' => 'col-lg-3', 'is_system' => 1];
        }
        if ($this->commonModel->db->tableExists('activity_logs')) {
            $defaults[] = ['slug' => 'today-logs',      'title' => 'Bugünkü Loglar',      'type' => 'stat',  'icon' => 'fas fa-history',         'color' => 'secondary', 'default_size' => 'col-lg-3', 'is_system' => 1];
        }
        if ($this->commonModel->db->tableExists('notifications')) {
            $defaults[] = ['slug' => 'unread-notifs',   'title' => 'Okunmamış Bildirim',  'type' => 'stat',  'icon' => 'fas fa-bell',            'color' => 'danger',    'default_size' => 'col-lg-3', 'is_system' => 1];
        }
        $count = 0;
        foreach ($defaults as $w) {
            $existing = $this->commonModel->selectOne('dashboard_widgets', ['slug' => $w['slug']]);
            if (!$existing) {
                $w['is_active'] = 1;
                $this->commonModel->create('dashboard_widgets', $w);
                $count++;
            }
        }

        return $count;
    }

    // ══════════════════════════════════════════════════
    //  Single widget data (used for table/chart/html types & AJAX refresh)
    // ══════════════════════════════════════════════════

    /**
     * Fetch data for a widget by its slug.
     * Routes to the appropriate built-in data provider.
     */
    public function getWidgetData(string $slug): array
    {
        $method = 'data_' . str_replace('-', '_', $slug);

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        // Custom data_source: check widget record
        $widget = $this->commonModel->selectOne('dashboard_widgets', ['slug' => $slug]);
        if ($widget && !empty($widget->data_source)) {
            return $this->resolveDataSource($widget->data_source);
        }

        return ['value' => '—', 'label' => $slug];
    }

    // ── Built-in Data Providers (only for non-stat types) ──

    protected function data_recent_activity(): array
    {
        return [
            'rows' => $this->commonModel->lists('activity_logs', 'username, action, module, record_title, created_at', [], 'created_at DESC', 8, 0),
            'label' => 'Recent Activity'
        ];
    }

    protected function data_recent_logins(): array
    {
        return [
            'rows' => $this->commonModel->lists(
                'auth_login',
                'auth_identities.secret, auth_login.ip_address, auth_login.date',
                ['auth_login.success' => 1],
                'auth_login.date DESC',
                8,
                0,
                [],
                [],
                [['table' => 'auth_identities', 'cond' => 'auth_identities.user_id = auth_login.user_id', 'type' => 'left']]
            ),
            'label' => 'Recent Logins'
        ];
    }

    // ── Stat data providers kept for single-widget AJAX refresh ──

    protected function data_total_users(): array
    {
        return ['value' => $this->commonModel->count('users', ['deleted_at' => null]), 'label' => 'Users'];
    }

    protected function data_total_pages(): array
    {
        return ['value' => $this->commonModel->count('pages', ['isActive' => 1]), 'label' => 'Pages'];
    }

    protected function data_total_blogs(): array
    {
        return ['value' => $this->commonModel->count('blog', ['isActive' => 1]), 'label' => 'Blogs'];
    }

    protected function data_total_comments(): array
    {
        return ['value' => $this->commonModel->count('comments', ['isApproved' => 1]), 'label' => 'Comments'];
    }

    protected function data_active_cronjobs(): array
    {
        return ['value' => $this->commonModel->count('cronjobs', ['is_active' => 1]), 'label' => 'Active'];
    }

    protected function data_failed_cronjobs(): array
    {
        return ['value' => $this->commonModel->count('cronjobs', ['last_status' => 'failed']), 'label' => 'Failed'];
    }

    protected function data_today_logs(): array
    {
        return ['value' => $this->commonModel->count('activity_logs', ['DATE(created_at)' => date('Y-m-d')]), 'label' => 'Today'];
    }

    protected function data_unread_notifs(): array
    {
        $userId = auth()->user()->id ?? 0;
        return ['value' => $this->commonModel->count('notifications', ['user_id' => $userId, 'is_read' => 0]), 'label' => 'Unread'];
    }

    /**
     * Resolve custom data_source string. Format: ClassName::methodName
     */
    protected function resolveDataSource(string $source): array
    {
        if (str_contains($source, '::')) {
            [$class, $method] = explode('::', $source, 2);
            if (class_exists($class) && method_exists($class, $method)) {
                $obj = new $class();
                return (array) $obj->{$method}();
            }
        }

        return ['value' => '—', 'label' => 'Unknown source'];
    }
}
