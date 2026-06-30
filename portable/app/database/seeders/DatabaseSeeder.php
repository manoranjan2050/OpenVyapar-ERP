<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $salesman = Role::firstOrCreate(['name' => 'salesman']);
        Role::firstOrCreate(['name' => 'accountant']);

        // Company
        $company = Company::create([
            'name' => 'Demo Traders',
            'legal_name' => 'Demo Traders Pvt Ltd',
            'gstin' => '27AABCT1332L1ZS',
            'pan' => 'AABCT1332L',
            'phone' => '9876543210',
            'email' => 'demo@openvyapar.in',
            'address' => '123, MG Road, Andheri West',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400058',
        ]);

        // Financial Year
        FinancialYear::create([
            'company_id' => $company->id,
            'name' => '2025-26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_current' => true,
        ]);

        // Branch
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Head Office',
            'gstin' => $company->gstin,
            'phone' => $company->phone,
            'address' => $company->address,
            'city' => $company->city,
            'state' => $company->state,
            'is_head_office' => true,
        ]);

        // Warehouse
        Warehouse::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Main Warehouse']);

        // Admin user — must_change_password forces user to set their own password on first login
        $adminUser = User::create([
            'name'                => 'Admin User',
            'email'               => 'admin@openvyapar.in',
            'password'            => bcrypt('password'),
            'company_id'          => $company->id,
            'branch_id'           => $branch->id,
            'phone'               => '9876543210',
            'must_change_password' => true,
        ]);
        $adminUser->assignRole($admin);

        // Salesman
        $salesUser = User::create([
            'name' => 'Sales Person',
            'email' => 'sales@openvyapar.in',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);
        $salesUser->assignRole($salesman);

        // Units
        $units = collect([
            ['name' => 'Pieces', 'short_name' => 'PCS'],
            ['name' => 'Kilogram', 'short_name' => 'KG'],
            ['name' => 'Gram', 'short_name' => 'GM'],
            ['name' => 'Litre', 'short_name' => 'LTR'],
            ['name' => 'Metre', 'short_name' => 'MTR'],
            ['name' => 'Box', 'short_name' => 'BOX'],
        ])->map(fn($u) => Unit::create(array_merge($u, ['company_id' => $company->id])));

        // Categories
        $electronics = Category::create(['company_id' => $company->id, 'name' => 'Electronics', 'slug' => 'electronics']);
        $grocery = Category::create(['company_id' => $company->id, 'name' => 'Grocery', 'slug' => 'grocery']);
        $clothing = Category::create(['company_id' => $company->id, 'name' => 'Clothing', 'slug' => 'clothing']);

        // Products
        $pcs = $units->firstWhere('short_name', 'PCS');
        $kg = $units->firstWhere('short_name', 'KG');

        Product::create(['company_id' => $company->id, 'category_id' => $electronics->id, 'unit_id' => $pcs->id, 'name' => 'USB-C Cable 1m', 'sku' => 'USBC-001', 'hsn_code' => '85444290', 'gst_rate' => '18', 'purchase_price' => 80, 'selling_price' => 149, 'mrp' => 199, 'opening_stock' => 50, 'low_stock_alert' => 10]);
        Product::create(['company_id' => $company->id, 'category_id' => $electronics->id, 'unit_id' => $pcs->id, 'name' => 'Phone Case', 'sku' => 'PHC-001', 'hsn_code' => '39269090', 'gst_rate' => '18', 'purchase_price' => 120, 'selling_price' => 249, 'mrp' => 349, 'opening_stock' => 30, 'low_stock_alert' => 5]);
        Product::create(['company_id' => $company->id, 'category_id' => $grocery->id, 'unit_id' => $kg->id, 'name' => 'Basmati Rice', 'sku' => 'RICE-001', 'hsn_code' => '10063090', 'gst_rate' => '5', 'purchase_price' => 55, 'selling_price' => 75, 'mrp' => 80, 'opening_stock' => 100, 'low_stock_alert' => 20]);
        Product::create(['company_id' => $company->id, 'category_id' => $grocery->id, 'unit_id' => $kg->id, 'name' => 'Refined Oil 1L', 'sku' => 'OIL-001', 'hsn_code' => '15079010', 'gst_rate' => '5', 'purchase_price' => 130, 'selling_price' => 155, 'mrp' => 170, 'opening_stock' => 60, 'low_stock_alert' => 15]);
        Product::create(['company_id' => $company->id, 'category_id' => $clothing->id, 'unit_id' => $pcs->id, 'name' => 'Cotton T-Shirt', 'sku' => 'TSH-001', 'hsn_code' => '61091000', 'gst_rate' => '12', 'purchase_price' => 200, 'selling_price' => 399, 'mrp' => 499, 'opening_stock' => 40, 'low_stock_alert' => 5]);

        // Customers
        Customer::create(['company_id' => $company->id, 'name' => 'Ramesh Sharma', 'phone' => '9823456789', 'email' => 'ramesh@example.com', 'billing_city' => 'Mumbai', 'billing_state' => 'Maharashtra', 'credit_limit' => 50000, 'credit_days' => 30]);
        Customer::create(['company_id' => $company->id, 'name' => 'Priya Enterprises', 'phone' => '9812345678', 'gstin' => '27AASCP1234D1Z1', 'billing_city' => 'Pune', 'billing_state' => 'Maharashtra', 'credit_limit' => 200000, 'credit_days' => 45]);
        Customer::create(['company_id' => $company->id, 'name' => 'Delhi Wholesale Mart', 'phone' => '9811234567', 'gstin' => '07AASCP5678D1Z2', 'billing_city' => 'Delhi', 'billing_state' => 'Delhi', 'credit_limit' => 500000, 'credit_days' => 60]);

        // Suppliers
        Supplier::create(['company_id' => $company->id, 'name' => 'Electronics Distributor Co.', 'phone' => '9876001234', 'gstin' => '27AASPE1234F1Z5', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'credit_days' => 30]);
        Supplier::create(['company_id' => $company->id, 'name' => 'Agro Foods Pvt Ltd', 'phone' => '9845001234', 'gstin' => '27AASPA5678G1Z3', 'city' => 'Nashik', 'state' => 'Maharashtra', 'credit_days' => 15]);
        Supplier::create(['company_id' => $company->id, 'name' => 'Fashion Hub', 'phone' => '9833001234', 'gstin' => '27AASPF9012H1Z1', 'city' => 'Surat', 'state' => 'Gujarat', 'credit_days' => 30]);
    }
}
