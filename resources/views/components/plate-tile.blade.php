@props(['code', 'name'])

<button
    type="button"
    data-plate
    data-code="{{ $code }}"
    data-name="{{ $name }}"
    class="plate relative isolate flex aspect-square cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl bg-white ring-1 ring-zinc-950/15 select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:bg-zinc-400 dark:ring-black/25"
>
    <span data-splats class="plate-splats" aria-hidden="true"></span>

    <span class="plate-stripe bg-tricolore-blue" aria-hidden="true"></span>

    <span class="plate-number text-2xl font-semibold tabular-nums text-zinc-950 min-[420px]:text-3xl">{{ $code }}</span>

    <span
        data-both
        hidden
        class="plate-both"
        aria-hidden="true"
    >
        <span class="flex-1 bg-car-blue"></span>
        <span class="flex-1 bg-car-red"></span>
    </span>

    <span
        aria-hidden="true"
        class="plate-name hidden truncate bg-white px-1.5 py-0.5 text-[0.6875rem] text-zinc-700 lg:block dark:bg-zinc-200 dark:text-zinc-800"
    >{{ $name }}</span>

    <span class="sr-only" data-plate-status>{{ $name }}. Nobody has spotted this one yet.</span>
</button>
