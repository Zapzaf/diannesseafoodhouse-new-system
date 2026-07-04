<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve a safe pagination size from the request, clamped to [1, 100].
     */
    protected function perPage(Request $request, int $default = 20): int
    {
        return max(1, min((int) $request->input('per_page', $default), 100));
    }
}
