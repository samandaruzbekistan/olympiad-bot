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
        $query = Subject::query();

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
        $validated = $request->validate(['name' => 'required|string|max:255']);
        Subject::create($validated);
        return redirect()->route('admin.subjects.index')->with('success', 'Fan yaratildi.');
    }
}
