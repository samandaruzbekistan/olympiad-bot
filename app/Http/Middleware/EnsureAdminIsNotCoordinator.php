<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsNotCoordinator
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if ($admin !== null && $admin->role === 'coordinator') {
            return redirect()->route('admin.coordinator.dashboard')
                ->with('error', 'Siz faqat koordinatori panelidan foydalana olasiz.');
        }

        return $next($request);
    }
}

