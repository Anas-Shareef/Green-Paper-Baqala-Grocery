<?php

namespace App\Livewire\Admin;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Livewire\Component;
use Livewire\WithPagination;

class Expenses extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $categoryId = null;
    public float $amount = 0.00;
    public string $description = '';
    public string $expenseDate = '';
    public string $paymentMethod = 'Cash';
    public string $reference = '';

    public function mount()
    {
        $this->expenseDate = now()->toDateString();
        $this->categoryId = ExpenseCategory::first()?->id;
    }

    public function saveExpense()
    {
        $this->validate([
            'categoryId' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.50',
            'description' => 'required|string|max:255',
            'expenseDate' => 'required|date',
        ]);

        Expense::create([
            'expense_category_id' => $this->categoryId,
            'amount' => $this->amount,
            'description' => $this->description,
            'expense_date' => $this->expenseDate,
            'payment_method' => $this->paymentMethod,
            'reference' => $this->reference,
            'created_by' => auth()->user()?->name ?? 'Admin',
        ]);

        $this->showModal = false;
        $this->amount = 0.00;
        $this->description = '';
        session()->flash('message', 'Business expense logged successfully.');
    }

    public function render()
    {
        return view('livewire.admin.expenses', [
            'expenses' => Expense::with('category')->orderBy('expense_date', 'desc')->paginate(15),
            'categories' => ExpenseCategory::where('status', 'active')->orderBy('name')->get(),
            'totalExpensesThisMonth' => Expense::whereMonth('expense_date', now()->month)->sum('amount'),
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Business Expense Management']);
    }
}
