<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        // Backward compatibility: old admins may have NULL role.
        if ($admin === null || $admin->role === 'coordinator') {
            abort(403, 'Ushbu bo‘lim faqat super admin uchun.');
        }

        return $next($request);
    }
}

