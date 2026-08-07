<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\FiscalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $perPage = $request->query('per_page', 15);
        $perPage = min((int) $perPage, 100);

        $query = FiscalRecord::where('restaurant_id', $restaurantId)
            ->orderBy('sequence_number', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        $records = $query->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ], 200);
    }
}
