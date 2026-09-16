<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baqqala Admin — Sign In</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center p-6 bg-slate-950">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl p-8 space-y-6 shadow-2xl">
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center font-black text-3xl text-slate-950 mx-auto shadow-xl shadow-emerald-500/20">
                B
            </div>
            <h2 class="text-2xl font-black text-white tracking-tight">BAQQALA ADMIN</h2>
            <p class="text-xs text-slate-400">Grocery Management, Barcode POS & CRM Portal</p>
        </div>

        @if(session()->has('error'))
        <div class="p-3 bg-rose-950/80 border border-rose-500/50 text-rose-300 rounded-xl text-xs font-semibold text-center">
            {{ session('error') }}
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-300 mb-1">Email Address</label>
                <input type="email" name="email" value="admin@baqqala.com" required placeholder="admin@baqqala.com" class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-3 outline-none focus:border-emerald-500 text-sm font-medium">
            </div>

            <div>
                <label class="block font-bold text-slate-300 mb-1">Password</label>
                <input type="password" name="password" value="password" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-4 py-3 outline-none focus:border-emerald-500 text-sm font-medium">
            </div>

            <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-sm uppercase tracking-wider rounded-xl shadow-xl shadow-emerald-500/20 transition-all">
                Sign In to Dashboard
            </button>
        </form>

        <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-[11px] space-y-1 font-mono text-slate-400 text-center">
            <div class="font-bold text-emerald-400">Demo Login Credentials:</div>
            <div>Admin: admin@baqqala.com / password</div>
            <div>Cashier: staff@baqqala.com / password</div>
            <div>Delivery: driver@baqqala.com / password</div>
        </div>
    </div>
</body>
</html>
