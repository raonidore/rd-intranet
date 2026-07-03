<?php

namespace App\Core;

class Application
{
    public static function boot(): void
    {
        session_start();

        date_default_timezone_set('America/Recife');

        error_reporting(E_ALL);

        ini_set('display_errors', 1);
    }
}
