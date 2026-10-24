<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Queries\HouseholdDirectoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdDirectoryController extends Controller
{
    public function index(Request $request, HouseholdDirectoryQuery $query): JsonResponse
    {
        return response()->json(['data' => $query->page($request)]);
    }
}
