<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

final class DashboardController
{
    public function index(Request $request): void
    {
        view('dashboard', ['user' => current_user()]);
    }
}
