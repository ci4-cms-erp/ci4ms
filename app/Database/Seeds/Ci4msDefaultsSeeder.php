<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;
use Modules\Install\Services\InstallService;

class Ci4msDefaultsSeeder extends Seeder
{
    public function run()
    {
        $fname = CLI::prompt('Please enter your name');
        $sname = CLI::prompt('Please enter your surname');
        $username = CLI::prompt('Please enter your username');
        $email = CLI::prompt('Please enter your E-mail');
        $password = CLI::prompt('Please enter your password');
        $installService= new InstallService();
        $installService->createDefaultData([
            'fname' => strip_tags(trim($fname)),
            'sname' => strip_tags(trim($sname)),
            'username' => strip_tags(trim($username)),
            'email' => strip_tags(trim($email)),
            'password' => $password,
        ]);
    }
}
