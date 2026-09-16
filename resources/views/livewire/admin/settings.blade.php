<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Business Settings & User Permissions
                <span class="badge badge-emerald">System Control</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Configure Minimum Order Value (MOV), delivery thresholds, branding, and staff user access.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button wire:click="$set('showUserModal', true)" class="btn-glow flex items-center gap-2">
                + Create Staff Account
            </button>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Store Settings Form -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 space-y-4 shadow-sm">
            <h3 class="font-extrabold text-slate-900 text-base">Store & MOV Threshold Configurations</h3>

            <form wire:submit.prevent="saveSettings" class="space-y-4 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Minimum Order Value (MOV ₹) *</label>
                        <input type="number" step="10" min="0" wire:model.defer="mov" required class="input-field text-emerald-700 font-mono font-bold text-base">
                        <p class="text-[10px] text-slate-400 mt-1">Customers cannot place delivery order below MOV.</p>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Internal Delivery Cost (₹) *</label>
                        <input type="number" step="5" min="0" wire:model.defer="defaultDeliveryCost" required class="input-field text-amber-700 font-mono font-bold text-base">
                        <p class="text-[10px] text-slate-400 mt-1">Deducted per order in net profit calculations.</p>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Store Name *</label>
                    <input type="text" wire:model.defer="storeName" required class="input-field">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tagline</label>
                    <input type="text" wire:model.defer="storeTagline" required class="input-field">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full btn-glow justify-center py-3 text-center">
                        Save System Configurations
                    </button>
                </div>
            </form>
        </div>

        <!-- Staff Accounts Table -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 space-y-4 shadow-sm">
            <h3 class="font-extrabold text-slate-900 text-base">Registered Staff Accounts & Roles</h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="tbl-head">
                        <tr>
                            <th class="p-3.5">User Name</th>
                            <th class="p-3.5">Email</th>
                            <th class="p-3.5">Role</th>
                            <th class="p-3.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach($users as $u)
                        <tr class="tbl-row">
                            <td class="tbl-cell font-bold text-slate-900">{{ $u->name }}</td>
                            <td class="tbl-cell text-slate-500 font-mono">{{ $u->email }}</td>
                            <td class="tbl-cell">
                                <span class="badge badge-emerald">
                                    {{ $u->role }}
                                </span>
                            </td>
                            <td class="tbl-cell text-right text-emerald-700 font-bold uppercase text-[10px]">{{ $u->status }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL: User Account Creation -->
    @if($showUserModal)
    <div class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">Create Staff Account</h3>
                <button wire:click="$set('showUserModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form wire:submit.prevent="createUser" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Full Name *</label>
                    <input type="text" wire:model.defer="userName" required placeholder="e.g. John Cashier" class="input-field">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Email Address *</label>
                    <input type="email" wire:model.defer="userEmail" required placeholder="staff@baqqala.com" class="input-field">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Password *</label>
                    <input type="password" wire:model.defer="userPassword" required placeholder="••••••••" class="input-field">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Role *</label>
                    <select wire:model.defer="userRole" required class="input-field">
                        <option value="staff">Cashier / Staff (POS & Stock)</option>
                        <option value="delivery">Delivery Agent</option>
                        <option value="admin">Store Admin</option>
                        <option value="super_admin">Super Admin (Full Access)</option>
                    </select>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showUserModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Create Account</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
