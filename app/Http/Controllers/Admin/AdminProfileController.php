<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function edit(): View
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 403);
        return view('admin.profile.edit', compact('admin'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admins,email,' . $admin->id,
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $admin->update($payload);

        return redirect()->route('admin.profile.edit')->with('success', 'Profil yangilandi.');
    }
}

