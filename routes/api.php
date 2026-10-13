<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BusinessAttributeController;
use App\Http\Controllers\Api\V1\BusinessAttributeValueController;
use App\Http\Controllers\Api\V1\BusinessBrandController;
use App\Http\Controllers\Api\V1\BusinessCategoryController;
use App\Http\Controllers\Api\V1\BusinessCollectionController;
use App\Http\Controllers\Api\V1\BusinessDashboardController;
use App\Http\Controllers\Api\V1\BusinessCouponController;
use App\Http\Controllers\Api\V1\BusinessDiscountController;
use App\Http\Controllers\Api\V1\BusinessInventoryController;
use App\Http\Controllers\Api\V1\BusinessOrderController;
use App\Http\Controllers\Api\V1\BusinessProductController;
use App\Http\Controllers\Api\V1\BusinessProductTypeController;
use App\Http\Controllers\Api\V1\BusinessStaffController;
use App\Http\Controllers\Api\V1\BusinessShippingMethodController;
use App\Http\Controllers\Api\V1\BusinessShippingZoneController;
use App\Http\Controllers\Api\V1\BusinessStoreController;
use App\Http\Controllers\Api\V1\BusinessSubscriptionController;
use App\Http\Controllers\Api\V1\BusinessTypeController;
use App\Http\Controllers\Api\V1\BusinessWebhookController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerPaymentMethodController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    Route::get("/health", function () {
        return response()->json(["status" => "Working"]);
    });
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('register-business', [AuthController::class, 'registerBusiness'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('login/guest', [AuthController::class, 'guestLogin'])->middleware('throttle:20,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
        Route::get('verify-email-link', [AuthController::class, 'verifyEmailLink'])->name('api.auth.verify-link')->middleware('throttle:10,1');
    });

    Route::get('business-types', [BusinessTypeController::class, 'index']);
    Route::get('plans', [PlanController::class, 'index']);
    Route::get('plans/{slug}', [PlanController::class, 'show']);

    Route::middleware('tenant')->group(function (): void {
        Route::get('store', [StoreController::class, 'show']);
        Route::get('store/pages', [StoreController::class, 'pages']);
        Route::get('store/pages/{slug}', [StoreController::class, 'page']);
        Route::get('store/banners', [StoreController::class, 'banners']);
        Route::get('store/shipping-methods', [StoreController::class, 'shippingMethods']);
        Route::get('store/payment-methods', [StoreController::class, 'paymentMethods']);
        Route::get('store/settings/public', [StoreController::class, 'publicSettings']);

        Route::get('products/search', [CatalogController::class, 'search']);
        Route::get('products/{id}/related', [CatalogController::class, 'relatedProducts'])->whereNumber('id');
        Route::get('products/{id}/reviews', [CatalogController::class, 'productReviews'])->whereNumber('id');
        Route::get('products', [CatalogController::class, 'products']);
        Route::get('products/{slug}', [CatalogController::class, 'product']);
        Route::get('categories', [CatalogController::class, 'categories']);
        Route::get('categories/{slug}/products', [CatalogController::class, 'categoryProducts']);
        Route::get('categories/{slug}', [CatalogController::class, 'category']);
        Route::get('brands', [CatalogController::class, 'brands']);
        Route::get('brands/{slug}', [CatalogController::class, 'brand']);
        Route::get('collections', [CatalogController::class, 'collections']);
        Route::get('collections/{slug}/products', [CatalogController::class, 'collectionProducts']);
        Route::get('collections/{slug}', [CatalogController::class, 'collection']);
        Route::get('tags', [CatalogController::class, 'tags']);
        Route::get('filters', [CatalogController::class, 'filters']);

        Route::middleware('auth.optional')->group(function (): void {
            Route::get('cart', [CartController::class, 'show']);
            Route::post('cart/items', [CartController::class, 'addItem']);
            Route::put('cart/items/{id}', [CartController::class, 'updateItem'])->whereNumber('id');
            Route::delete('cart/items/{id}', [CartController::class, 'removeItem'])->whereNumber('id');
            Route::delete('cart', [CartController::class, 'clear']);
            Route::post('cart/apply-coupon', [CartController::class, 'applyCoupon']);
            Route::delete('cart/remove-coupon', [CartController::class, 'removeCoupon']);
            Route::post('cart/estimate-shipping', [CartController::class, 'estimateShipping']);
            Route::post('cart/merge', [CartController::class, 'merge'])->middleware('auth:api');

            Route::post('checkout', [CheckoutController::class, 'checkout']);
            Route::post('checkout/calculate-totals', [CheckoutController::class, 'calculateTotals']);
            Route::get('checkout/{order_number}/status', [OrderController::class, 'checkoutStatus']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order_number}/tracking', [OrderController::class, 'tracking']);
            Route::post('orders/{order_number}/cancel', [OrderController::class, 'cancel']);
            Route::post('orders/{order_number}/return-request', [OrderController::class, 'requestReturn']);
            Route::get('orders/{order_number}', [OrderController::class, 'show']);

            Route::get('blog/posts', [ContentController::class, 'blogPosts']);
            Route::get('blog/posts/{slug}', [ContentController::class, 'blogPost']);
            Route::get('blog/categories', [ContentController::class, 'blogCategories']);
            Route::post('newsletter/subscribe', [ContentController::class, 'subscribe'])->middleware('throttle:10,1');
            Route::post('newsletter/unsubscribe', [ContentController::class, 'unsubscribe'])->middleware('throttle:10,1');
            Route::post('contact', [ContentController::class, 'contact'])->middleware('throttle:5,1');
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('auth/password', [AuthController::class, 'updatePassword']);
        Route::post('auth/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('auth/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:5,1');
        Route::post('auth/switch-store', [AuthController::class, 'switchStore']);

        Route::get('customer/profile', [CustomerController::class, 'profile']);
        Route::put('customer/profile', [CustomerController::class, 'updateProfile']);
        Route::get('customer/addresses', [CustomerController::class, 'addresses']);
        Route::post('customer/addresses', [CustomerController::class, 'createAddress']);
        Route::get('customer/addresses/{id}', [CustomerController::class, 'showAddress'])->whereNumber('id');
        Route::put('customer/addresses/{id}', [CustomerController::class, 'updateAddress'])->whereNumber('id');
        Route::delete('customer/addresses/{id}', [CustomerController::class, 'deleteAddress'])->whereNumber('id');
        Route::put('customer/addresses/{id}/default-shipping', [CustomerController::class, 'setDefaultShipping'])->whereNumber('id');
        Route::put('customer/addresses/{id}/default-billing', [CustomerController::class, 'setDefaultBilling'])->whereNumber('id');
        Route::get('customer/payment-methods', [CustomerPaymentMethodController::class, 'index']);
        Route::post('customer/payment-methods', [CustomerPaymentMethodController::class, 'store']);
        Route::delete('customer/payment-methods/{id}', [CustomerPaymentMethodController::class, 'destroy'])->whereNumber('id');
        Route::put('customer/payment-methods/{id}/default', [CustomerPaymentMethodController::class, 'setDefault'])->whereNumber('id');

        Route::middleware('tenant')->group(function (): void {
            Route::get('customer/notification-preferences', [CustomerController::class, 'notificationPreferences']);
            Route::put('customer/notification-preferences', [CustomerController::class, 'updateNotificationPreferences']);
            Route::get('customer/orders', [CustomerController::class, 'orders']);
            Route::get('customer/orders/{order_number}', [CustomerController::class, 'order']);
            Route::post('customer/orders/{order_number}/cancel', [CustomerController::class, 'cancelOrder']);

            Route::get('customer/reviews', [ReviewController::class, 'customerReviews']);
            Route::post('reviews', [ReviewController::class, 'store']);
            Route::put('reviews/{id}', [ReviewController::class, 'update'])->whereNumber('id');
            Route::delete('reviews/{id}', [ReviewController::class, 'destroy'])->whereNumber('id');
            Route::post('reviews/{id}/vote', [ReviewController::class, 'vote'])->whereNumber('id');

            Route::get('wishlists', [WishlistController::class, 'index']);
            Route::post('wishlists', [WishlistController::class, 'store']);
            Route::get('wishlists/{id}', [WishlistController::class, 'show'])->whereNumber('id');
            Route::put('wishlists/{id}', [WishlistController::class, 'update'])->whereNumber('id');
            Route::delete('wishlists/{id}', [WishlistController::class, 'destroy'])->whereNumber('id');
            Route::post('wishlists/{id}/items', [WishlistController::class, 'addItem'])->whereNumber('id');
            Route::delete('wishlists/{id}/items/{item_id}', [WishlistController::class, 'removeItem'])->whereNumber('id')->whereNumber('item_id');
        });

        Route::middleware(['tenant', 'business.scope'])->prefix('dashboard')->group(function (): void {
            Route::get('stats', [BusinessDashboardController::class, 'stats']);
            Route::get('recent-orders', [BusinessDashboardController::class, 'recentOrders']);
            Route::get('top-products', [BusinessDashboardController::class, 'topProducts']);
            Route::get('alerts', [BusinessDashboardController::class, 'alerts']);
            Route::get('sales-chart', [BusinessDashboardController::class, 'salesChart']);
        });

        Route::prefix('business')->middleware(['tenant', 'business.scope'])->group(function (): void {
            Route::get('dashboard/stats', [BusinessDashboardController::class, 'stats']);
            Route::get('dashboard/recent-orders', [BusinessDashboardController::class, 'recentOrders']);
            Route::get('dashboard/top-products', [BusinessDashboardController::class, 'topProducts']);
            Route::get('dashboard/alerts', [BusinessDashboardController::class, 'alerts']);
            Route::get('dashboard/sales-chart', [BusinessDashboardController::class, 'salesChart']);

            Route::middleware('business.manager')->group(function (): void {
                Route::get('store', [BusinessStoreController::class, 'show']);
                Route::put('store', [BusinessStoreController::class, 'update']);
                Route::put('store/settings', [BusinessStoreController::class, 'updateSettings']);
                Route::put('store/theme', [BusinessStoreController::class, 'updateTheme']);
                Route::post('store/logo', [BusinessStoreController::class, 'uploadLogo']);
                Route::post('store/banner', [BusinessStoreController::class, 'uploadBanner']);
                Route::get('store/analytics', [BusinessStoreController::class, 'analytics']);

                Route::get('staff', [BusinessStaffController::class, 'index']);
                Route::post('staff', [BusinessStaffController::class, 'store']);
                Route::get('staff/{id}', [BusinessStaffController::class, 'show'])->whereNumber('id');
                Route::put('staff/{id}', [BusinessStaffController::class, 'update'])->whereNumber('id');
                Route::delete('staff/{id}', [BusinessStaffController::class, 'destroy'])->whereNumber('id');
                Route::post('staff/{id}/assign-role', [BusinessStaffController::class, 'assignRole'])->whereNumber('id');
                Route::post('staff/{id}/toggle-status', [BusinessStaffController::class, 'toggleStatus'])->whereNumber('id');

                Route::get('categories/tree', [BusinessCategoryController::class, 'tree']);
                Route::put('categories/reorder', [BusinessCategoryController::class, 'reorder']);
                Route::apiResource('categories', BusinessCategoryController::class)->except(['create', 'edit']);
                Route::apiResource('brands', BusinessBrandController::class)->except(['create', 'edit']);
                Route::apiResource('product-types', BusinessProductTypeController::class)->except(['create', 'edit']);
                Route::get('attributes/{id}/values', [BusinessAttributeValueController::class, 'index'])->whereNumber('id');
                Route::post('attributes/{id}/values', [BusinessAttributeValueController::class, 'store'])->whereNumber('id');
                Route::put('attribute-values/{id}', [BusinessAttributeValueController::class, 'update'])->whereNumber('id');
                Route::delete('attribute-values/{id}', [BusinessAttributeValueController::class, 'destroy'])->whereNumber('id');
                Route::apiResource('attributes', BusinessAttributeController::class)->except(['create', 'edit']);
                Route::apiResource('collections', BusinessCollectionController::class)->except(['create', 'edit']);
                Route::apiResource('coupons', BusinessCouponController::class)->except(['create', 'edit']);
                Route::apiResource('discounts', BusinessDiscountController::class)->except(['create', 'edit']);
                Route::apiResource('shipping-zones', BusinessShippingZoneController::class)->except(['create', 'edit']);
                Route::apiResource('shipping-methods', BusinessShippingMethodController::class)->except(['create', 'edit']);
                Route::post('collections/{id}/products', [BusinessCollectionController::class, 'attachProducts'])->whereNumber('id');
                Route::delete('collections/{id}/products/{product_id}', [BusinessCollectionController::class, 'detachProduct'])->whereNumber('id')->whereNumber('product_id');

                Route::post('products/bulk-update', [BusinessProductController::class, 'bulkUpdate']);
                Route::post('products/import', [BusinessProductController::class, 'import']);
                Route::get('products/export', [BusinessProductController::class, 'export']);
                Route::get('products', [BusinessProductController::class, 'index']);
                Route::post('products', [BusinessProductController::class, 'store']);
                Route::get('products/{id}/variants', [BusinessProductController::class, 'variants'])->whereNumber('id');
                Route::post('products/{id}/variants', [BusinessProductController::class, 'createVariant'])->whereNumber('id');
                Route::post('products/{id}/images', [BusinessProductController::class, 'uploadImages'])->whereNumber('id');
                Route::delete('products/{id}/images/{image_id}', [BusinessProductController::class, 'deleteImage'])->whereNumber('id')->whereNumber('image_id');
                Route::put('products/{id}/images/reorder', [BusinessProductController::class, 'reorderImages'])->whereNumber('id');
                Route::put('products/{id}/publish', [BusinessProductController::class, 'publish'])->whereNumber('id');
                Route::put('products/{id}/unpublish', [BusinessProductController::class, 'unpublish'])->whereNumber('id');
                Route::post('products/{id}/duplicate', [BusinessProductController::class, 'duplicate'])->whereNumber('id');
                Route::get('products/{id}', [BusinessProductController::class, 'show'])->whereNumber('id');
                Route::put('products/{id}', [BusinessProductController::class, 'update'])->whereNumber('id');
                Route::delete('products/{id}', [BusinessProductController::class, 'destroy'])->whereNumber('id');
                Route::put('variants/{id}', [BusinessProductController::class, 'updateVariant'])->whereNumber('id');
                Route::delete('variants/{id}', [BusinessProductController::class, 'deleteVariant'])->whereNumber('id');

                Route::get('inventory', [BusinessInventoryController::class, 'index']);
                Route::get('inventory/locations', [BusinessInventoryController::class, 'locations']);
                Route::post('inventory/locations', [BusinessInventoryController::class, 'createLocation']);
                Route::put('inventory/locations/{id}', [BusinessInventoryController::class, 'updateLocation'])->whereNumber('id');
                Route::delete('inventory/locations/{id}', [BusinessInventoryController::class, 'deleteLocation'])->whereNumber('id');
                Route::post('inventory/adjust', [BusinessInventoryController::class, 'adjust']);
                Route::get('inventory/movements', [BusinessInventoryController::class, 'movements']);
                Route::get('inventory/low-stock', [BusinessInventoryController::class, 'lowStock']);

                Route::get('orders', [BusinessOrderController::class, 'index']);
                Route::get('orders/{id}/timeline', [BusinessOrderController::class, 'timeline'])->whereNumber('id');
                Route::put('orders/{id}/status', [BusinessOrderController::class, 'updateStatus'])->whereNumber('id');
                Route::post('orders/{id}/fulfill', [BusinessOrderController::class, 'fulfill'])->whereNumber('id');
                Route::post('orders/{id}/ship', [BusinessOrderController::class, 'ship'])->whereNumber('id');
                Route::post('orders/{id}/deliver', [BusinessOrderController::class, 'deliver'])->whereNumber('id');
                Route::post('orders/{id}/cancel', [BusinessOrderController::class, 'cancel'])->whereNumber('id');
                Route::post('orders/{id}/mark-paid', [BusinessOrderController::class, 'markPaid'])->whereNumber('id');
                Route::post('orders/{id}/refund', [BusinessOrderController::class, 'refund'])->whereNumber('id');
                Route::post('orders/{id}/notes', [BusinessOrderController::class, 'addNotes'])->whereNumber('id');
                Route::get('orders/{id}', [BusinessOrderController::class, 'show'])->whereNumber('id');

                Route::get('subscription', [BusinessSubscriptionController::class, 'show']);
                Route::post('subscription', [BusinessSubscriptionController::class, 'subscribe']);
                Route::put('subscription/plan', [BusinessSubscriptionController::class, 'changePlan']);
                Route::post('subscription/cancel', [BusinessSubscriptionController::class, 'cancel']);
                Route::get('subscription/invoices', [BusinessSubscriptionController::class, 'invoices']);

                Route::get('webhooks', [BusinessWebhookController::class, 'index']);
                Route::post('webhooks', [BusinessWebhookController::class, 'store']);
                Route::put('webhooks/{id}', [BusinessWebhookController::class, 'update'])->whereNumber('id');
                Route::post('webhooks/{id}/rotate-secret', [BusinessWebhookController::class, 'rotateSecret'])->whereNumber('id');
                Route::delete('webhooks/{id}', [BusinessWebhookController::class, 'destroy'])->whereNumber('id');
            });
        });

        Route::prefix('admin')->middleware('admin')->group(function (): void {
            Route::get('businesses', [AdminController::class, 'businesses']);
            Route::put('businesses/{id}/status', [AdminController::class, 'updateBusinessStatus'])->whereNumber('id');
            Route::get('users', [AdminController::class, 'users']);
            Route::put('users/{id}/status', [AdminController::class, 'updateUserStatus'])->whereNumber('id');
        });
    });
});
