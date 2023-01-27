<?php

namespace App\Controllers\templates\default;

use App\Libraries\CommonLibrary;

class Forms extends \App\Controllers\BaseController
{
    public function contactForm_post()
    {
        $valData = ([
            'name' => ['label' => 'Full Name', 'rules' => 'required'],
            'email' => ['label' => 'Email Address', 'rules' => 'required|valid_email'],
            'phone' => ['label' => 'Phone Number', 'rules' => 'required'],
            'message' => ['label' => 'Message', 'rules' => 'required']
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $commonLibrary = new CommonLibrary();
        $mailResult = $commonLibrary->phpMailer($this->request->getPost('email'), $this->request->getPost('name'), [['mail' => 'bertugozer1994@gmail.com']], $this->request->getPost('email'),$this->request->getPost('name'), 'İletişim Formu - ' . $this->request->getPost('phone'), $this->request->getPost('message'));
        if ($mailResult === true) return redirect()->back()->with('message', 'Mesajınız tarafımıza iletildi. En kısa zamanda geri dönüş sağlanacaktır');
        else return redirect()->back()->withInput()->with('error', $mailResult);
    }

    public function searchForm()
    {
        
    }
}
