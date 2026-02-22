<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail  = '';
    public string $fromName   = '';
    public string $recipients = '';

    /**
     * The "user agent"
     */
    public string $userAgent = 'CodeIgniter';

    /**
     * The mail sending protocol: mail, sendmail, smtp
     */
    public string $protocol = 'mail';

    /**
     * The server path to Sendmail.
     */
    public string $mailPath = '/usr/sbin/sendmail';

    /**
     * SMTP Server Hostname
     */
    public string $SMTPHost = '';

    /**
     * SMTP Username
     */
    public string $SMTPUser = '';

    /**
     * SMTP Password
     */
    public string $SMTPPass = '';

    /**
     * SMTP Port
     */
    public int $SMTPPort = 25;

    /**
     * SMTP Timeout (in seconds)
     */
    public int $SMTPTimeout = 5;

    /**
     * Enable persistent SMTP connections
     */
    public bool $SMTPKeepAlive = false;

    /**
     * SMTP Encryption.
     *
     * @var string '', 'tls' or 'ssl'. 'tls' will issue a STARTTLS command
     *             to the server. 'ssl' means implicit SSL. Connection on port
     *             465 should set this to ''.
     */
    public string $SMTPCrypto = 'tls';

    /**
     * Enable word-wrap
     */
    public bool $wordWrap = true;

    /**
     * Character count to wrap at
     */
    public int $wrapChars = 76;

    /**
     * Type of mail, either 'text' or 'html'
     */
    public string $mailType = 'text';

    /**
     * Character set (utf-8, iso-8859-1, etc.)
     */
    public string $charset = 'UTF-8';

    /**
     * Whether to validate the email address
     */
    public bool $validate = false;

    /**
     * Email Priority. 1 = highest. 5 = lowest. 3 = normal
     */
    public int $priority = 3;

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $CRLF = "\r\n";

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $newline = "\r\n";

    /**
     * Enable BCC Batch Mode.
     */
    public bool $BCCBatchMode = false;

    /**
     * Number of emails in each BCC batch
     */
    public int $BCCBatchSize = 200;

    /**
     * Enable notify message from server
     */
    public bool $DSN = false;

    public function __construct()
    {
        parent::__construct();

        // 1. Ayarları Cache'den veya Veritabanından Çek
        // BaseController'daki mantığın aynısını buraya uyguluyoruz.

        $settings = (object)cache('settings');

        if (! $settings) {
            // Cache'de yoksa veritabanına bağlan
            try {

                $commonModel = new \ci4commonmodel\Models\CommonModel();
                $$settings->mail = \json_decode($commonModel->selectOne('settings', ['key' => 'mail'], 'value')->value, \JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                // Veritabanı hatası olursa (kurulum aşaması vb.) sessiz kal veya logla
                log_message('error', 'Email Config DB Error: ' . $e->getMessage());
                return;
            }
        }

        // 2. Ayarları Config Dosyasına Enjekte Et
        // $settings->mail objesinin dolu olduğundan emin olalım
        if (isset($settings->mail)) {
            $this->fromEmail  = 'noreply@' . $_SERVER['HTTP_HOST'];
            $this->fromName   = 'noreply@' . $_SERVER['HTTP_HOST'];
            $this->recipients = $settings->mail->recipients ?? '';

            $mailConfig = $settings->mail;

            // Protokol (smtp, mail, sendmail)
            $this->protocol = $mailConfig->protocol ?? 'smtp';

            // SMTP Ayarları
            $this->SMTPHost = $mailConfig->server ?? '';
            $this->SMTPUser = $mailConfig->address ?? '';
            $this->SMTPPort = (int) ($mailConfig->port ?? 587);
            $this->SMTPCrypto = $mailConfig->tls ?? 'tls'; // ssl veya tls

            // 3. Şifre Çözme (Decryption)
            // BaseController'daki şifre çözme mantığını buraya alıyoruz
            if (! empty($mailConfig->password)) {
                try {
                    $encrypter = \Config\Services::encrypter();
                    // BaseController'daki kodunuz: base64_decode ve decrypt
                    $this->SMTPPass = $encrypter->decrypt(base64_decode($mailConfig->password));
                } catch (\Throwable $e) {
                    log_message('error', 'Email SMTP Password Decrypt Error: ' . $e->getMessage());
                }
            }

            // Diğer Ayarlar
            $this->mailType = 'html';
            $this->charset  = 'UTF-8';
            $this->newline  = "\r\n";
            $this->wordWrap = true;
        }
    }
}
