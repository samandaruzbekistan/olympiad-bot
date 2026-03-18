<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subject::query()->withCount('users', 'olympiads');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $subjects = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.subjects.index', compact('subjects'));
    }

    public function create(): View
    {
        return view('admin.subjects.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255|unique:subjects,name']);
        Subject::create($validated);

        return redirect()->route('admin.subjects.index')->with('success', 'Fan yaratildi.');
    }

    public function edit(Subject $subject): View
    {
        return view('admin.subjects.edit', compact('subject'));
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name,' . $subject->id,
        ]);
        $subject->update($validated);

        return redirect()->route('admin.subjects.index')->with('success', 'Fan yangilandi.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->users()->detach();
        $subject->olympiads()->detach();
        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('success', 'Fan o\'chirildi.');
    }
}
