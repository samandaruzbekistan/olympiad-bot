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
        $query = Payment::query()->with('registration.olympiad');

        if ($search = $request->query('search')) {
            $query->where('transaction_id', 'like', "%{$search}%");
        }

        $payments = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }
}
