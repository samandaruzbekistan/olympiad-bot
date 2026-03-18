<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalUsers = User::count();
        $totalOlympiads = Olympiad::count();
        $totalRegistrations = Registration::count();
        $totalPayments = Payment::where('status', 'success')->count();
        $totalRevenue = (int) Payment::where('status', 'success')->sum('amount');
        $totalTickets = Ticket::count();
        $paidRegistrations = Registration::where('payment_status', 'paid')->count();

        $recentRegistrations = Registration::with(['user', 'olympiad'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalOlympiads',
            'totalRegistrations',
            'totalPayments',
            'totalRevenue',
            'totalTickets',
            'paidRegistrations',
            'recentRegistrations',
        ));
    }
}
