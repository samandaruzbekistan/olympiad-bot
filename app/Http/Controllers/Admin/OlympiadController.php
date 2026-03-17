<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OlympiadController extends Controller
{
    public function index(Request $request): View
    {
        $query = Olympiad::query()->withCount('registrations')->with('subjects');

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $olympiads = $query->orderByDesc('start_date')->paginate(15)->withQueryString();

        return view('admin.olympiads.index', compact('olympiads'));
    }

    public function create(): View
    {
        $subjects = Subject::orderBy('name')->get();
        return view('admin.olympiads.create', compact('subjects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'price' => 'required|integer|min:0',
            'start_date' => 'required|date',
            'location_name' => 'required|string|max:255',
            'location_address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:draft,active,closed',
        ]);
        $subjectIds = $validated['subject_ids'] ?? [];
        unset($validated['subject_ids']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('olympiads', 'public');
        }

        $olympiad = Olympiad::create($validated);
        $olympiad->subjects()->sync($subjectIds);
        return redirect()->route('admin.olympiads.index')->with('success', 'Olimpiada yaratildi.');
    }
}
