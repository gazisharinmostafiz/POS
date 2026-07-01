@php
    $title = 'Vendor Billing';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Tong POS Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto max-w-6xl px-6 py-8">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-cyan-300">SaaS billing</p>
                <h1 class="mt-2 text-3xl font-bold">Vendor billing</h1>
                <p class="mt-2 text-slate-400">Manage the SaaS subscription for {{ $tenant->name }}.</p>
            </div>
            <a href="{{ route('areas.tenant-admin') }}" class="rounded border border-slate-700 px-4 py-2 text-sm text-slate-200">Back</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @error('billing')
            <div class="mb-6 rounded border border-red-700 bg-red-950 px-4 py-3 text-sm text-red-200">
                {{ $message }}
            </div>
        @enderror

        <section class="mb-6 rounded border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-xl font-semibold">Current subscription</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-4">
                <div>
                    <dt class="text-sm text-slate-400">Plan</dt>
                    <dd class="mt-1 font-semibold">{{ $tenant->plan?->name ?? 'None' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-400">Status</dt>
                    <dd class="mt-1 font-semibold">{{ $tenant->subscription_status }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-400">Interval</dt>
                    <dd class="mt-1 font-semibold">{{ $subscription?->billing_interval ?? 'Not subscribed' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-400">Access until</dt>
                    <dd class="mt-1 font-semibold">{{ $tenant->subscription_ends_at?->format('Y-m-d H:i') ?? 'Open' }}</dd>
                </div>
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-xl font-semibold">{{ $subscription ? 'Change plan' : 'Create subscription' }}</h2>
                <form method="POST" action="{{ $subscription ? route('tenant.billing.plan') : route('tenant.billing.subscribe') }}" class="mt-4 space-y-4">
                    @csrf
                    @if ($subscription)
                        @method('PATCH')
                    @endif

                    <div>
                        <label for="plan_id" class="block text-sm font-medium">Plan</label>
                        <select id="plan_id" name="plan_id" required class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id', $tenant->plan_id) == $plan->id)>
                                    {{ $plan->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('plan_id') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="billing_interval" class="block text-sm font-medium">Billing interval</label>
                        <select id="billing_interval" name="billing_interval" required class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                        @error('billing_interval') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>

                    @unless ($subscription)
                        <div>
                            <label for="trial_days" class="block text-sm font-medium">Trial days</label>
                            <input id="trial_days" name="trial_days" type="number" min="0" max="365" value="{{ old('trial_days', 14) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2">
                            @error('trial_days') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                        </div>
                    @endunless

                    <button type="submit" class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">
                        {{ $subscription ? 'Update subscription' : 'Create subscription' }}
                    </button>
                </form>
            </section>

            <section class="rounded border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-xl font-semibold">Subscription actions</h2>
                <p class="mt-2 text-sm text-slate-400">Cancellation affects SaaS access only. Restaurant diner payments are separate.</p>

                @if ($subscription)
                    <form method="POST" action="{{ route('tenant.billing.cancel') }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-red-700 px-4 py-2 font-semibold text-red-200">
                            Cancel subscription
                        </button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-slate-300">No active Stripe subscription record is stored for this vendor.</p>
                @endif
            </section>
        </div>
    </main>
</body>
</html>
