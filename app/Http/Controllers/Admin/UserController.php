<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->applyFilters(User::query()->with(['region', 'district', 'subjects']), $request);

        $users = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $regions = Region::orderBy('name_uz')->get();

        $districts = [];
        if ($request->filled('region_id')) {
            $districts = District::where('region_id', $request->query('region_id'))
                ->orderBy('name_uz')
                ->get();
        }

        return view('admin.users.index', compact('users', 'regions', 'districts'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(User::query()->with(['region', 'district', 'subjects']), $request);
        $users = $query->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Foydalanuvchilar');

        $headers = ['#', 'Ism', 'Familiya', 'Telefon', 'Telegram ID', 'Viloyat', 'Tuman', 'Maktab', 'Sinf', 'Fanlar', "Ro'yxatdan o'tgan"];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $row = 2;
        foreach ($users as $i => $user) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $user->first_name);
            $sheet->setCellValue("C{$row}", $user->last_name);
            $sheet->setCellValueExplicit("D{$row}", $user->phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$row}", $user->telegram_id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("F{$row}", $user->region?->name_uz ?? '');
            $sheet->setCellValue("G{$row}", $user->district?->name_uz ?? '');
            $sheet->setCellValue("H{$row}", $user->school ?? '');
            $sheet->setCellValue("I{$row}", $user->grade ? $user->grade . '-sinf' : '');
            $sheet->setCellValue("J{$row}", $user->subjects->pluck('name')->join(', '));
            $sheet->setCellValue("K{$row}", $user->created_at->format('d.m.Y H:i'));

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }

            $row++;
        }

        $bodyStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle("A2:K" . ($row - 1))->applyFromArray($bodyStyle);

        foreach (range('A', 'K') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'foydalanuvchilar_' . now()->format('Y-m-d_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function districts(Request $request)
    {
        $regionId = $request->query('region_id');
        if (! $regionId) {
            return response()->json([]);
        }

        $districts = District::where('region_id', $regionId)
            ->orderBy('name_uz')
            ->get(['id', 'name_uz']);

        return response()->json($districts);
    }

    private function applyFilters($query, Request $request)
    {
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('telegram_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->query('region_id'));
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->query('district_id'));
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->query('grade'));
        }

        return $query;
    }
}
