<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ReleaseNote;
use Illuminate\View\View;

class ReleaseNoteController extends Controller
{
    public function index(): View
    {
        return view('tenant.release-notes.index', [
            'releaseNotes' => ReleaseNote::query()
                ->where('is_published', true)
                ->latest('published_at')
                ->latest()
                ->get(),
        ]);
    }
}
