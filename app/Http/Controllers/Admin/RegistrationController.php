<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Registration::query()->with(['user.region', 'user.district', 'olympiad']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        if ($olympiadId = $request->query('olympiad_id')) {
            $query->where('olympiad_id', $olympiadId);
        }

        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        $registrations = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $olympiads = Olympiad::orderBy('title')->get(['id', 'title']);

        return view('admin.registrations.index', compact('registrations', 'olympiads'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Registration::query()
            ->with(['user.region', 'user.district', 'user.subjects', 'olympiad']);

        if ($olympiadId = $request->query('olympiad_id')) {
            $query->where('olympiad_id', $olympiadId);
        }

        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Qatnashuvchilar');

        $headers = [
            '#', 'Ism', 'Familiya', 'Tug\'ilgan sana', 'Telefon', 'Telegram ID',
            'Viloyat', 'Tuman', 'Maktab', 'Sinf', 'Fanlar',
            'Olimpiada', 'Chipta raqami', "To'lov holati", "To'lov tizimi",
            "Ro'yxat sanasi",
        ];

        $lastCol = 'P';
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ];
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $payLabels = ['paid' => "To'langan", 'pending' => 'Kutilmoqda', 'failed' => 'Bekor qilindi'];

        $row = 2;
        foreach ($registrations as $i => $reg) {
            $user = $reg->user;

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $user?->first_name ?? '');
            $sheet->setCellValue("C{$row}", $user?->last_name ?? '');
            $sheet->setCellValue("D{$row}", $user?->birth_date?->format('d.m.Y') ?? '');
            $sheet->setCellValueExplicit("E{$row}", $user?->phone ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $user?->telegram_id ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $user?->region?->name_uz ?? '');
            $sheet->setCellValue("H{$row}", $user?->district?->name_uz ?? '');
            $sheet->setCellValue("I{$row}", $user?->school ?? '');
            $sheet->setCellValue("J{$row}", $user?->grade ? $user->grade . '-sinf' : '');
            $sheet->setCellValue("K{$row}", $user?->subjects?->pluck('name')->join(', ') ?? '');
            $sheet->setCellValue("L{$row}", $reg->olympiad?->title ?? '');
            $sheet->setCellValue("M{$row}", $reg->ticket_number ?? '');
            $sheet->setCellValue("N{$row}", $payLabels[$reg->payment_status] ?? $reg->payment_status);
            $sheet->setCellValue("O{$row}", $reg->payment_system ? strtoupper($reg->payment_system) : '');
            $sheet->setCellValue("P{$row}", $reg->created_at->format('d.m.Y H:i'));

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }

            $row++;
        }

        if ($row > 2) {
            $sheet->getStyle("A2:{$lastCol}" . ($row - 1))->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $olympiad = $request->query('olympiad_id')
            ? Olympiad::find($request->query('olympiad_id'))
            : null;

        $prefix  = $olympiad ? \Illuminate\Support\Str::slug($olympiad->title) . '_' : '';
        $filename = "qatnashuvchilar_{$prefix}" . now()->format('Y-m-d_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
