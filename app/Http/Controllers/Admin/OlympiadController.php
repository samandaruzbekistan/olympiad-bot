<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Olympiad;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OlympiadController extends Controller
{
    private function validationRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'price' => 'required|integer|min:0',
            'capacity' => 'nullable|integer|min:0',
            'start_date' => 'required|date',
            'location_name' => 'required|string|max:255',
            'location_address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:draft,active,closed',
        ];
    }

    public function index(Request $request): View
    {
        $query = Olympiad::query()->withCount('registrations')->with('subjects');

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
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
        $validated = $request->validate($this->validationRules());
        $subjectIds = $validated['subject_ids'] ?? [];
        unset($validated['subject_ids']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('olympiads', 'public');
        }

        $olympiad = Olympiad::create($validated);
        $olympiad->subjects()->sync($subjectIds);

        return redirect()->route('admin.olympiads.index')->with('success', 'Olimpiada yaratildi.');
    }

    public function edit(Olympiad $olympiad): View
    {
        $subjects = Subject::orderBy('name')->get();
        $olympiad->load('subjects');

        return view('admin.olympiads.edit', compact('olympiad', 'subjects'));
    }

    public function update(Request $request, Olympiad $olympiad): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());
        $subjectIds = $validated['subject_ids'] ?? [];
        unset($validated['subject_ids']);

        if ($request->hasFile('logo')) {
            if ($olympiad->logo) {
                Storage::disk('public')->delete($olympiad->logo);
            }
            $validated['logo'] = $request->file('logo')->store('olympiads', 'public');
        }

        $olympiad->update($validated);
        $olympiad->subjects()->sync($subjectIds);

        return redirect()->route('admin.olympiads.index')->with('success', 'Olimpiada yangilandi.');
    }

    public function destroy(Olympiad $olympiad): RedirectResponse
    {
        if ($olympiad->logo) {
            Storage::disk('public')->delete($olympiad->logo);
        }
        $olympiad->subjects()->detach();
        $olympiad->delete();

        return redirect()->route('admin.olympiads.index')->with('success', 'Olimpiada o\'chirildi.');
    }
}
