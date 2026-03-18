<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    public function index(): View
    {
        $usersByRegion = User::query()
            ->select('regions.name_uz as region', DB::raw('COUNT(*) as total'))
            ->leftJoin('regions', 'users.region_id', '=', 'regions.id')
            ->groupBy('regions.name_uz')
            ->orderByDesc('total')
            ->get();

        $usersByGrade = User::query()
            ->select('grade', DB::raw('COUNT(*) as total'))
            ->whereNotNull('grade')
            ->groupBy('grade')
            ->orderBy('grade')
            ->get();

        $registrationsByOlympiad = Registration::query()
            ->select('olympiads.title', DB::raw('COUNT(*) as total'))
            ->join('olympiads', 'registrations.olympiad_id', '=', 'olympiads.id')
            ->groupBy('olympiads.title')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $revenueByOlympiad = Payment::query()
            ->select('olympiads.title', DB::raw('SUM(payments.amount) as total'))
            ->join('registrations', 'payments.registration_id', '=', 'registrations.id')
            ->join('olympiads', 'registrations.olympiad_id', '=', 'olympiads.id')
            ->where('payments.status', 'success')
            ->groupBy('olympiads.title')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $paymentsByStatus = Payment::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $dailyRegistrations = Registration::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyRevenue = Payment::query()
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topSubjects = DB::table('user_subject')
            ->select('subjects.name', DB::raw('COUNT(*) as total'))
            ->join('subjects', 'user_subject.subject_id', '=', 'subjects.id')
            ->groupBy('subjects.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('admin.statistics.index', compact(
            'usersByRegion',
            'usersByGrade',
            'registrationsByOlympiad',
            'revenueByOlympiad',
            'paymentsByStatus',
            'dailyRegistrations',
            'dailyRevenue',
            'topSubjects',
        ));
    }
}
