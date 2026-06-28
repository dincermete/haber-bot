@php
    $sourceName = $sourceName ?? '—';
    $status = $status ?? null;
@endphp

<div class="col-span-full flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50">
    <span class="inline-flex items-center rounded-md bg-primary-100 px-2.5 py-1 text-xs font-semibold text-primary-800 dark:bg-primary-900/40 dark:text-primary-200">
        {{ $sourceName }}
    </span>
    @if ($status)
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $status }}</span>
    @endif
</div>
