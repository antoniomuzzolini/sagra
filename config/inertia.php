<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page paths
    |--------------------------------------------------------------------------
    |
    | The package default is `js/Pages`, but the Vue starter kit ships pages
    | in lowercase `js/pages`. The mismatch only surfaces on case-sensitive
    | filesystems (i.e. Linux CI), where `assertInertia` cannot find the
    | page components.
    |
    */

    'page_paths' => [

        resource_path('js/pages'),

    ],

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [

            resource_path('js/pages'),

        ],

        'page_extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
