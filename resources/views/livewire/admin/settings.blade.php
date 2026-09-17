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
        
        <!-- Native Device OS Push Notification Control Card -->
        <div x-data="osNotificationControl()" class="bg-white border border-slate-200/80 rounded-2xl p-6 space-y-4 shadow-sm lg:col-span-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-base flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 01-6 0v-1m6 0H9"></path></svg>
                        Native Device OS Push Notifications
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Receive instant device system banners on your phone screen or desktop OS when a customer places a grocery delivery order.</p>
                </div>

                <!-- Status & Permission Toggle Button -->
                <div class="flex items-center gap-3">
                    <template x-if="permission === 'granted'">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full text-xs font-bold shadow-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                            ✓ OS Notifications Active
                        </span>
                    </template>
                    <template x-if="permission === 'denied'">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-100 text-rose-800 border border-rose-300 rounded-full text-xs font-bold">
                            ⚠️ Blocked in Browser Settings
                        </span>
                    </template>
                    <template x-if="permission === 'default'">
                        <button @click="requestPermission()" class="btn-glow px-4 py-2.5 text-xs font-bold flex items-center gap-2 shadow-md">
                            🔔 Enable Native OS Notifications
                        </button>
                    </template>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                        📱 Instant Device OS Banner
                    </div>
                    <p class="text-slate-500 text-[11px] leading-relaxed">System popups appear on lockscreen and phone notification area.</p>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                        🚀 Direct One-Tap Navigation
                    </div>
                    <p class="text-slate-500 text-[11px] leading-relaxed">Tapping the notification opens <code class="bg-slate-200 px-1 py-0.5 rounded text-[10px] text-slate-800 font-mono">/admin/orders</code> directly.</p>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                        🔊 Audio Synthesizer Chime
                    </div>
                    <p class="text-slate-500 text-[11px] leading-relaxed">Plays loud chime sound automatically on new customer orders.</p>
                </div>
            </div>

            <script>
            function osNotificationControl() {
                return {
                    permission: 'Notification' in window ? Notification.permission : 'denied',
                    async requestPermission() {
                        if ('Notification' in window) {
                            const res = await Notification.requestPermission();
                            this.permission = res;
                        }
                    }
                }
            }
            </script>
        </div>
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
