@props(['id', 'judul', 'keterangan' => null])

<section id="{{ $id }}" aria-labelledby="{{ $id }}-judul" {{ $attributes->merge(['class' => 'scroll-mt-4 rounded-lg md:scroll-mt-40 border border-slate-200 bg-white']) }}>
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 id="{{ $id }}-judul" class="text-base font-semibold text-slate-900">{{ $judul }}</h2>
            @if ($keterangan)
                <p class="mt-1 text-sm text-slate-600">{{ $keterangan }}</p>
            @endif
        </div>

        @isset($aksi)
            <div class="flex flex-wrap items-center gap-3">{{ $aksi }}</div>
        @endisset
    </header>

    <div class="p-5">
        {{ $slot }}
    </div>
</section>
