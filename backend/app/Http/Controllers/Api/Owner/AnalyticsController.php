<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Owner\AnalyticsSummaryResource;
use App\Http\Resources\Owner\MostSoldProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'closed');

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $totalOrders = (int) (clone $query)->count();

        $revenueData = (clone $query)->select(
            DB::raw('COALESCE(SUM(total_price), 0) as total_revenue'),
            DB::raw('COALESCE(AVG(total_price), 0) as avg_ticket')
        )->first();

        $totalItemsSold = (int) DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'closed')
            ->when($startDate !== null, fn ($q) => $q->where('orders.created_at', '>=', $startDate . ' 00:00:00'))
            ->when($endDate !== null, fn ($q) => $q->where('orders.created_at', '<=', $endDate . ' 23:59:59'))
            ->sum('order_items.quantity');

        $peakHours = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'closed')
            ->when($startDate !== null, fn ($q) => $q->where('created_at', '>=', $startDate . ' 00:00:00'))
            ->when($endDate !== null, fn ($q) => $q->where('created_at', '<=', $endDate . ' 23:59:59'))
            ->selectRaw("CAST(strftime('%H', created_at) AS INTEGER) as hour, COUNT(*) as order_count")
            ->groupByRaw("CAST(strftime('%H', created_at) AS INTEGER)")
            ->orderByRaw('order_count DESC')
            ->limit(5)
            ->get();

        $firstOrder = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'closed')
            ->when($startDate !== null, fn ($q) => $q->where('created_at', '>=', $startDate . ' 00:00:00'))
            ->when($endDate !== null, fn ($q) => $q->where('created_at', '<=', $endDate . ' 23:59:59'))
            ->orderBy('created_at', 'asc')
            ->value('created_at');

        $lastOrder = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'closed')
            ->when($startDate !== null, fn ($q) => $q->where('created_at', '>=', $startDate . ' 00:00:00'))
            ->when($endDate !== null, fn ($q) => $q->where('created_at', '<=', $endDate . ' 23:59:59'))
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        $startDateValue = $startDate !== null ? $startDate : ($firstOrder !== null ? \Carbon\Carbon::parse($firstOrder)->format('Y-m-d') : null);
        $endDateValue = $endDate !== null ? $endDate : ($lastOrder !== null ? \Carbon\Carbon::parse($lastOrder)->format('Y-m-d') : null);

        $resource = new AnalyticsSummaryResource([
            'total_revenue' => (float) ($revenueData->total_revenue ?? 0),
            'avg_ticket' => (float) ($revenueData->avg_ticket ?? 0),
            'total_orders' => $totalOrders,
            'total_items_sold' => $totalItemsSold,
            'date_range' => [
                'start' => $startDateValue,
                'end' => $endDateValue,
            ],
            'peak_hours' => $peakHours,
        ]);

        return response()->json(['data' => $resource->resolve()], 200);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $limit = min((int) $request->query('limit', 10), 50);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->select(
                'products.name as product_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByRaw('total_quantity DESC')
            ->limit($limit);

        if ($startDate !== null) {
            $query->where('orders.created_at', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate !== null) {
            $query->where('orders.created_at', '<=', $endDate . ' 23:59:59');
        }

        $products = $query->get()->map(fn ($item) => (array) $item);

        return response()->json([
            'data' => MostSoldProductResource::collection($products)->resolve(),
        ], 200);
    }

    public function exportCsv(Request $request)
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $filename = 'financial_export_' . now()->format('Y-m-d_His') . '.csv';

        ob_start();

        $handle = fopen('php://output', 'w');

        fputcsv($handle, [
            'order_id',
            'order_date',
            'table_number',
            'total_price',
            'status',
            'items_count',
            'items_total',
        ]);

        DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'closed')
            ->when($startDate !== null, fn ($q) => $q->where('created_at', '>=', $startDate . ' 00:00:00'))
            ->when($endDate !== null, fn ($q) => $q->where('created_at', '<=', $endDate . ' 23:59:59'))
            ->orderBy('created_at', 'asc')
            ->chunk(500, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    $itemsCount = DB::table('order_items')
                        ->where('order_id', $order->id)
                        ->count();

                    $itemsTotal = DB::table('order_items')
                        ->where('order_id', $order->id)
                        ->sum(DB::raw('quantity * unit_price'));

                    $tableNumber = DB::table('tables')
                        ->where('id', $order->table_id)
                        ->value('number');

                        $createdAt = $order->created_at;
                        if ($createdAt instanceof \Carbon\Carbon) {
                            $createdAt = $createdAt->toIso8601String();
                        } elseif (is_string($createdAt)) {
                            $createdAt = $createdAt;
                        } else {
                            $createdAt = '';
                        }

                        fputcsv($handle, [
                            $order->id,
                            $createdAt,
                            $tableNumber ?? '',
                            number_format((float) $order->total_price, 2, '.', ''),
                            $order->status,
                            $itemsCount,
                            number_format((float) $itemsTotal, 2, '.', ''),
                        ]);
                }
            });

        fclose($handle);

        $csvContent = ob_get_clean();

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache',
        ]);
    }
}
