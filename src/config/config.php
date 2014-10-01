<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | These are the directories we search for files in.
    |
    | NOTE that the '.' in require_tree . is relative to where the manifest file
    | (i.e. app/assets/javascripts/application.js) is located
    |
    */

    'paths' => array(
        'app/assets/javascripts',
        'app/assets/less',
        'provider/assets',
        'public/packages'
    ),

    /*
    |--------------------------------------------------------------------------
    | Map of file extensions to types
    |--------------------------------------------------------------------------
    */

    'contentTypes' => array(
        '.css'  => 'text/css',
        '.less' => 'text/css',
        '.js'   => 'application/javascript',
        '.jpeg' => 'image/jpeg',
        '.jpg'  => 'image/jpeg',
        '.png'  => 'image/png',
        '.gif'  => 'image/gif'
    ),

    /*
    |--------------------------------------------------------------------------
    | Post-processors
    |--------------------------------------------------------------------------
    */

    'postprocessors' => array(
        'text/css' => '\\Torann\\Duct\\Processors\\LessParser',
    ),

    /*
    |--------------------------------------------------------------------------
    | Asset compressors
    |--------------------------------------------------------------------------
    */

    'compressors' => array(
        'text/css'                => '\\Torann\\Duct\\Compressor\\UglifyCss',
        'application/javascript'  => '\\Torann\\Duct\\Compressor\\UglifyJs'
    ),

    /*
    |--------------------------------------------------------------------------
    | Local assets directory
    |--------------------------------------------------------------------------
    |
    | Override default prefix folder for local assets. Don't use trailing slash!.
    | They are relative to your public folder.
    */

    'asset_dir' => 'assets',

    /*
    |--------------------------------------------------------------------------
    | Production Environment
    |--------------------------------------------------------------------------
    |
    | Assets needs to know what your production environment is so that it can
    | respond with the correct assets. When in production Assets will attempt
    | to return any built collections. If a collection has not been built
    | Assets will dynamically route to each asset in the collection and apply
    | the filters.
    |
    | The last method can be very taxing so it's highly recommended that
    | collections are built when deploying to a production environment.
    |
    | You can supply an array of production environment names if you need to.
    |
    */

    'production' => array('production', 'prod'),

    /*
    |--------------------------------------------------------------------------
    | Enable static file fingerprints
    |--------------------------------------------------------------------------
    |
    | If enabled this will append a fingerprint to the static files when
    | copied in production.
    |
    */

    'enable_static_file_fingerprint' => true,

    /*
    |--------------------------------------------------------------------------
    | Static files to publish to the public assets directory
    |--------------------------------------------------------------------------
    |
    | The array consists of the asset's location and its destination.
    |
    | 'destination' => array(
    |     'source'
    | )
    |
    */

    'static_files' => array(

        'images' => array(
            'app/assets/images'
        ),
    ),

);

