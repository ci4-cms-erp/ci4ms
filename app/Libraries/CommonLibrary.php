<?php namespace App\Libraries;

use ci4commonModel\Models\CommonModel;
use Modules\Backend\Config\Auth;
use PHPMailer\PHPMailer\PHPMailer;

class CommonLibrary
{
    protected $config;
    protected $commonModel;

    public function __construct()
    {
        $this->config = new Auth();
        $this->commonModel = new CommonModel();
    }

    /**
     * @param string $setFromMail
     * @param string $setFromName
     * @param array $addAddresses = [['mail'=>'example@ci4ms.com','name'=>'ci4ms'],['mail'=>'example2@ci4ms.com','name'=>'ci4ms2']]
     * @param string $addReplyToMail
     * @param string $addReplyToName
     * @param string $subject
     * @param string $body
     * @param string $altBody
     * @param array $addCCs = [['mail'=>'example@ci4ms.com','name'=>'ci4ms'],['mail'=>'example2@ci4ms.com','name'=>'ci4ms2']]
     * @param array $addBCCs = [['mail'=>'example@ci4ms.com','name'=>'ci4ms'],['mail'=>'example2@ci4ms.com','name'=>'ci4ms2']]
     * @param array $addAttachments = [['path'=>'/var/tmp/file.tar.gz','name'=>'ci4ms.tar.gz'],['path'=>'/tmp/image.jpg','name'=>'ci4ms.jpg']] name is optional
     * @return bool|string
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function phpMailer(string $setFromMail, string $setFromName, array $addAddresses, string $addReplyToMail, string $addReplyToName, string $subject, string $body, string $altBody = '', array $addCCs = [], array $addBCCs = [], array $addAttachments = [],)
    {
        $settings = $this->commonModel->selectOne('settings');
        $this->config->mailConfig = ['protocol' => $settings->mailProtocol,
            'SMTPHost' => $settings->mailServer,
            'SMTPPort' => $settings->mailPort,
            'SMTPUser' => $settings->mailAddress,
            'SMTPPass' => $settings->mailPassword,
            'charset' => 'UTF-8',
            'mailtype' => 'html',
            'wordWrap' => 'true',
            'TLS' => $settings->mailTLS,
            'newline' => "\r\n"];
        if ($settings->mailProtocol === 'smtp') $this->config->mailConfig['SMTPCrypto'] = 'PHPMailer::ENCRYPTION_STARTTLS';
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->Host = $this->config->mailConfig['SMTPHost'];        // Set the SMTP server to send through
            $mail->Username = $this->config->mailConfig['SMTPUser'];    // SMTP username
            $mail->Password = $this->config->mailConfig['SMTPPass'];    // SMTP password
            $mail->CharSet = "UTF-8";

            if ($this->config->mailConfig['protocol'] === 'smtp') {
                $mail->Port = $this->config->mailConfig['SMTPPort']; // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
                $mail->isSMTP(); // Send using SMTP
                $mail->SMTPAuth = true; // Enable SMTP authentication
            }
            if ($this->config->mailConfig['TLS'] === true) $mail->SMTPSecure = $this->config->mailConfig['SMTPCrypto']; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged

            //Recipients
            $mail->setFrom($setFromMail, $setFromName);
            foreach ($addAddresses as $address) {
                if (!empty($address['name'])) $mail->addAddress($address['mail'], $address['name']);  // Name is optional
                else $mail->addAddress($address['mail']);  // Name is optional
            }

            $mail->addReplyTo($addReplyToMail, $addReplyToName);
            foreach ($addCCs as $addCC) $mail->addCC($addCC);
            foreach ($addBCCs as $addBCC) $mail->addBCC($addBCC);
            foreach ($addAttachments as $addAttachment) {
                if (!empty($addAttachment['name'])) $mail->addAttachment($addAttachment['path'], $addAttachment['name']);
                else $mail->addAttachment($addAttachment['path']);
            }

            // Content
            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body = $body;
            if (!empty($altBody)) $mail->AltBody = $altBody;
            return $mail->send();
        } catch (\App\Libraries\Exception $e) {
            return $mail->ErrorInfo;
        }
    }

    private function findFunction($string, $start, $end)
    {
        $part = explode($start, $string);
        $d = [];
        foreach ($part as $item) {
            if (strpos($item, '/}')) $d[] = explode($end, $item);
        }
        $part = null;
        foreach ($d as $item) {
            $part[$start . $item[0] . $end] = $item[0];
        }
        return $part;
    }

    //TODO: çoklu veri işlenmesi için virgül kullanılır hale getirilecek.(,)
    public function parseInTextFunctions(string $string)
    {
        $functions = $this->findFunction($string, '{', '/}');
        if (strpos($string, '[/')) {
            $val = $this->findFunction($string, '[/', '/]');
            $v = array_values($val)[0];
        }
        if (empty($functions)) return $string;
        foreach ($functions as $function) {
            $f = explode('|', $function);
            if (strpos($f[1], '[/')) $f[1] = strstr($f[1], '[/', true);
            if (!empty($val)) $data[$function] = call_user_func_array($f, [$v]);
            else $data[$function] = call_user_func($f);
        }
        return str_replace(array_keys($functions), $data, $string);
    }

    /*TODO: buraya blog controllerınun yapacağı işleri fonksiyonlayarak çağırılmasını sağlayacağız. conroller dururken pages kısmına eklenen sayfalar içinde fonksiyonlar ile çağırılacak.*/

