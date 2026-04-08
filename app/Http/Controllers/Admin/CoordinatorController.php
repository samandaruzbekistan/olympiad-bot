<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoordinatorController extends Controller
{
    public function dashboard(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        $regionId = $admin?->region_id;

        $olympiads = Olympiad::query()
            ->withCount([
                'registrations as total_participants' => fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('region_id', $regionId)),
                'registrations as paid_count' => fn ($q) => $q->where('payment_status', 'paid')->whereHas('user', fn ($uq) => $uq->where('region_id', $regionId)),
                'registrations as pending_count' => fn ($q) => $q->where('payment_status', 'pending')->whereHas('user', fn ($uq) => $uq->where('region_id', $regionId)),
                'registrations as failed_count' => fn ($q) => $q->where('payment_status', 'failed')->whereHas('user', fn ($uq) => $uq->where('region_id', $regionId)),
            ])
            ->orderByDesc('start_date')
            ->paginate(15);

        return view('admin.coordinator.dashboard', compact('olympiads'));
    }

    public function participants(Request $request, Olympiad $olympiad): View
    {
        $admin = Auth::guard('admin')->user();
        $regionId = $admin?->region_id;

        $query = Registration::query()
            ->with(['user.region', 'user.district'])
            ->where('olympiad_id', $olympiad->id)
            ->whereHas('user', fn ($q) => $q->where('region_id', $regionId));

        if ($status = $request->query('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'total' => (clone $query)->count(),
            'paid' => (clone $query)->where('payment_status', 'paid')->count(),
            'pending' => (clone $query)->where('payment_status', 'pending')->count(),
            'failed' => (clone $query)->where('payment_status', 'failed')->count(),
        ];

        return view('admin.coordinator.participants', compact('olympiad', 'registrations', 'counts'));
    }

    public function export(Request $request, Olympiad $olympiad): StreamedResponse
    {
        $admin = Auth::guard('admin')->user();
        $regionId = $admin?->region_id;

        $query = Registration::query()
            ->with(['user.region', 'user.district', 'user.subjects'])
            ->where('olympiad_id', $olympiad->id)
            ->whereHas('user', fn ($q) => $q->where('region_id', $regionId));

        if ($status = $request->query('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->get();

        $sheet = (new Spreadsheet())->getActiveSheet();
        $headers = ['#', 'Ism', 'Familiya', 'Telefon', 'Viloyat', 'Tuman', 'Maktab', 'Sinf', 'Fanlar', 'To\'lov holati', 'To\'lov tizimi', 'Chipta', 'Sana'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $r = 2;
        foreach ($rows as $i => $reg) {
            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", $reg->user?->first_name ?? '');
            $sheet->setCellValue("C{$r}", $reg->user?->last_name ?? '');
            $sheet->setCellValue("D{$r}", $reg->user?->phone ?? '');
            $sheet->setCellValue("E{$r}", $reg->user?->region?->name_uz ?? '');
            $sheet->setCellValue("F{$r}", $reg->user?->district?->name_uz ?? '');
            $sheet->setCellValue("G{$r}", $reg->user?->school ?? '');
            $sheet->setCellValue("H{$r}", $reg->user?->grade ? $reg->user->grade . '-sinf' : '');
            $sheet->setCellValue("I{$r}", $reg->user?->subjects?->pluck('name')->join(', ') ?? '');
            $sheet->setCellValue("J{$r}", $reg->payment_status);
            $sheet->setCellValue("K{$r}", $reg->payment_system ?? '');
            $sheet->setCellValue("L{$r}", $reg->ticket_number ?? '');
            $sheet->setCellValue("M{$r}", $reg->created_at?->format('d.m.Y H:i'));
            $r++;
        }

        foreach (range('A', 'M') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $writer = new Xlsx($sheet->getParent());
        $filename = 'coordinator_' . $olympiad->id . '_' . now()->format('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

