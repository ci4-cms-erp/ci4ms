<?php

namespace Modules\Blog\Config;

class BlogConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/blogs',
        'backend/blogs/*'
    ];

    public $filters = ['backendGuard' => ['before' => [
        'backend/blogs',
        'backend/blogs/*'
    ]]];

    public $moduleInfo = [
        'icon' => 'fas fa-blog',
    ];

    public $menus = [

        'Blog.blog' => [
            'icon'         => 'fas fa-align-center',
            'inNavigation' => true,
            'hasChild'     => true,
            'pageSort'     => 3,
            'parent_pk'    => null
        ],
        'Blog.blogs' => [
            'icon'         => 'far fa-file-alt',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 1,
            'parent_pk'    => 'Blog.blog'
        ],
        'Blog.categories' => [
            'icon'         => 'fas fa-project-diagram',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 2,
            'parent_pk'    => 'Blog.blog'
        ],
        'Blog.tags' => [
            'icon'         => 'fas fa-tags',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 3,
            'parent_pk'    => 'Blog.blog'
        ],
        'Blog.commentList' => [
            'icon'         => 'fas fa-comments',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 4,
            'parent_pk'    => 'Blog.blog'
        ],
        'Blog.badwords' => [
            'icon'         => 'fas fa-otter',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 5,
            'parent_pk'    => 'Blog.blog'
        ]
        ];
}