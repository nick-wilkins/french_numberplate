@props(['filter', 'label'])

<button
    type="button"
    data-filter-button="{{ $filter }}"
    aria-pressed="false"
    class="flex min-h-14 cursor-pointer flex-col items-center justify-center rounded-2xl px-1 ring-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 aria-pressed:bg-zinc-950 aria-pressed:text-white aria-pressed:ring-zinc-950 not-aria-pressed:text-zinc-700 not-aria-pressed:ring-zinc-950/15 dark:aria-pressed:bg-white dark:aria-pressed:text-zinc-950 dark:aria-pressed:ring-white dark:not-aria-pressed:text-zinc-200 dark:not-aria-pressed:ring-white/20"
>
    <span class="text-lg leading-none font-semibold tabular-nums" data-filter-count="{{ $filter }}">0</span>
    <span class="mt-0.5 text-sm leading-none whitespace-nowrap">{{ $label }}</span>
</button>
