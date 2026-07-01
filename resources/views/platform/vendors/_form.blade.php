@csrf

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="block text-sm font-medium" for="name">Vendor name</label>
        <input id="name" name="name" value="{{ old('name', $vendor->name) }}" required class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        @error('name') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium" for="slug">Slug</label>
        <input id="slug" name="slug" value="{{ old('slug', $vendor->slug) }}" required class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        @error('slug') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium" for="domain">Custom domain</label>
        <input id="domain" name="domain" value="{{ old('domain', $vendor->domain) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        @error('domain') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium" for="subdomain">Subdomain</label>
        <input id="subdomain" name="subdomain" value="{{ old('subdomain', $vendor->subdomain) }}" class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        @error('subdomain') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<label class="mt-5 flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vendor->is_active)) class="rounded border-slate-700 bg-slate-950">
    Active vendor
</label>

<div class="mt-6 flex gap-3">
    <button type="submit" class="rounded bg-cyan-400 px-4 py-2 font-semibold text-slate-950">{{ $submitLabel }}</button>
    <a href="{{ route('platform.vendors.index') }}" class="rounded border border-slate-700 px-4 py-2 text-slate-200">Cancel</a>
</div>
