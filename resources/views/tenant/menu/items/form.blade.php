@csrf
<div class="grid gap-4 md:grid-cols-2">
    <label class="block md:col-span-2">
        <span class="font-bold">Category</span>
        <select name="category_id" required class="pos-field mt-1">
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $item->category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="font-bold">Name</span>
        <input name="name" value="{{ old('name', $item->name) }}" required class="pos-field mt-1">
    </label>
    <label class="block">
        <span class="font-bold">Price</span>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $item->price) }}" required class="pos-field mt-1">
    </label>
    <label class="block md:col-span-2">
        <span class="font-bold">Description</span>
        <textarea name="description" rows="4" class="pos-field mt-1">{{ old('description', $item->description) }}</textarea>
    </label>
    <label class="block">
        <span class="font-bold">Image</span>
        <input type="file" name="image" accept="image/*" class="pos-field mt-1">
    </label>
    <label class="block">
        <span class="font-bold">Sort order</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}" min="0" required class="pos-field mt-1">
    </label>
    <input type="hidden" name="is_available" value="0">
    <label class="inline-flex min-h-11 items-center gap-2 font-bold">
        <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $item->is_available ?? true)) class="rounded border-slate-300">
        Available
    </label>
    <input type="hidden" name="is_active" value="0">
    <label class="inline-flex min-h-11 items-center gap-2 font-bold">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="rounded border-slate-300">
        Visible
    </label>
</div>
<button type="submit" class="pos-button mt-5 bg-slate-950 text-white">Save item</button>
