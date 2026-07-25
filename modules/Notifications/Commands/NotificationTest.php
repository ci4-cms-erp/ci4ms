<?php

declare(strict_types=1);

namespace Modules\Notifications\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Notifications\Libraries\Notifier;

/**
 * Bildirim Merkezi'ni uçtan uca denemek için hızlı bir test komutu.
 *
 * `Notifier` servisini çağırdığı için aynı zamanda gerçek kod yolunu
 * (create / relevance / count / cache invalidation) doğrular.
 *
 * Kullanım:
 *   php spark notifications:test                 # user_id=1'e bir bildirim
 *   php spark notifications:test 5               # user_id=5'e bir bildirim
 *   php spark notifications:test --role superadmin   # grup için tek satır (üyelik okuma-zamanında çözülür)
 *   php spark notifications:test 5 --title "Merhaba" --body "Deneme" --url /backend/blog
 */
class NotificationTest extends BaseCommand
{
    protected $group       = 'Ci4MS';
    protected $name        = 'notifications:test';
    protected $description = 'Send a test notification to a user (or a role) to verify the Notification Center.';
    protected $usage       = 'notifications:test [user_id] [--role group] [--title text] [--body text] [--url path]';
    protected $arguments   = [
        'user_id' => 'Target user id (default: 1). Ignored when --role is given.',
    ];
    protected $options = [
        '--role'  => 'Send to every user in this Shield group instead of a single user.',
        '--title' => 'Notification title (default: "Test bildirimi").',
        '--body'  => 'Notification body (optional).',
        '--url'   => 'Click target: site-relative "/..." or http(s) (optional).',
    ];

    public function run(array $params): void
    {
        $notifier = new Notifier();

        $title = (string) ($params['title'] ?? CLI::getOption('title') ?: 'Test bildirimi');
        $body  = (string) ($params['body']  ?? CLI::getOption('body')  ?: 'Bu, Bildirim Merkezi doğrulaması için gönderilen bir test kaydıdır.');
        $url   = $params['url'] ?? CLI::getOption('url') ?: null;
        $role  = $params['role'] ?? CLI::getOption('role') ?: null;

        if ($role) {
            $created = $notifier->toRole((string) $role, 'test', $title, $body, $url ? (string) $url : null);

            if ($created === 0) {
                CLI::error("'{$role}' grubu için bildirim satırı oluşturulamadı.");
                return;
            }

            CLI::write(CLI::color("OK: '{$role}' grubu için bildirim satırı oluşturuldu (üyelik okuma-zamanında çözülür).", 'green'));
            return;
        }

        $userId = (int) (($params[0] ?? null) ?: 1);

        if ($notifier->toUser($userId, 'test', $title, $body, $url ? (string) $url : null)) {
            $unread = $notifier->unreadCount($userId);
            CLI::write(CLI::color("OK: user_id={$userId} için test bildirimi oluşturuldu.", 'green'));
            CLI::write('Bu kullanıcının güncel okunmamış sayısı: ' . $unread);
            CLI::write('Backend\'e o kullanıcıyla girip üst çubuktaki çanı kontrol et.');
        } else {
            CLI::error("Bildirim oluşturulamadı (user_id={$userId}). notifications tablosu migrate edildi mi?");
        }
    }
}
