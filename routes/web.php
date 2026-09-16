<?php

use App\Livewire\Admin\Customers;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Expenses;
use App\Livewire\Admin\Inventory;
use App\Livewire\Admin\Orders;
use App\Livewire\Admin\Reports;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\StockReceiving;
use App\Livewire\Admin\WhatsApp;
use App\Livewire\Auth\Login;
use App\Livewire\Pos\PosScreen;
use App\Models\Order;
use App\Services\PdfInvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/admin/dashboard');
    }
    return redirect('/login');
});

Route::get('/login', Login::class)->name('login');
Route::post('/login', function (\Illuminate\Http\Request $request) {
    $email = $request->input('email', 'admin@baqqala.com');
    $password = $request->input('password', 'password');
    if (Auth::attempt(['email' => $email, 'password' => $password])) {
        session()->regenerate();
        return redirect()->intended('/admin/dashboard');
    }
    return back()->with('error', 'Invalid email or password.');
});
Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect('/login');
})->name('logout');

// Main Admin & POS Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/dashboard', Dashboard::class)->name('admin.dashboard');
    Route::get('/pos', PosScreen::class)->name('pos');
    Route::get('/admin/inventory', Inventory::class)->name('admin.inventory');
    Route::get('/admin/receiving', StockReceiving::class)->name('admin.receiving');
    Route::get('/admin/customers', Customers::class)->name('admin.customers');
    Route::get('/admin/orders', Orders::class)->name('admin.orders');
    Route::get('/admin/whatsapp', WhatsApp::class)->name('admin.whatsapp');
    Route::get('/admin/expenses', Expenses::class)->name('admin.expenses');
    Route::get('/admin/reports', Reports::class)->name('admin.reports');
    Route::get('/admin/settings', Settings::class)->name('admin.settings');
});

// Download PDF Invoice Receipt
Route::get('/invoice/{order:order_number}', function (Order $order, PdfInvoiceService $pdfService) {
    return $pdfService->downloadInvoice($order);
})->name('invoice.download');
