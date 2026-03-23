<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OlympiadType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OlympiadTypeController extends Controller
{
    public function index(Request $request): View
    {
        $query = OlympiadType::query()->withCount('olympiads');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $types = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.olympiad-types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.olympiad-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255|unique:olympiad_types,name']);
        OlympiadType::create($validated);

        return redirect()->route('admin.olympiad-types.index')->with('success', 'Tur yaratildi.');
    }

    public function edit(OlympiadType $olympiadType): View
    {
        return view('admin.olympiad-types.edit', compact('olympiadType'));
    }

    public function update(Request $request, OlympiadType $olympiadType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:olympiad_types,name,' . $olympiadType->id,
        ]);
        $olympiadType->update($validated);

        return redirect()->route('admin.olympiad-types.index')->with('success', 'Tur yangilandi.');
    }

    public function destroy(OlympiadType $olympiadType): RedirectResponse
    {
        $olympiadType->olympiads()->update(['type_id' => null]);
        $olympiadType->delete();

        return redirect()->route('admin.olympiad-types.index')->with('success', "Tur o'chirildi.");
    }
}
