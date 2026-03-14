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
        $query = Olympiad::query()->withCount('registrations')->with('subject');

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
            'subject_id' => 'nullable|exists:subjects,id',
            'price' => 'required|integer|min:0',
            'start_date' => 'required|date',
            'location_name' => 'required|string|max:255',
            'location_address' => 'nullable|string|max:255',
            'status' => 'required|in:draft,active,closed',
        ]);
        Olympiad::create($validated);
        return redirect()->route('admin.olympiads.index')->with('success', 'Olympiad created.');
    }
}
