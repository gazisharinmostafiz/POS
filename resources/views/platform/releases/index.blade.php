@extends('platform.layout')

@section('title', 'Release Notes')

@section('content')
    <h1 class="text-3xl font-bold">Release notes</h1>
    <p class="mt-2 text-slate-400">Publish update notes for vendors after each deployment.</p>

    <section class="mt-6 rounded-lg border border-slate-800 bg-slate-900 p-6">
        <h2 class="text-xl font-bold">Create release note</h2>
        <form method="POST" action="{{ route('platform.releases.store') }}" class="mt-4 grid gap-4">
            @csrf
            <label class="block">
                <span class="text-sm font-bold text-slate-300">Version</span>
                <input name="version" value="{{ old('version', config('app.version')) }}" class="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                @error('version')<p class="mt-1 text-sm text-red-300">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-300">Title</span>
                <input name="title" value="{{ old('title') }}" class="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                @error('title')<p class="mt-1 text-sm text-red-300">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-300">Notes</span>
                <textarea name="body" rows="6" class="mt-1 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-sm text-red-300">{{ $message }}</p>@enderror
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" name="is_published" value="1" checked class="rounded border-slate-700 bg-slate-950">
                Publish for vendors
            </label>
            <button class="w-fit rounded bg-emerald-500 px-4 py-2 font-bold text-slate-950">Create release note</button>
        </form>
    </section>

    <section class="mt-6 space-y-3">
        @forelse ($releaseNotes as $note)
            <article class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-500">Version {{ $note->version }}</p>
                        <h2 class="text-xl font-bold">{{ $note->title }}</h2>
                    </div>
                    <span class="rounded px-2 py-1 text-xs font-bold {{ $note->is_published ? 'bg-emerald-950 text-emerald-300' : 'bg-slate-800 text-slate-300' }}">
                        {{ $note->is_published ? 'Published' : 'Draft' }}
                    </span>
                </div>
                <p class="mt-3 whitespace-pre-line text-slate-300">{{ $note->body }}</p>
            </article>
        @empty
            <p class="rounded-lg border border-slate-800 bg-slate-900 p-5 text-slate-400">No release notes yet.</p>
        @endforelse
    </section>

    <div class="mt-6">{{ $releaseNotes->links() }}</div>
@endsection
