<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked when loading your views. Of
    | course, the usual Laravel view path has already been registered for
    | you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

    /*
    |--------------------------------------------------------------------------
    | Namespaced View Paths
    |--------------------------------------------------------------------------
    |
    | Register namespaced view paths to be used by the view factory instance.
    | The paths are searched by the namespace when a qualified view name is
    | specified.
    |
    */

    'namespaces' => [
        'mail' => resource_path('views/emails'),
    ],

];
