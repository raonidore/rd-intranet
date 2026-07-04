<?php

namespace App\Core\Bootstrap;

use App\Core\Engine\Registry;
use App\Modules\Samba\SambaModule;
use App\Modules\Samba\Discovery\SambaDiscoveryEngine;

class CoreBootstrap
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $module = new SambaModule();

        Registry::modules()->register($module);

        Registry::discovery()->register(
            new SambaDiscoveryEngine()
        );

        self::$booted = true;
    }
}
