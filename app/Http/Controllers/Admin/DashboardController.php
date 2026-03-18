<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Registration;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalUsers = User::count();
        $totalOlympiads = Olympiad::count();
        $totalRegistrations = Registration::count();
        $totalPayments = 0;
        $totalRevenue = 0;

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalOlympiads',
            'totalRegistrations',
            'totalPayments',
            'totalRevenue',
        ));
    }
}
