@csrf
<div class="grid gap-5 md:grid-cols-2">
    <label class="block md:col-span-2">
        <span class="pos-label">Category</span>
        <select name="category_id" required class="pos-field mt-1.5">
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $item->category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="pos-label">Name</span>
        <input name="name" value="{{ old('name', $item->name) }}" required class="pos-field mt-1.5">
    </label>
    <label class="block">
        <span class="pos-label">Price</span>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $item->price) }}" required class="pos-field mt-1.5">
    </label>
    <label class="block md:col-span-2">
        <span class="pos-label">Description</span>
        <textarea name="description" rows="4" class="pos-field mt-1.5">{{ old('description', $item->description) }}</textarea>
    </label>
    <label class="block">
        <span class="pos-label">Image</span>
        <input type="file" name="image" accept="image/*" class="pos-field mt-1.5">
    </label>
    <label class="block">
        <span class="pos-label">Sort order</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}" min="0" required class="pos-field mt-1.5">
    </label>
    <div class="pos-toggle-row">
        <div><p class="pos-toggle-label">Available to order</p></div>
        <input type="hidden" name="is_available" value="0">
        <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $item->is_available ?? true)) class="h-5 w-5 rounded border-slate-300 text-brand-600">
    </div>
    <div class="pos-toggle-row">
        <div><p class="pos-toggle-label">Visible on POS</p></div>
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="h-5 w-5 rounded border-slate-300 text-brand-600">
    </div>
</div>
<div class="mt-6 flex gap-3">
    <button type="submit" class="pos-btn-primary">Save item</button>
    <a href="{{ request()->routeIs('platform.*') ? route('platform.vendors.menu.items.index', $tenant) : route('tenant.menu.items.index') }}" class="pos-btn-secondary">Cancel</a>
</div>
