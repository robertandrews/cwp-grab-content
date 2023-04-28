<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit84f8ee48341e20b78a2e9f74263fb366
{
    public static $prefixLengthsPsr4 = array (
        'f' => 
        array (
            'fivefilters\\Readability\\' => 24,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
        ),
        'M' => 
        array (
            'Masterminds\\' => 12,
        ),
        'L' => 
        array (
            'League\\Uri\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'fivefilters\\Readability\\' => 
        array (
            0 => __DIR__ . '/..' . '/fivefilters/readability.php/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Masterminds\\' => 
        array (
            0 => __DIR__ . '/..' . '/masterminds/html5/src',
        ),
        'League\\Uri\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/uri-interfaces/src',
            1 => __DIR__ . '/..' . '/league/uri/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit84f8ee48341e20b78a2e9f74263fb366::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit84f8ee48341e20b78a2e9f74263fb366::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit84f8ee48341e20b78a2e9f74263fb366::$classMap;

        }, null, ClassLoader::class);
    }
}
