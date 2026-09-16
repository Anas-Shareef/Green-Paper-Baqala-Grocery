<?php

namespace App\Livewire\Admin;

use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Settings extends Component
{
    public float $mov = 300.00;
    public float $defaultDeliveryCost = 25.00;
    public string $storeName = 'Baqqala';
    public string $storeTagline = 'Your Everyday Grocery, Delivered.';
    public string $currencySymbol = '₹';

    // User Creation Modal
    public bool $showUserModal = false;
    public string $userName = '';
    public string $userEmail = '';
    public string $userPassword = '';
    public string $userRole = 'staff';

    public function mount()
    {
        $this->mov = (float) BusinessSetting::get('minimum_order_value', 300.00);
        $this->defaultDeliveryCost = (float) BusinessSetting::get('default_internal_delivery_cost', 25.00);
        $this->storeName = BusinessSetting::get('store_name', 'Baqqala');
        $this->storeTagline = BusinessSetting::get('store_tagline', 'Your Everyday Grocery, Delivered.');
        $this->currencySymbol = BusinessSetting::get('currency_symbol', '₹');
    }

    public function saveSettings()
    {
        BusinessSetting::set('minimum_order_value', $this->mov);
        BusinessSetting::set('default_internal_delivery_cost', $this->defaultDeliveryCost);
        BusinessSetting::set('store_name', $this->storeName);
        BusinessSetting::set('store_tagline', $this->storeTagline);
        BusinessSetting::set('currency_symbol', $this->currencySymbol);

        session()->flash('message', 'System settings and MOV thresholds updated successfully.');
    }

    public function createUser()
    {
        $this->validate([
            'userName' => 'required|string|max:255',
            'userEmail' => 'required|email|unique:users,email',
            'userPassword' => 'required|string|min:6',
            'userRole' => 'required|in:super_admin,admin,staff,delivery',
        ]);

        User::create([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => Hash::make($this->userPassword),
            'role' => $this->userRole,
            'status' => 'active',
        ]);

        $this->showUserModal = false;
        $this->userName = '';
        $this->userEmail = '';
        $this->userPassword = '';
        session()->flash('message', "New staff account created successfully.");
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'users' => User::orderBy('id', 'desc')->get(),
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Business Settings & Users']);
    }
}
