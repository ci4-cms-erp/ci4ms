<@php

namespace Modules\Backend\Controllers;

class {class} extends {extends}
{
<?php if ($type === 'controller'): ?>
    public function index()
    {
        return view('Modules\Backend\Views\',$this->defData);
    }

    public function show($id = null)
    {
        //
    }

    public function new()
    {
        //
    }

    public function create()
    {
        //
    }

    public function edit($id = null)
    {
        //
    }

    public function update($id = null)
    {
        //
    }

    public function delete($id = null)
    {
        //
    }
<?php elseif ($type === 'presenter'): ?>
    public function index()
    {
        return view('Modules\Backend\Views\',$this->defData);
    }

    public function show($id = null)
    {
        //
    }

    public function new()
    {
        //
    }

    public function create()
    {
        //
    }

    public function edit($id = null)
    {
        //
    }

    public function update($id = null)
    {
        //
    }

    public function remove($id = null)
    {
        //
    }

    public function delete($id = null)
    {
        //
    }
<?php else: ?>
    public function index()
    {
        return view('Modules\Backend\Views\',$this->defData);
    }
<?php endif ?>
}
