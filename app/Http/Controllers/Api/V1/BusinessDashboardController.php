<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\CustomerBusiness;
use App\Models\Order;
use App\Models\Product;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BusinessDashboardController extends BaseController
{
    public function stats(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $orders = Order::query()->forBusiness($business->id);
        $paid = (clone $orders)->where('payment_status', 'paid');
        $from = now()->subDays(29)->startOfDay();

        return $this->successResponse([
            'orders_total' => (clone $orders)->count(),
            'orders_pending' => (clone $orders)->where('status', 'pending')->count(),
            'orders_today' => (clone $orders)->whereDate('created_at', today())->count(),
            'revenue_total' => round((float) (clone $paid)->sum('grand_total'), 2),
            'revenue_30_days' => round((float) (clone $paid)->where('created_at', '>=', $from)->sum('grand_total'), 2),
            'average_order_value' => round((float) ((clone $paid)->avg('grand_total') ?? 0), 2),
            'products_active' => Product::query()->forBusiness($business->id)->where('is_active', true)->count(),
            'products_draft' => Product::query()->forBusiness($business->id)->where('is_active', false)->count(),
            'customers' => CustomerBusiness::query()->where('business_id', $business->id)->count(),
        ], 'Dashboard statistics retrieved successfully.');
    }

    public function recentOrders(Request $request): JsonResponse
    {
        $orders = Order::query()->forBusiness($this->business($request)->id)->with(['items', 'shipments'])
            ->latest('created_at')->limit(min(max((int) $request->integer('limit', 10), 1), 50))->get();

        return $this->successResponse(OrderResource::collection($orders), 'Recent orders retrieved successfully.');
    }

    public function topProducts(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.business_id', $business->id)
            ->whereIn('orders.payment_status', ['paid', 'partially_paid'])
            ->whereNull('orders.deleted_at')
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.product_sku', 'products.slug')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit(min(max((int) $request->integer('limit', 10), 1), 50))
            ->get([
                'order_items.product_id', 'order_items.product_name', 'order_items.product_sku', 'products.slug',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.total) as revenue'),
            ]);

        return $this->successResponse($rows, 'Top products retrieved successfully.');
    }

    public function alerts(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $lowStock = Product::query()->forBusiness($business->id)->where('track_inventory', true)
            ->withSum('inventoryItems', 'available_quantity')->get(['id', 'name', 'sku', 'stock', 'low_stock_threshold'])
            ->filter(fn (Product $product): bool => $product->available_stock <= (int) $product->low_stock_threshold)
            ->take(20)->map(fn (Product $product): array => [
                'type' => 'low_stock', 'product_id' => $product->id, 'name' => $product->name,
                'available_quantity' => $product->available_stock, 'threshold' => $product->low_stock_threshold,
            ])->values();
        $pendingOrders = Order::query()->forBusiness($business->id)->whereIn('status', ['pending', 'confirmed'])->count();

        return $this->successResponse([
            'low_stock_products' => $lowStock,
            'pending_orders' => $pendingOrders,
            'inactive_store' => $business->status !== 'active',
        ], 'Dashboard alerts retrieved successfully.');
    }

    public function salesChart(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'in:7,30,90,365']]);
        $business = $this->business($request);
        $days = (int) ($data['days'] ?? 30);
        $start = now()->subDays($days - 1)->startOfDay();
        $sales = Order::query()->forBusiness($business->id)->where('payment_status', 'paid')->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(grand_total) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();

        return $this->successResponse(['days' => $days, 'data' => $sales], 'Sales chart retrieved successfully.');
    }

    private function business(Request $request): Business
    {
        $business = $request->attributes->get('tenant');
        if (! $business instanceof Business) {
            throw new ApiException('STORE_NOT_FOUND', 'Store context is required.', 404);
        }

        return $business;
    }
}
