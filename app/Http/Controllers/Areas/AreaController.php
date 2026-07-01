<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function platform(): View
    {
        return view('areas.platform');
    }

    public function tenantAdmin(): View
    {
        return view('areas.tenant-admin');
    }

    public function waiter(): View
    {
        return view('areas.waiter', [
            'menuItems' => MenuItem::query()
                ->with('category')
                ->forTenant(current_tenant())
                ->forBranch(current_branch())
                ->visibleToWaiter()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function counter(): View
    {
        return view('areas.counter');
    }

    public function kitchen(): View
    {
        return view('areas.kitchen');
    }
}
