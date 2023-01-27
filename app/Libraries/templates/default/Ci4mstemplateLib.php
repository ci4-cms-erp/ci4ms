<?php namespace App\Libraries\templates\default;

use ci4commonModel\Models\CommonModel;

class Ci4mstemplateLib
{
    public static function contactForm()
    {
        return view('templates/default/contactForm');
    }

    public static function categories()
    {
        $commonModel = new CommonModel();
        return view('templates/default/categories', ['categories' => $commonModel->lists('categories')]);
    }

    public static function gmapiframe()
    {
        $commonModel = new CommonModel();
        return view('templates/default/gmapiframe',  ['settings' => $commonModel->selectOne('settings')]);
    }
}