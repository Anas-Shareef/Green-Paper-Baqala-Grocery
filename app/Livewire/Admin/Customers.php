<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Services\CustomerImportService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Customers extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $selectedZone = '';

    // Customer Detail Drawer / Modal
    public bool $showCustomerModal = false;
    public ?Customer $viewingCustomer = null;

    // CSV Importer Modal
    public bool $showImporterModal = false;
    public $csvFile;
    public string $duplicateStrategy = 'skip'; // skip, update, merge
    public ?array $importSummary = null;

    public function viewCustomer(int $id)
    {
        $this->viewingCustomer = Customer::with('orders.items')->findOrFail($id);
        $this->showCustomerModal = true;
    }

    public function processImport(CustomerImportService $importService)
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:5120',
            'duplicateStrategy' => 'required|in:skip,update,merge',
        ]);

        try {
            $path = $this->csvFile->getRealPath();
            $this->importSummary = $importService->importCsv($path, $this->duplicateStrategy);
            session()->flash('message', "CSV Import completed! Imported: {$this->importSummary['imported']}, Duplicates: {$this->importSummary['duplicates']}");
        } catch (\Exception $e) {
            session()->flash('error', "Import failed: " . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Customer::query()->orderBy('id', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('villa_number', 'like', "%{$this->search}%")
                    ->orWhere('zone', 'like', "%{$this->search}%");
            });
        }

        if ($this->selectedZone) {
            $query->where('zone', $this->selectedZone);
        }

        $zones = Customer::whereNotNull('zone')->distinct()->pluck('zone');

        return view('livewire.admin.customers', [
            'customers' => $query->paginate(15),
            'zones' => $zones,
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Customer CRM & CSV Importer']);
    }
}
