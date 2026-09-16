<?php

namespace App\Livewire\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Livewire\Component;
use Livewire\WithPagination;

class WhatsApp extends Component
{
    use WithPagination;

    // Meta Settings Form
    public string $accessToken = '';
    public string $phoneNumberId = '';
    public string $businessAccountId = '';

    // Campaign Builder Form
    public bool $showCampaignModal = false;
    public string $campaignName = '';
    public string $campaignMessage = '';
    public string $campaignImage = '';
    public string $targetCategory = '';
    public string $targetZone = '';

    public function mount()
    {
        $this->accessToken = BusinessSetting::get('whatsapp_access_token', '');
        $this->phoneNumberId = BusinessSetting::get('whatsapp_phone_number_id', '');
        $this->businessAccountId = BusinessSetting::get('whatsapp_business_account_id', '');
    }

    public function saveSettings()
    {
        BusinessSetting::set('whatsapp_access_token', $this->accessToken);
        BusinessSetting::set('whatsapp_phone_number_id', $this->phoneNumberId);
        BusinessSetting::set('whatsapp_business_account_id', $this->businessAccountId);

        session()->flash('message', 'Meta WhatsApp Cloud API credentials updated successfully.');
    }

    public function dispatchCampaign(WhatsAppService $whatsAppService)
    {
        $this->validate([
            'campaignName' => 'required|string|max:255',
            'campaignMessage' => 'required|string|min:10',
        ]);

        $query = Customer::where('whatsapp_marketing_opt_in', true)->where('status', 'active');

        if ($this->targetZone) {
            $query->where('zone', $this->targetZone);
        }

        $recipients = $query->get();

        $campaign = MarketingCampaign::create([
            'name' => $this->campaignName,
            'message' => $this->campaignMessage,
            'image' => $this->campaignImage,
            'audience_filter' => [
                'target_category' => $this->targetCategory,
                'target_zone' => $this->targetZone,
                'opted_in_only' => true,
            ],
            'status' => 'sent',
            'sent_at' => now(),
            'created_by' => auth()->user()?->name ?? 'Admin',
        ]);

        $sentCount = 0;
        foreach ($recipients as $cust) {
            $rec = MarketingCampaignRecipient::create([
                'marketing_campaign_id' => $campaign->id,
                'customer_id' => $cust->id,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $whatsAppService->sendMessage([
                'customer_id' => $cust->id,
                'campaign_id' => $campaign->id,
                'message_type' => 'marketing',
                'recipient' => $cust->whatsapp_number ?? $cust->phone,
                'message_body' => $this->campaignMessage,
            ]);

            $sentCount++;
        }

        $this->showCampaignModal = false;
        $this->campaignName = '';
        $this->campaignMessage = '';
        session()->flash('message', "Broadcast campaign '{$campaign->name}' dispatched to {$sentCount} opted-in WhatsApp customers!");
    }

    public function render()
    {
        $zones = Customer::whereNotNull('zone')->distinct()->pluck('zone');

        return view('livewire.admin.whats-app', [
            'messages' => WhatsAppMessage::with(['customer', 'order'])->orderBy('id', 'desc')->paginate(15),
            'campaigns' => MarketingCampaign::with('recipients')->orderBy('id', 'desc')->get(),
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
            'zones' => $zones,
            'optedInCount' => Customer::where('whatsapp_marketing_opt_in', true)->count(),
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Meta WhatsApp Marketing & Broadcasts']);
    }
}
