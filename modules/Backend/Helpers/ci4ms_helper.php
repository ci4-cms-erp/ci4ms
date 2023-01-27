<?php

if (!function_exists('menu')) {
    /**
     * @param $menus
     * @param $parent
     * @return void
     */
    function menu($menus, $parent = null)
    {
        foreach ($menus as $menu) {
            if ($menu->parent == $parent) {
                echo '<li class="dd-item" data-id="' . $menu->pages_id . '" id="menu-' . $menu->pages_id . '">
                                <div class="dd-handle dd3-handle">
                                    <i class="fas fa-bars"></i>
                                </div>
                                <div class="dd-content">
                                    <div class="d-flex justify-content-between align-items-center">';
                if (isset($menu->hasChildren) && $menu->hasChildren === true)
                    echo '<button class="dd-nodrag btn btn-sm btn-light dd-collapse float-left" data-action="collapse"><i class="fas fa-sort-down"></i></button><button class="dd-expand btn btn-sm btn-light float-left" data-action="expand" type="button"><i class="fas fa-caret-right"></i></button>';

                echo '<span class="float-left">' . $menu->title . '</span>
                                        <div class="dd-nodrag btn-group float-right">
                               <button class="removeFromMenu btn btn-secondary btn-sm" onclick="removeFromMenu(\'';
                echo $menu->pages_id . '\',\'' . $menu->urlType;
                echo '\')" type="button"><i class="fas fa-trash"></i></button>
                              </div>
                                    </div>
                                </div>';
                if (isset($menu->hasChildren) && $menu->hasChildren === true)
                    echo '<ol class="dd-list">';
                menu($menus, $menu->pages_id);
                if (isset($menu->hasChildren) && $menu->hasChildren === true)
                    echo '</ol>';
                echo '</li>';
            }
        }
    }
}