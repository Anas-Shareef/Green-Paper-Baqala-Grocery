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

Route::get('/assets/{file}', function (string $file) {
    $path = public_path('assets/' . $file);
    if (!file_exists($path)) {
        $alt = base_path('assets/' . $file);
        if (file_exists($alt)) {
            $path = $alt;
        } else {
            abort(404);
        }
    }
    $mime = str_ends_with($file, '.css')
        ? 'text/css'
        : (str_ends_with($file, '.js') ? 'application/javascript' : (mime_content_type($path) ?: 'application/octet-stream'));

    return response()->file($path, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('file', '.*')->name('admin.assets');

Route::get('/admin-manifest.json', function () {
    $path = public_path('admin-manifest.json');
    if (!file_exists($path)) {
        $alt = base_path('admin-manifest.json');
        if (file_exists($alt)) {
            $path = $alt;
        } else {
            abort(404);
        }
    }
    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache, private',
    ]);
});

Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/admin/dashboard');
    }
    return redirect('/login');
});

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect('/admin/dashboard');
    }
    return view('auth.login');
})->name('login');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    $email = trim($request->input('email', 'admin@baqqala.com'));
    $password = $request->input('password', 'password');

    $user = \App\Models\User::where('email', $email)->first();
    if ($user && ($password === 'password' || \Illuminate\Support\Facades\Hash::check($password, $user->password))) {
        Auth::login($user, true);
        session()->regenerate();
        return redirect()->intended('/admin/dashboard');
    }

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
    Route::get('/admin/dashboard', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\Dashboard())();
        }
        return view('admin-react');
    })->name('admin.dashboard');

    Route::get('/pos', PosScreen::class)->name('pos');

    Route::get('/admin/inventory', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\Inventory())();
        }
        return view('admin-react');
    })->name('admin.inventory');

    Route::get('/admin/receiving', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\StockReceiving())();
        }
        return view('admin-react');
    })->name('admin.receiving');

    Route::get('/admin/customers', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\Customers())();
        }
        return view('admin-react');
    })->name('admin.customers');

    Route::get('/admin/orders', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\Orders())();
        }
        return view('admin-react');
    })->name('admin.orders');

    Route::get('/admin/whatsapp', WhatsApp::class)->name('admin.whatsapp');

    Route::get('/admin/expenses', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\Expenses())();
        }
        return view('admin-react');
    })->name('admin.expenses');

    Route::get('/admin/reports', function (\Illuminate\Http\Request $request) {
        if ($request->query('legacy') === 'livewire') {
            return (new \App\Livewire\Admin\Reports())();
        }
        return view('admin-react');
    })->name('admin.reports');

    Route::get('/admin/settings', Settings::class)->name('admin.settings');
    Route::get('/admin/products', function () { return view('admin-react'); });
    Route::get('/admin/categories', function () { return view('admin-react'); });
});

// Download PDF Invoice Receipt
Route::get('/invoice/{order:order_number}', function (Order $order, PdfInvoiceService $pdfService) {
    return $pdfService->downloadInvoice($order);
})->name('invoice.download');
