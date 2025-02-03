<?php

namespace App\Libraries;

use ci4commonModel\Models\CommonModel;
use Modules\Backend\Config\Auth;
use PHPMailer\PHPMailer\PHPMailer;

class CommonLibrary
{
    protected $config;
    protected $commonModelİ;

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
        $settings = (object)cache('settings');
        $this->config->mailConfig = [
            'protocol' => $settings->mail->protocol,
            'SMTPHost' => $settings->mail->server,
            'SMTPPort' => $settings->mail->port,
            'SMTPUser' => $settings->mail->address,
            'SMTPPass' => $settings->mail->password,
            'charset' => 'UTF-8',
            'mailtype' => 'html',
            'wordWrap' => 'true',
            'TLS' => $settings->mail->tls,
            'newline' => "\r\n"
        ];
        if ($settings->mail->protocol === 'smtp') $this->config->mailConfig['SMTPCrypto'] = 'PHPMailer::ENCRYPTION_STARTTLS';
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
            foreach ($addCCs as $addCC) { 
                if (!empty($addCC['name'])) $mail->addAddress($addCC['mail'], $addCC['name']);  // Name is optional
                $mail->addCC($addCC['mail']);
            }
            foreach ($addBCCs as $addBCC){
                if (!empty($addBCC['name'])) $mail->addAddress($addBCC['mail'], $addBCC['name']);  // Name is optional
                $mail->addBCC($addBCC['mail']);
            }
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

    /**
     * @param string $string
     * @param string $start
     * @param string $end
     */
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
    /**
     * Undocumented function
     *
     * @param string $string
     * @return void
     */
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

    /**
     * @param string $comment
     * @param array $badwordsList
     * @param bool $status
     * @param bool $autoReject
     * @param bool $autoAccept
     * @return bool|string
     */
    public function commentBadwordFiltering(string $comment, array $badwordsList, bool $status = false, bool $autoReject = false, bool $autoAccept = false): bool|string
    {
        $pattern = '/\b(' . implode('|', $badwordsList) . ')\b/i';
        if ($autoReject) return false;
        if ($status && $autoAccept) {
            $comment = preg_replace($pattern, str_repeat('*', strlen('$0')), $comment);
            return $comment;
        }
        if ($status) return preg_replace($pattern, str_repeat('*', strlen('$0')), $comment);
        if ($autoAccept) return $comment;
        return false;
    }

    /**
     * @param string $comment
     */
    private function getHomepageBreadcrumb()
    {
        $menus = (object)cache('menus');
        $homepage = array_filter((array) $menus, function ($menu) {
            return $menu->seflink == '/';
        });
        if (!empty($homepage)) return reset($homepage);
        else return [];
    }

    /**
     * @param integer $id
     * @param string $type
     */
    public function get_breadcrumbs($id, $type = 'page')
    {
        $method = 'get' . ucfirst($type) . 'Breadcrumbs';
        if (method_exists($this, $method)) {
            return $this->$method($id);
        }
        return [];
    }

    /**
     *
     * @param int $id
     * @return void
     */
    private function getPageBreadcrumbs($id)
    {
        $menus = (object)cache('menus');
        $homepage = $this->getHomepageBreadcrumb();
        if (is_integer($id))
            $current_page = array_filter((array) $menus, function ($menu) use ($id) {
                return $menu->pages_id == $id;
            });
        if (is_string($id))
            $current_page = array_filter((array) $menus, function ($menu) use ($id) {
                return $menu->seflink == $id;
            });
        $current_page = reset($current_page);
        // Mevcut sayfa veya anasayfa boş ise breadcrumb'ları boş döndürün
        if (!$current_page || !$homepage) return array();

        $breadcrumbs = [['title' => $homepage->title, 'url' => $homepage->seflink]];
        $tmpCurrentPage = $current_page;
        // Sayfanın mevcut parent_id'si olana kadar döngüye girin ve breadcrumb'ları diziye ekleyin
        while ($tmpCurrentPage->parent) {
            $parent_pages = array_filter((array) $menus, function ($menu) use ($tmpCurrentPage) {
                return $menu->id == $tmpCurrentPage->parent && $menu->seflink != '/';
            });
            $parent_page = reset($parent_pages);

            if ($parent_page) {
                array_push($breadcrumbs, ['title' => $parent_page->title, 'url' => $parent_page->seflink]);
                $tmpCurrentPage = $parent_page;
            }
        }
        // Son olarak, mevcut sayfanın bileşenlerini de breadcrumb'lar dizisine ekleyin
        array_push($breadcrumbs, ['title' => $current_page->title, 'url' => '']);

        return $breadcrumbs;
    }

    private function getBlogBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => $homepage->seflink]];
        $blog = $this->commonModel->selectOne('blog', ['id' => $id]);
        $category = $this->commonModel->lists('categories', 'categories.*', ['blog_categories_pivot.blog_id' => $id], 'id ASC', 0, 0, [], [], [
            [
                'table' => 'blog_categories_pivot',
                'cond' => 'categories.id = blog_categories_pivot.categories_id',
                'type' => 'left'
            ]
        ]);
        if ($blog) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => 'blog'];
            $breadcrumbs[] = ['title' => $category[0]->title, 'url' => 'category/' . $category[0]->seflink];
            $breadcrumbs[] = ['title' => $blog->title, 'url' => ''];
        }
        return $breadcrumbs;
    }

    private function getCategoryBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => $homepage->seflink]];
        $category = $this->commonModel->selectOne('categories', ['id' => $id]);
        if ($category) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => 'blog'];
            $breadcrumbs[] = ['title' => $category->title, 'url' => ''];
        }
        return $breadcrumbs;
    }

    private function getTagBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => $homepage->seflink]];
        $tag = $this->commonModel->selectOne('tags', ['id' => $id]);
        if ($tag) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => 'blog'];
            $breadcrumbs[] = ['title' => $tag->tag, 'url' => ''];
        }
        return $breadcrumbs;
    }
}
