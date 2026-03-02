<?php

if (!function_exists('nestable')) {
    /**
     * @param $menus
     * @param $parent
     * @return void
     */
    function nestable($menus, $parent = null)
    {
        foreach ($menus as $menu) {
            if ($menu->parent == $parent) {
                echo '<li class="dd-item" data-id="' . $menu->id . '" id="menu-' . $menu->id . '">
                                <div class="dd-handle dd3-handle">
                                    <i class="fas fa-bars"></i>
                                </div>
                                <div class="dd-content">
                                    <div class="d-flex justify-content-between align-items-center">';

                if ((bool)$menu->hasChildren === true)
                    echo '<button class="dd-nodrag btn btn-sm btn-light dd-collapse float-left" data-action="collapse"><i class="fas fa-sort-down"></i></button>
<button class="dd-expand btn btn-sm btn-light float-left" data-action="expand" type="button"><i class="fas fa-caret-right"></i></button>';

                echo '<span class="float-left">' . esc($menu->title) . '</span>
                                        <div class="dd-nodrag btn-group float-right">
                               <button class="removeFromMenu btn btn-secondary btn-sm" onclick="removeFromMenu(\'' . $menu->id . '\',\'' . $menu->urlType .
                    '\')" type="button"><i class="fas fa-trash"></i></button>
                              </div>
                                    </div>
                                </div>';
                if ((bool)$menu->hasChildren === true) echo '<ol class="dd-list">';
                nestable($menus, $menu->id);
                if ((bool)$menu->hasChildren === true) echo '</ol>';
                echo '</li>';
            }
        }
    }
}

if (!function_exists('format_number')) {
    function format_number($n = '')
    {
        return ($n === '') ? '' : number_format((float)$n, 2, '.', ',');
    }
}

if (!function_exists('randomPassword')) {
    function randomPassword()
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
}
