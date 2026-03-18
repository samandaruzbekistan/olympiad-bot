@extends('admin.layout')

@section('title', 'Statistika')
@section('page-title', 'Statistika')

@section('content')
<div class="space-y-8">
    {{-- Kunlik ro'yxatga olish va daromad --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Kunlik ro'yxatga olish (oxirgi 30 kun)</h3>
            <div class="mt-4">
                <canvas id="dailyRegistrationsChart" height="200"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Kunlik daromad (oxirgi 30 kun)</h3>
            <div class="mt-4">
                <canvas id="dailyRevenueChart" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- Viloyat va sinf bo'yicha --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Viloyatlar bo'yicha foydalanuvchilar</h3>
            <div class="mt-4">
                <canvas id="regionChart" height="300"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Sinflar bo'yicha foydalanuvchilar</h3>
            <div class="mt-4">
                <canvas id="gradeChart" height="300"></canvas>
            </div>
        </div>
    </div>

    {{-- Olimpiadalar va fanlar --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Olimpiadalar bo'yicha ro'yxatga olish</h3>
            <div class="mt-4">
                <canvas id="olympiadChart" height="300"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Olimpiadalar bo'yicha daromad</h3>
            <div class="mt-4">
                <canvas id="revenueByOlympiadChart" height="300"></canvas>
            </div>
        </div>
    </div>

    {{-- To'lovlar holati va mashhur fanlar --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">To'lovlar holati</h3>
            <div class="mt-4 flex justify-center">
                <div class="w-64">
                    <canvas id="paymentStatusChart" height="260"></canvas>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Eng mashhur fanlar</h3>
            <div class="mt-4">
                <canvas id="subjectsChart" height="260"></canvas>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var colors = {
        indigo: 'rgb(79, 70, 229)',
        indigoLight: 'rgba(79, 70, 229, 0.1)',
        emerald: 'rgb(16, 185, 129)',
        emeraldLight: 'rgba(16, 185, 129, 0.1)',
        amber: 'rgb(245, 158, 11)',
        red: 'rgb(239, 68, 68)',
        slate: 'rgb(100, 116, 139)',
    };

    var chartDefaults = { responsive: true, maintainAspectRatio: false };

    // Daily registrations
    new Chart(document.getElementById('dailyRegistrationsChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($dailyRegistrations->pluck('date')) !!},
            datasets: [{
                label: "Ro'yxatga olish",
                data: {!! json_encode($dailyRegistrations->pluck('total')) !!},
                borderColor: colors.indigo,
                backgroundColor: colors.indigoLight,
                fill: true,
                tension: 0.3,
            }]
        },
        options: { ...chartDefaults, plugins: { legend: { display: false } } }
    });

    // Daily revenue
    new Chart(document.getElementById('dailyRevenueChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($dailyRevenue->pluck('date')) !!},
            datasets: [{
                label: 'Daromad',
                data: {!! json_encode($dailyRevenue->pluck('total')) !!},
                borderColor: colors.emerald,
                backgroundColor: colors.emeraldLight,
                fill: true,
                tension: 0.3,
            }]
        },
        options: { ...chartDefaults, plugins: { legend: { display: false } } }
    });

    // By region
    new Chart(document.getElementById('regionChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($usersByRegion->pluck('region')->map(fn($r) => $r ?? 'Noaniq')) !!},
            datasets: [{
                label: 'Foydalanuvchilar',
                data: {!! json_encode($usersByRegion->pluck('total')) !!},
                backgroundColor: colors.indigo,
                borderRadius: 6,
            }]
        },
        options: { ...chartDefaults, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    // By grade
    new Chart(document.getElementById('gradeChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($usersByGrade->pluck('grade')->map(fn($g) => $g . '-sinf')) !!},
            datasets: [{
                label: 'Foydalanuvchilar',
                data: {!! json_encode($usersByGrade->pluck('total')) !!},
                backgroundColor: colors.emerald,
                borderRadius: 6,
            }]
        },
        options: { ...chartDefaults, plugins: { legend: { display: false } } }
    });

    // Registrations by olympiad
    new Chart(document.getElementById('olympiadChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($registrationsByOlympiad->pluck('title')) !!},
            datasets: [{
                label: "Ro'yxatga olish",
                data: {!! json_encode($registrationsByOlympiad->pluck('total')) !!},
                backgroundColor: colors.indigo,
                borderRadius: 6,
            }]
        },
        options: { ...chartDefaults, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    // Revenue by olympiad
    new Chart(document.getElementById('revenueByOlympiadChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($revenueByOlympiad->pluck('title')) !!},
            datasets: [{
                label: 'Daromad',
                data: {!! json_encode($revenueByOlympiad->pluck('total')) !!},
                backgroundColor: colors.emerald,
                borderRadius: 6,
            }]
        },
        options: { ...chartDefaults, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    // Payment status pie
    new Chart(document.getElementById('paymentStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Muvaffaqiyatli', 'Kutilmoqda', 'Muvaffaqiyatsiz'],
            datasets: [{
                data: [
                    {{ $paymentsByStatus['success'] ?? 0 }},
                    {{ $paymentsByStatus['pending'] ?? 0 }},
                    {{ $paymentsByStatus['failed'] ?? 0 }}
                ],
                backgroundColor: [colors.emerald, colors.amber, colors.red],
            }]
        },
        options: { ...chartDefaults }
    });

    // Top subjects
    new Chart(document.getElementById('subjectsChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($topSubjects->pluck('name')) !!},
            datasets: [{
                label: 'Foydalanuvchilar',
                data: {!! json_encode($topSubjects->pluck('total')) !!},
                backgroundColor: colors.indigo,
                borderRadius: 6,
            }]
        },
        options: { ...chartDefaults, indexAxis: 'y', plugins: { legend: { display: false } } }
    });
});
</script>
@endpush
@endsection