    public function commentBadwordFiltering(string $comment, array $badwordsList, bool $status = false, bool $autoReject = false,bool $autoAccept=false): bool|string
    {
        $pattern = '/\b(' . implode('|', $badwordsList) . ')\b/i';
        if ($autoReject) return false;
        if($status && $autoAccept){
            $comment = preg_replace($pattern, str_repeat('*', strlen('$0')), $comment);
            return $comment;
        }
        if ($status) return preg_replace($pattern, str_repeat('*', strlen('$0')), $comment);
        if ($autoAccept) return $comment;
        return false;
    }

    public function get_breadcrumbs(mixed $page_id)
    {
        $menus=(object)cache('menus');
        $homepage = array_filter((array) $menus, function($menu) {
            return $menu->seflink == '/';
        });
        $homepage = reset($homepage);
        if(is_integer($page_id))
            $current_page = array_filter((array) $menus, function($menu) use($page_id) {
                return $menu->pages_id == $page_id;
            });
        if(is_string($page_id))
            $current_page = array_filter((array) $menus, function($menu) use($page_id) {
                return $menu->seflink == $page_id;
            });
        $current_page = reset($current_page);
        // Mevcut sayfa veya anasayfa boş ise breadcrumb'ları boş döndürün
        if (!$current_page || !$homepage) return array();

        $breadcrumbs = array();

        // Anasayfayı breadcrumb'lar dizisinin başına ekleyin
        array_unshift($breadcrumbs, ['title' => $homepage->title, 'url' => $homepage->seflink]);
        $tmpCurrentPage=$current_page;
        // Sayfanın mevcut parent_id'si olana kadar döngüye girin ve breadcrumb'ları diziye ekleyin
        while ($tmpCurrentPage->parent) {
            $parent_pages = array_filter((array) $menus, function($menu) use($tmpCurrentPage) {
                return $menu->id == $tmpCurrentPage->parent && $menu->seflink != '/';
            });
            $parent_page = reset($parent_pages);

            if ($parent_page) {
                array_push($breadcrumbs, ['title' => $parent_page->title, 'url' => $parent_page->seflink]);
                $tmpCurrentPage = $parent_page;
            }
        }
        // Son olarak, mevcut sayfanın bileşenlerini de breadcrumb'lar dizisine ekleyin
        array_push($breadcrumbs, ['title' => $current_page->title, 'url' =>'']);

        return $breadcrumbs;
    }
}