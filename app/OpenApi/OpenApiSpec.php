<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.3',
    info: new OA\Info(
        title: 'ShopLine REST API',
        version: '1.0.0',
        description: 'Multi-tenant commerce API. Set X-Store-Slug (or use a configured store domain) for tenant-scoped endpoints. All responses use the ShopLine response envelope.',
    ),
    servers: [new OA\Server(url: '/', description: 'API root; paths include /api/v1')],
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Bearer access token issued by Laravel Passport',
)]
#[OA\Tag(name: 'Auth', description: 'Passport registration, login, token rotation, and verification')]
#[OA\Tag(name: 'Storefront', description: 'Tenant store configuration and content')]
#[OA\Tag(name: 'Products', description: 'Tenant-scoped catalog and full-text search')]
#[OA\Tag(name: 'Categories', description: 'Product categories')]
#[OA\Tag(name: 'Brands', description: 'Product brands')]
#[OA\Tag(name: 'Collections', description: 'Curated product collections')]
#[OA\Tag(name: 'Cart', description: 'Guest and authenticated shopping carts')]
#[OA\Tag(name: 'Checkout', description: 'Order placement and checkout totals')]
#[OA\Tag(name: 'Orders', description: 'Customer and business order lifecycle')]
#[OA\Tag(name: 'Customers', description: 'Customer profile, addresses, preferences, and payment tokens')]
#[OA\Tag(name: 'Reviews', description: 'Product reviews and votes')]
#[OA\Tag(name: 'Coupons', description: 'Promotion codes')]
#[OA\Tag(name: 'Shipping', description: 'Shipping zones and methods')]
#[OA\Tag(name: 'Payments', description: 'Payment records; gateway integrations are deliberately decoupled')]
#[OA\Tag(name: 'Wishlist', description: 'Customer wishlists')]
#[OA\Tag(name: 'Blog', description: 'Store blog content')]
#[OA\Tag(name: 'Pages', description: 'Published storefront pages')]
#[OA\Tag(name: 'Banners', description: 'Storefront banners')]
#[OA\Tag(name: 'Newsletter', description: 'Newsletter and contact subscriptions')]
#[OA\Tag(name: 'Staff', description: 'Business staff management')]
#[OA\Tag(name: 'Reports', description: 'Tenant-specific dashboard metrics')]
#[OA\Tag(name: 'Subscriptions', description: 'Plans and store subscriptions')]
#[OA\Tag(name: 'Webhooks', description: 'Tenant event webhooks')]
#[OA\Tag(name: 'Admin', description: 'Platform administration')]
#[OA\Schema(
    schema: 'ApiSuccess',
    type: 'object',
    required: ['success', 'message', 'data', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Products retrieved successfully'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
        new OA\Property(property: 'meta', ref: '#/components/schemas/ApiMeta'),
    ],
)]
#[OA\Schema(
    schema: 'ApiMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-10-12T10:30:00.000000Z'),
        new OA\Property(property: 'request_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'store', type: 'string', nullable: true, example: 'my-store'),
    ],
)]
#[OA\Schema(
    schema: 'ApiError',
    type: 'object',
    required: ['success', 'message', 'error', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(property: 'error', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
            new OA\Property(property: 'details', type: 'object', additionalProperties: true),
        ], type: 'object'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/ApiMeta'),
    ],
)]
#[OA\Schema(
    schema: 'User',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'name', type: 'string', example: 'Alex Rivera'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
    ],
)]
#[OA\Schema(
    schema: 'Business',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'Northwind Goods'),
        new OA\Property(property: 'slug', type: 'string', example: 'northwind-goods'),
        new OA\Property(property: 'business_type', type: 'string', example: 'home'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
    ],
)]
#[OA\Schema(
    schema: 'ProductVariant',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'Blue / Medium'),
        new OA\Property(property: 'sku', type: 'string'),
        new OA\Property(property: 'price', type: 'string', example: '24.99'),
        new OA\Property(property: 'option_values', type: 'array', items: new OA\Items(type: 'object')),
    ],
)]
#[OA\Schema(
    schema: 'Product',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'Everyday Backpack'),
        new OA\Property(property: 'slug', type: 'string', example: 'everyday-backpack'),
        new OA\Property(property: 'sku', type: 'string', example: 'BAG-001'),
        new OA\Property(property: 'price', type: 'string', example: '59.00'),
        new OA\Property(property: 'in_stock', type: 'boolean', example: true),
        new OA\Property(property: 'variants', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductVariant')),
    ],
)]
#[OA\Schema(
    schema: 'Cart',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'subtotal', type: 'string', example: '59.00'),
        new OA\Property(property: 'grand_total', type: 'string', example: '64.00'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
    ],
)]
#[OA\Schema(
    schema: 'Order',
    type: 'object',
    properties: [
        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-8AB93D27'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'payment_status', type: 'string', example: 'pending'),
        new OA\Property(property: 'grand_total', type: 'string', example: '64.00'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
    ],
)]
#[OA\Schema(
    schema: 'Coupon',
    type: 'object',
    properties: [
        new OA\Property(property: 'code', type: 'string', example: 'WELCOME10'),
        new OA\Property(property: 'type', type: 'string', example: 'percentage'),
        new OA\Property(property: 'value', type: 'number', format: 'float', example: 10),
    ],
)]
#[OA\Schema(
    schema: 'Review',
    type: 'object',
    properties: [
        new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 5),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'comment', type: 'string', nullable: true),
        new OA\Property(property: 'is_verified_purchase', type: 'boolean'),
    ],
)]
final class OpenApiSpec
{
}
