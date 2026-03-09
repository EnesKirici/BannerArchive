<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParticleTheme;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * Public API endpoint — frontend particles background config
     */
    public function getActiveThemeConfig(): JsonResponse
    {
        $theme = ParticleTheme::active();

        if (! $theme) {
            return response()->json(['config' => null]);
        }

        return response()->json(['config' => $theme->config]);
    }
}
