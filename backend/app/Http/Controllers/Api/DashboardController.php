<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();

        $todaySales = SalesInvoice::where('company_id', $companyId)
            ->whereDate('invoice_date', $today)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $monthSales = SalesInvoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $monthStart)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $monthPurchase = PurchaseInvoice::where('company_id', $companyId)
            ->whereDate('invoice_date', '>=', $monthStart)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $outstandingReceivable = SalesInvoice::where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->sum('balance_amount');

        $outstandingPayable = PurchaseInvoice::where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'partially_paid'])
            ->sum('balance_amount');

        $totalProducts = Product::where('company_id', $companyId)->where('is_active', true)->count();
        $totalCustomers = Customer::where('company_id', $companyId)->where('is_active', true)->count();
        $totalSuppliers = Supplier::where('company_id', $companyId)->where('is_active', true)->count();
        $lowStockCount = $this->inventory->lowStockProducts($companyId)->count();

        $salesChart = SalesInvoice::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('invoice_date', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('DATE(invoice_date) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topProducts = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->where('sales_invoices.company_id', $companyId)
            ->where('sales_invoices.status', '!=', 'cancelled')
            ->whereDate('sales_invoices.invoice_date', '>=', $monthStart)
            ->selectRaw('products.name, SUM(sales_invoice_items.quantity) as qty, SUM(sales_invoice_items.total_amount) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return response()->json([
            'today_sales' => $todaySales,
            'month_sales' => $monthSales,
            'month_purchase' => $monthPurchase,
            'outstanding_receivable' => $outstandingReceivable,
            'outstanding_payable' => $outstandingPayable,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'total_suppliers' => $totalSuppliers,
            'low_stock_count' => $lowStockCount,
            'sales_chart' => $salesChart,
            'top_products' => $topProducts,
        ]);
    }
}
