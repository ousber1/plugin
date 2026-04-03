<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@omnichannel.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Staff user
        User::create([
            'name' => 'Staff User',
            'email' => 'staff@omnichannel.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'is_active' => true,
        ]);

        // Sample customers
        $customers = [
            ['name' => 'Ahmed Hassan', 'email' => 'ahmed@example.com', 'phone' => '+1234567890', 'city' => 'New York', 'tags' => ['VIP', 'frequent']],
            ['name' => 'Sarah Johnson', 'email' => 'sarah@example.com', 'phone' => '+1234567891', 'city' => 'Los Angeles', 'tags' => ['wholesale']],
            ['name' => 'Mohamed Ali', 'email' => 'mohamed@example.com', 'phone' => '+1234567892', 'city' => 'Chicago', 'tags' => ['VIP']],
            ['name' => 'Emma Wilson', 'phone' => '+1234567893', 'city' => 'Miami', 'tags' => ['new']],
            ['name' => 'James Brown', 'phone' => '+1234567894', 'city' => 'Houston', 'tags' => ['frequent']],
        ];

        foreach ($customers as $c) {
            Customer::create($c);
        }

        // Sample products
        $products = [
            ['name' => 'Wireless Headphones', 'sku' => 'WH-001', 'barcode' => '1234567890123', 'cost_price' => 25.00, 'selling_price' => 49.99, 'stock_quantity' => 50, 'category' => 'Electronics', 'low_stock_threshold' => 10],
            ['name' => 'Phone Case Premium', 'sku' => 'PC-001', 'barcode' => '1234567890124', 'cost_price' => 5.00, 'selling_price' => 19.99, 'stock_quantity' => 100, 'category' => 'Accessories', 'low_stock_threshold' => 20],
            ['name' => 'USB-C Cable 2m', 'sku' => 'UC-001', 'barcode' => '1234567890125', 'cost_price' => 3.00, 'selling_price' => 12.99, 'stock_quantity' => 200, 'category' => 'Accessories', 'low_stock_threshold' => 30],
            ['name' => 'Bluetooth Speaker', 'sku' => 'BS-001', 'barcode' => '1234567890126', 'cost_price' => 35.00, 'selling_price' => 79.99, 'stock_quantity' => 30, 'category' => 'Electronics', 'low_stock_threshold' => 5],
            ['name' => 'Screen Protector', 'sku' => 'SP-001', 'barcode' => '1234567890127', 'cost_price' => 1.50, 'selling_price' => 9.99, 'stock_quantity' => 300, 'category' => 'Accessories', 'low_stock_threshold' => 50],
            ['name' => 'Power Bank 10000mAh', 'sku' => 'PB-001', 'barcode' => '1234567890128', 'cost_price' => 15.00, 'selling_price' => 39.99, 'stock_quantity' => 40, 'category' => 'Electronics', 'low_stock_threshold' => 8],
            ['name' => 'Laptop Stand', 'sku' => 'LS-001', 'barcode' => '1234567890129', 'cost_price' => 20.00, 'selling_price' => 45.99, 'stock_quantity' => 25, 'category' => 'Accessories', 'low_stock_threshold' => 5],
            ['name' => 'Wireless Mouse', 'sku' => 'WM-001', 'barcode' => '1234567890130', 'cost_price' => 8.00, 'selling_price' => 24.99, 'stock_quantity' => 3, 'category' => 'Electronics', 'low_stock_threshold' => 10],
            ['name' => 'Webcam HD', 'sku' => 'WC-001', 'barcode' => '1234567890131', 'cost_price' => 30.00, 'selling_price' => 69.99, 'stock_quantity' => 15, 'category' => 'Electronics', 'low_stock_threshold' => 5],
            ['name' => 'Keyboard Mechanical', 'sku' => 'KB-001', 'barcode' => '1234567890132', 'cost_price' => 40.00, 'selling_price' => 89.99, 'stock_quantity' => 0, 'category' => 'Electronics', 'low_stock_threshold' => 5],
        ];

        foreach ($products as $p) {
            Product::create($p);
        }

        // Default settings
        $settings = [
            ['key' => 'store_name', 'value' => 'OmniChannel Store', 'group' => 'general'],
            ['key' => 'currency', 'value' => 'USD', 'group' => 'general'],
            ['key' => 'tax_rate', 'value' => '0', 'group' => 'general'],
            ['key' => 'low_stock_threshold', 'value' => '5', 'group' => 'general'],
            ['key' => 'whatsapp_token', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_phone_id', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_verify_token', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'openai_api_key', 'value' => '', 'group' => 'api'],
            ['key' => 'meta_ads_token', 'value' => '', 'group' => 'api'],
            ['key' => 'meta_ads_account_id', 'value' => '', 'group' => 'api'],
        ];

        foreach ($settings as $s) {
            Setting::create($s);
        }
    }
}
