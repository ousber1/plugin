<?php

declare(strict_types=1);

/*
 * Role-based access control definition.
 * Seeded into roles / permissions / role_permissions. The super_admin role bypasses every check.
 */
return [
    'permissions' => [
        'dashboard' => ['dashboard.view'],
        'products' => ['products.view', 'products.manage', 'categories.manage', 'collections.manage'],
        'customers' => ['customers.view', 'customers.manage'],
        'orders' => ['orders.view', 'orders.manage', 'orders.status', 'orders.create'],
        'artwork' => ['artwork.view', 'artwork.approve', 'artwork.delete'],
        'production' => ['production.view', 'production.manage'],
        'inventory' => ['inventory.view', 'inventory.manage', 'suppliers.manage', 'purchases.manage'],
        'documents' => ['invoices.view', 'invoices.manage', 'quotations.view', 'quotations.manage'],
        'finance' => ['payments.view', 'payments.manage', 'expenses.manage', 'finance.view', 'reports.view'],
        'marketing' => ['coupons.manage', 'marketing.manage', 'reviews.manage', 'newsletter.manage'],
        'shipping' => ['shipping.manage', 'shipments.manage'],
        'system' => ['settings.manage', 'users.manage', 'roles.manage', 'logs.view', 'backups.manage'],
    ],

    'roles' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Full unrestricted access',
            'permissions' => ['*'],
        ],
        'administrator' => [
            'name' => 'Administrator',
            'description' => 'Manages the whole business except roles',
            'permissions' => ['*', '!roles.manage'],
        ],
        'sales_manager' => [
            'name' => 'Sales Manager',
            'description' => 'Orders, customers, quotations, marketing',
            'permissions' => [
                'dashboard.view', 'products.view', 'customers.view', 'customers.manage',
                'orders.view', 'orders.manage', 'orders.status', 'orders.create', 'artwork.view',
                'quotations.view', 'quotations.manage', 'invoices.view', 'payments.view',
                'coupons.manage', 'marketing.manage', 'reviews.manage', 'newsletter.manage',
                'shipping.manage', 'shipments.manage', 'reports.view',
            ],
        ],
        'designer' => [
            'name' => 'Designer',
            'description' => 'Catalogue and customer artwork preparation',
            'permissions' => [
                'dashboard.view', 'products.view', 'products.manage', 'categories.manage', 'collections.manage',
                'orders.view', 'artwork.view', 'artwork.approve', 'production.view',
            ],
        ],
        'production_manager' => [
            'name' => 'Production Manager',
            'description' => 'Production workflow, machines and inventory',
            'permissions' => [
                'dashboard.view', 'orders.view', 'orders.status', 'artwork.view', 'artwork.approve',
                'production.view', 'production.manage', 'inventory.view', 'inventory.manage',
                'suppliers.manage', 'purchases.manage', 'shipments.manage',
            ],
        ],
        'accountant' => [
            'name' => 'Accountant',
            'description' => 'Invoices, payments, expenses and reports',
            'permissions' => [
                'dashboard.view', 'customers.view', 'orders.view', 'invoices.view', 'invoices.manage',
                'quotations.view', 'payments.view', 'payments.manage', 'expenses.manage', 'finance.view',
                'reports.view', 'inventory.view', 'suppliers.manage', 'purchases.manage',
            ],
        ],
        'customer_support' => [
            'name' => 'Customer Support',
            'description' => 'Customer follow-up and order tracking',
            'permissions' => [
                'dashboard.view', 'products.view', 'customers.view', 'orders.view', 'orders.status',
                'artwork.view', 'quotations.view', 'reviews.manage', 'shipments.manage',
            ],
        ],
    ],
];
