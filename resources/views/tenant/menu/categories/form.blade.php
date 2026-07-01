@csrf
<div class="grid gap-4">
    <label class="block">
        <span class="font-bold">Name</span>
        <input name="name" value="{{ old('name', $category->name) }}" required class="pos-field mt-1">
        @error('name') <p class="mt-1 text-sm font-bold text-red-700">{{ $message }}</p> @enderror
    </label>
    <label class="block">
        <span class="font-bold">Description</span>
        <textarea name="description" rows="4" class="pos-field mt-1">{{ old('description', $category->description) }}</textarea>
    </label>
    <label class="block">
        <span class="font-bold">Sort order</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" required class="pos-field mt-1">
    </label>
    <input type="hidden" name="is_active" value="0">
    <label class="inline-flex min-h-11 items-center gap-2 font-bold">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="rounded border-slate-300">
        Active
    </label>
</div>
<button type="submit" class="pos-button mt-5 bg-slate-950 text-white">Save category</button>
