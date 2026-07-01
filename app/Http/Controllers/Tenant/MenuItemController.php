<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function index(?Tenant $tenant = null): View
    {
        $tenant = $this->tenant($tenant);

        return view('tenant.menu.items.index', [
            'tenant' => $tenant,
            'items' => MenuItem::query()
                ->with('category')
                ->forTenant($tenant)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(?Tenant $tenant = null): View
    {
        $tenant = $this->tenant($tenant);

        return view('tenant.menu.items.create', [
            'tenant' => $tenant,
            'item' => new MenuItem(['is_active' => true, 'is_available' => true, 'sort_order' => 0]),
            'categories' => $this->categories($tenant),
        ]);
    }

    public function store(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        $tenant = $this->tenant($tenant);
        $data = $this->validated($request, $tenant);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store("menu-items/{$tenant->id}", 'public');
        }

        $tenant->menuItems()->create($data);

        return redirect($this->routeUrl('items.index', $tenant))->with('status', 'Menu item created.');
    }

    public function edit(MenuItem $item): View
    {
        $this->authorizeTenant($item);

        return view('tenant.menu.items.edit', [
            'tenant' => $item->tenant,
            'item' => $item,
            'categories' => $this->categories($item->tenant),
        ]);
    }

    public function update(Request $request, MenuItem $item): RedirectResponse
    {
        $this->authorizeTenant($item);
        $data = $this->validated($request, $item->tenant);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store("menu-items/{$item->tenant_id}", 'public');
        }

        $item->update($data);

        return redirect($this->routeUrl('items.index', $item->tenant))->with('status', 'Menu item updated.');
    }

    public function toggleAvailability(MenuItem $item): RedirectResponse
    {
        $this->authorizeTenant($item);
        $item->forceFill(['is_available' => ! $item->is_available])->save();

        return back()->with('status', 'Menu item availability updated.');
    }

    public function toggleActive(MenuItem $item): RedirectResponse
    {
        $this->authorizeTenant($item);
        $item->forceFill(['is_active' => ! $item->is_active])->save();

        return back()->with('status', 'Menu item visibility updated.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $this->authorizeTenant($item);
        abort_unless(request()->user()?->hasRole(['super_admin', 'platform_owner']), 403);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();

        return back()->with('status', 'Menu item deleted.');
    }

    private function validated(Request $request, Tenant $tenant): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenant->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenant->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        return $data;
    }

    private function tenant(?Tenant $tenant): Tenant
    {
        $tenant ??= current_tenant();
        abort_unless($tenant, 404);

        return $tenant;
    }

    private function categories(Tenant $tenant)
    {
        return Category::query()->forTenant($tenant)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function authorizeTenant(MenuItem $item): void
    {
        if (! request()->routeIs('platform.*')) {
            abort_unless(current_tenant()?->is($item->tenant), 404);
        }
    }

    private function routeUrl(string $route, Tenant $tenant): string
    {
        return request()->routeIs('platform.*')
            ? route("platform.vendors.menu.{$route}", $tenant)
            : route("tenant.menu.{$route}");
    }
}
