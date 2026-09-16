<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Meta WhatsApp Cloud API Engine
                <span class="badge badge-emerald">Official Meta Integration</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Automated transactional order notifications, PDF billing attachments, and targeted WhatsApp marketing broadcasts.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button wire:click="$set('showCampaignModal', true)" class="btn-glow flex items-center gap-2">
                + Create Marketing Broadcast
            </button>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- LEFT COL: Meta Cloud API Settings -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 class="font-extrabold text-slate-900 text-base">Meta API Credentials Setup</h3>
            <p class="text-xs text-slate-500">Credentials are stored securely in backend business settings. If blank, simulated WhatsApp delivery logging is active.</p>

            <form wire:submit.prevent="saveSettings" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">WhatsApp Access Token</label>
                    <input type="password" wire:model.defer="accessToken" placeholder="EAAG..." class="input-field font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Phone Number ID</label>
                    <input type="text" wire:model.defer="phoneNumberId" placeholder="10594..." class="input-field font-mono">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Business Account ID</label>
                    <input type="text" wire:model.defer="businessAccountId" placeholder="10923..." class="input-field font-mono">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full btn-glow justify-center py-2.5">
                        Save Meta Credentials
                    </button>
                </div>
            </form>

            <div class="pt-3 border-t border-slate-100 text-xs space-y-2">
                <div class="flex justify-between text-slate-600">
                    <span>Opted-In Marketing Audience:</span>
                    <span class="font-bold text-emerald-700 font-mono">{{ $optedInCount }} Customers</span>
                </div>
            </div>
        </div>

        <!-- RIGHT 2 COLS: WhatsApp Message Log & Broadcast Campaigns -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Message Logs Table -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
                <h3 class="font-extrabold text-slate-900 text-base">WhatsApp Message Activity Log</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="tbl-head">
                            <tr>
                                <th class="p-3.5">Recipient</th>
                                <th class="p-3.5">Type</th>
                                <th class="p-3.5">Message Snippet</th>
                                <th class="p-3.5 text-center">Status</th>
                                <th class="p-3.5 text-right">Sent Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse($messages as $msg)
                            <tr class="tbl-row">
                                <td class="tbl-cell font-mono font-bold text-slate-900">+{{ $msg->recipient }}</td>
                                <td class="tbl-cell">
                                    <span class="badge badge-slate">
                                        {{ str_replace('_', ' ', $msg->message_type) }}
                                    </span>
                                </td>
                                <td class="tbl-cell max-w-[220px] truncate text-slate-600">{{ $msg->message_body }}</td>
                                <td class="tbl-cell text-center">
                                    @if($msg->status === 'delivered')
                                    <span class="badge badge-emerald">{{ $msg->status }}</span>
                                    @else
                                    <span class="badge badge-amber">{{ $msg->status }}</span>
                                    @endif
                                </td>
                                <td class="tbl-cell text-right font-mono text-slate-400 text-[11px]">{{ $msg->created_at->format('H:i:s') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="p-6 text-center text-slate-400 font-medium">No WhatsApp messages dispatched yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-2 border-t border-slate-100">
                    {{ $messages->links() }}
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL: Broadcast Campaign Builder -->
    @if($showCampaignModal)
    <div class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">New WhatsApp Broadcast Campaign</h3>
                <button wire:click="$set('showCampaignModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form wire:submit.prevent="dispatchCampaign" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Campaign Name *</label>
                    <input type="text" wire:model.defer="campaignName" required placeholder="e.g. Weekend Dairy Special 20% Off" class="input-field">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Target Audience Zone Filter</label>
                    <select wire:model.defer="targetZone" class="input-field">
                        <option value="">All Zones (Opted-in customers)</option>
                        @foreach($zones as $z)
                        <option value="{{ $z }}">{{ $z }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">WhatsApp Message Body *</label>
                    <textarea wire:model.defer="campaignMessage" required placeholder="Baqqala Special Offer! Fresh produce and daily essentials delivered to your Villa..." class="input-field h-28 py-2.5"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showCampaignModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Dispatch Broadcast</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
