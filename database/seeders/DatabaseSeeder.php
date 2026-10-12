<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Plan;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'store.manage', 'staff.manage', 'catalog.manage', 'inventory.manage',
            'orders.manage', 'orders.fulfill', 'customers.manage', 'reports.view',
            'reviews.moderate', 'webhooks.manage', 'platform.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $rolePermissions = [
            'admin' => $permissions,
            'business' => ['store.manage', 'staff.manage', 'catalog.manage', 'inventory.manage', 'orders.manage', 'orders.fulfill', 'customers.manage', 'reports.view', 'reviews.moderate', 'webhooks.manage'],
            'seller' => ['catalog.manage', 'inventory.manage', 'orders.manage', 'orders.fulfill', 'customers.manage'],
            'customer' => [],
        ];

        foreach ($rolePermissions as $roleName => $allowed) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            $role->syncPermissions($allowed);
        }

        $plans = [
            [
                'name' => 'Starter', 'slug' => 'starter', 'description' => 'For new independent stores.',
                'price' => 0, 'billing_cycle' => 'monthly', 'trial_days' => 0,
                'features' => ['Online storefront', 'Order management', 'Basic analytics'],
                'limits' => ['max_products' => 100, 'max_staff' => 2, 'max_locations' => 1],
                'is_active' => true, 'is_featured' => false, 'sort_order' => 1,
            ],
            [
                'name' => 'Growth', 'slug' => 'growth', 'description' => 'For growing commerce teams.',
                'price' => 49, 'billing_cycle' => 'monthly', 'trial_days' => 14,
                'features' => ['Everything in Starter', 'Advanced analytics', 'Discounts and collections'],
                'limits' => ['max_products' => 5000, 'max_staff' => 10, 'max_locations' => 5],
                'is_active' => true, 'is_featured' => true, 'sort_order' => 2,
            ],
            [
                'name' => 'Scale', 'slug' => 'scale', 'description' => 'For established multi-channel stores.',
                'price' => 199, 'billing_cycle' => 'monthly', 'trial_days' => 14,
                'features' => ['Everything in Growth', 'Priority support', 'Custom integrations'],
                'limits' => ['max_products' => 50000, 'max_staff' => 50, 'max_locations' => 25],
                'is_active' => true, 'is_featured' => false, 'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $adminEmail = mb_strtolower(trim((string) env('SHOPLINE_ADMIN_EMAIL', '')));
        if ($adminEmail !== '') {
            $admin = User::query()->where('email', $adminEmail)->first();
            if ($admin !== null && ! $admin->hasRole('admin', 'api')) {
                $admin->assignRole(Role::findOrCreate('admin', 'api'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
