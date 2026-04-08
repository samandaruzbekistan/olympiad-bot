<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoordinatorManagementController extends Controller
{
    public function index(Request $request): View
    {
        $query = Admin::query()->with('region')->where('role', 'coordinator');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $coordinators = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.coordinators.index', compact('coordinators'));
    }

    public function create(): View
    {
        $regions = Region::orderBy('name_uz')->get();
        return view('admin.coordinators.create', compact('regions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admins,email',
            'password' => 'required|string|min:6|confirmed',
            'region_id' => 'required|exists:regions,id',
        ]);

        Admin::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'coordinator',
            'region_id' => $data['region_id'],
        ]);

        return redirect()->route('admin.coordinators.index')->with('success', 'Koordinator yaratildi.');
    }

    public function edit(Admin $coordinator): View
    {
        abort_unless($coordinator->role === 'coordinator', 404);

        $regions = Region::orderBy('name_uz')->get();
        return view('admin.coordinators.edit', compact('coordinator', 'regions'));
    }

    public function update(Request $request, Admin $coordinator): RedirectResponse
    {
        abort_unless($coordinator->role === 'coordinator', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admins,email,' . $coordinator->id,
            'password' => 'nullable|string|min:6|confirmed',
            'region_id' => 'required|exists:regions,id',
        ]);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'region_id' => $data['region_id'],
        ];

        if (! empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        $coordinator->update($update);

        return redirect()->route('admin.coordinators.index')->with('success', 'Koordinator yangilandi.');
    }

    public function destroy(Admin $coordinator): RedirectResponse
    {
        abort_unless($coordinator->role === 'coordinator', 404);
        $coordinator->delete();

        return redirect()->route('admin.coordinators.index')->with('success', 'Koordinator o\'chirildi.');
    }
}

