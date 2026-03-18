<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::query()->with(['registration.user', 'registration.olympiad']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('registration.user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $payments = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $totalAmount = Payment::where('status', 'success')->sum('amount');
        $totalCount = Payment::where('status', 'success')->count();

        return view('admin.payments.index', compact('payments', 'totalAmount', 'totalCount'));
    }
}
