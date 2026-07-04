<?php

namespace App\Core\Engine;

class Registry
{
    private static ?ModuleManager $modules = null;

    private static ?DiscoveryManager $discovery = null;

    public static function modules(): ModuleManager
    {
        if (self::$modules === null) {
            self::$modules = new ModuleManager();
        }

        return self::$modules;
    }

    public static function discovery(): DiscoveryManager
    {
        if (self::$discovery === null) {
            self::$discovery = new DiscoveryManager();
        }

        return self::$discovery;
    }
}
