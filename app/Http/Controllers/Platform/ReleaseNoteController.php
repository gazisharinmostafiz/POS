<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ReleaseNote;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReleaseNoteController extends Controller
{
    public function index(): View
    {
        return view('platform.releases.index', [
            'releaseNotes' => ReleaseNote::query()->latest('published_at')->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $published = $request->boolean('is_published', true);

        $releaseNote = ReleaseNote::query()->create([
            'created_by' => $request->user()->id,
            'version' => $validated['version'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_published' => $published,
            'published_at' => $published ? now() : null,
            'metadata' => [
                'app_version_at_creation' => config('app.version'),
            ],
        ]);

        $auditLogService->releaseChanged(AuditLogService::RELEASE_NOTE_CREATED, $releaseNote, [
            'version' => $releaseNote->version,
            'published' => $releaseNote->is_published,
        ]);

        return redirect()->route('platform.releases.index')->with('status', 'Release note created.');
    }
}
