<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuCategoryController extends Controller
{
    public function index(?Tenant $tenant = null): View
    {
        $tenant = $this->tenant($tenant);

        return view('tenant.menu.categories.index', [
            'tenant' => $tenant,
            'categories' => Category::query()
                ->forTenant($tenant)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(?Tenant $tenant = null): View
    {
        return view('tenant.menu.categories.create', [
            'tenant' => $this->tenant($tenant),
            'category' => new Category(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        $tenant = $this->tenant($tenant);
        $tenant->categories()->create($this->validated($request));

        return redirect($this->routeUrl('categories.index', $tenant))->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        $this->authorizeTenant($category);

        return view('tenant.menu.categories.edit', [
            'tenant' => $category->tenant,
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeTenant($category);
        $category->update($this->validated($request));

        return redirect($this->routeUrl('categories.index', $category->tenant))->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeTenant($category);
        abort_unless($this->canDelete(), 403);

        $category->delete();

        return back()->with('status', 'Category deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function tenant(?Tenant $tenant): Tenant
    {
        $tenant ??= current_tenant();
        abort_unless($tenant, 404);

        return $tenant;
    }

    private function authorizeTenant(Category $category): void
    {
        if (! request()->routeIs('platform.*')) {
            abort_unless(current_tenant()?->is($category->tenant), 404);
        }
    }

    private function canDelete(): bool
    {
        return request()->user()?->hasRole(['super_admin', 'platform_owner']) ?? false;
    }

    private function routeUrl(string $route, Tenant $tenant): string
    {
        return request()->routeIs('platform.*')
            ? route("platform.vendors.menu.{$route}", $tenant)
            : route("tenant.menu.{$route}");
    }
}
