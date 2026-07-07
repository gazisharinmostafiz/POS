@csrf
<div class="grid gap-5">
    <label class="block">
        <span class="pos-label">Name</span>
        <input name="name" value="{{ old('name', $category->name) }}" required class="pos-field mt-1.5">
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </label>
    <label class="block">
        <span class="pos-label">Description</span>
        <textarea name="description" rows="4" class="pos-field mt-1.5">{{ old('description', $category->description) }}</textarea>
    </label>
    <label class="block max-w-xs">
        <span class="pos-label">Sort order</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" required class="pos-field mt-1.5">
    </label>
    <div class="pos-toggle-row max-w-md">
        <div>
            <p class="pos-toggle-label">Active on menu</p>
            <p class="pos-toggle-hint">Hidden categories won't appear on the waiter POS.</p>
        </div>
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="h-5 w-5 rounded border-slate-300 text-brand-600">
    </div>
</div>
<div class="mt-6 flex gap-3">
    <button type="submit" class="pos-btn-primary">Save category</button>
    <a href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.categories.index', $tenant) : route('tenant.menu.categories.index') }}" class="pos-btn-secondary">Cancel</a>
</div>
