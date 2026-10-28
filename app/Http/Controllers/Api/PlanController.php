<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::where('active', true)
            ->orderBy('sort')
            ->get(['id', 'name', 'slug', 'description', 'price', 'trial_days', 'features']);

        return response()->json(['data' => $plans]);
    }
}

