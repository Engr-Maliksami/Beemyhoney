<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Product;
use App\Models\UserCustomers;
use App\Models\Order;
use App\Models\Invoice;
use Carbon\Carbon;
use App\Models\UserFacebookAccount;

class Dashboard extends Component
{
    public $TotalCustomers = 0;
    public $NewCustomersToday = 0;
    public $FacebookCustomers = 0;
    public $ManualCustomers = 0;
    public $TotalOrders = 0;
    public $ApprovedOrders = 0;
    public $ShippedOrders = 0;
    public $TotalProducts = 0;
    public $TotalFBAccounts = 0;
    public $Top10Customers;
    public $Top10Products;
    public $WeeklySales;
    public $DailyPurchases;
    public $PricesGroupedByDate;
    public $selectedDateRange;
    public $startDate;
    public $endDate;

    public function mount()
    {
        $this->selectedDateRange = Carbon::today()->subDays(15)->format('Y-m-d') . ' to ' . Carbon::today()->format('Y-m-d');
        $this->startDate = Carbon::today()->subDays(15);
        $this->endDate = Carbon::today();
    }

    public function updatedSelectedDateRange($value)
    {
        $dates = explode(' to ', $value);
        if (count($dates) === 2) {
            $this->startDate = Carbon::parse($dates[0]);
            $this->endDate = Carbon::parse($dates[1]);
        }
    }

    public function render()
    {
        // Query data within the selected date range
        $this->TotalCustomers = UserCustomers::whereBetween('created_at', [$this->startDate, $this->endDate])->count();
        $this->NewCustomersToday = UserCustomers::whereDate('created_at', Carbon::today())->count();
        $this->FacebookCustomers = UserCustomers::whereBetween('created_at', [$this->startDate, $this->endDate])->where('source', 'auto')->count();
        $this->ManualCustomers = UserCustomers::whereBetween('created_at', [$this->startDate, $this->endDate])->where('source', 'manual')->count();
        
        $this->TotalOrders = Order::whereBetween('created_at', [$this->startDate, $this->endDate])->count();
        $this->ApprovedOrders = Order::where('status', 'confirmed')->whereBetween('created_at', [$this->startDate, $this->endDate])->count();
        $this->ShippedOrders = Order::where('status', 'completed')->whereBetween('created_at', [$this->startDate, $this->endDate])->count();
        
        $this->TotalProducts = Product::whereBetween('created_at', [$this->startDate, $this->endDate])->count();

        $this->Top10Customers = Order::selectRaw('user_customer_id, SUM(total_amount) as total_spent')
            ->groupBy('user_customer_id')
            ->orderByDesc('total_spent')
            ->take(10)
            ->whereBetween('order_date', [$this->startDate, $this->endDate])
            ->with('userCustomer')
            ->get();

        $this->Top10Products = Order::join('order_details', 'orders.id', '=', 'order_details.order_id')
            ->selectRaw('order_details.product_name, SUM(order_details.quantity) as total_quantity_sold')
            ->groupBy('order_details.product_name')
            ->orderByDesc('total_quantity_sold')
            ->take(10)
            ->whereBetween('orders.order_date', [$this->startDate, $this->endDate])
            ->with('orderDetails.product')
            ->get();

        $this->WeeklySales = Order::selectRaw('DATE(order_date) as date, SUM(total_amount) as total_sales')
            ->whereBetween('order_date', [$this->startDate, $this->endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->DailyPurchases = Order::selectRaw('DATE(order_date) as date, SUM(total_amount) as total_purchases')
            ->where('status', 'confirmed')
            ->whereBetween('order_date', [$this->startDate, $this->endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->PricesGroupedByDate = Order::selectRaw('DATE(order_date) as date, SUM(total_amount) as total_price, COUNT(*) as order_count')
            ->whereBetween('order_date', [$this->startDate, $this->endDate])
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
        
        return view('livewire.dashboard');
    }
}
