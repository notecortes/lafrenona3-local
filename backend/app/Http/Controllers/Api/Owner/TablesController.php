<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreTableRequest;
use App\Http\Requests\Owner\UpdateTableRequest;
use App\Http\Resources\Owner\TableResource;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TablesController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $tables = Table::when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->get();

        return TableResource::collection($tables);
    }

    public function store(StoreTableRequest $request): TableResource
    {
        $table = Table::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'number' => $request->input('number'),
            'status' => $request->input('status', 'free'),
        ]);

        return new TableResource($table);
    }

    public function show(Table $table): TableResource
    {
        return new TableResource($table);
    }

    public function update(UpdateTableRequest $request, Table $table): TableResource
    {
        $table->update([
            'number' => $request->input('number', $table->number),
            'status' => $request->input('status', $table->status),
        ]);

        return new TableResource($table);
    }

    public function destroy(Table $table): JsonResponse
    {
        $table->delete();

        return response()->json(['message' => 'Table deleted.'], 200);
    }
}
