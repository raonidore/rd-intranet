<?php

namespace App\Controllers;

use App\Core\Engine\Registry;

class CoreController
{
    public function index()
    {
        auth_required();

        $modules = Registry::modules()->info();

        $discovery = Registry::discovery()->runAll();

        view('core/index', compact(
            'modules',
            'discovery'
        ));
    }
}
