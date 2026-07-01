<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Release Notes</title>
    @include('partials.pwa')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <main class="mx-auto max-w-4xl px-4 py-6">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Updates</p>
                <h1 class="text-3xl font-black">Release notes</h1>
            </div>
            <a href="{{ route('areas.tenant-admin') }}" class="rounded bg-white px-3 py-2 text-sm font-bold">Back to admin</a>
        </header>

        <section class="space-y-4">
            @forelse ($releaseNotes as $note)
                <article class="rounded-lg border border-slate-300 bg-white p-5">
                    <p class="text-sm font-bold text-slate-500">Version {{ $note->version }}</p>
                    <h2 class="mt-1 text-xl font-black">{{ $note->title }}</h2>
                    <p class="mt-3 whitespace-pre-line text-slate-700">{{ $note->body }}</p>
                    @if ($note->published_at)
                        <p class="mt-4 text-xs font-bold text-slate-500">Published {{ $note->published_at->toDateString() }}</p>
                    @endif
                </article>
            @empty
                <p class="rounded-lg border border-slate-300 bg-white p-5 text-slate-600">No release notes have been published yet.</p>
            @endforelse
        </section>

        <footer class="mt-8 text-xs font-bold text-slate-500">
            Version {{ config('app.version') }}
        </footer>
    </main>
</body>
</html>
