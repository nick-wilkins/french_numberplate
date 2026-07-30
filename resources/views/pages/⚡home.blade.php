<?php

use App\Support\PlateCatalogue;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @return list<array{label: string, plates: list<array{code: string, name: string, group: string}>}>
     */
    #[Computed]
    public function metropolitanDecades(): array
    {
        return PlateCatalogue::metropolitanDecades();
    }

    /**
     * @return list<array{code: string, name: string, group: string}>
     */
    #[Computed]
    public function overseasPlates(): array
    {
        return PlateCatalogue::overseas();
    }

    #[Computed]
    public function totalPlates(): int
    {
        return PlateCatalogue::count();
    }
};
?>

@php
    $secondaryButton =
        'inline-flex min-h-11 shrink-0 cursor-pointer items-center justify-center rounded-full px-4 text-sm font-semibold ' .
        'ring-1 ring-zinc-950/15 text-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 ' .
        'focus-visible:outline-blue-500 dark:text-zinc-100 dark:ring-white/25';
@endphp

<div data-game data-total="{{ $this->totalPlates }}" data-filter="all" class="flex min-h-dvh flex-col">
    <header
        data-header
        class="sticky top-0 z-30 border-b border-zinc-950/10 bg-white dark:border-white/10 dark:bg-zinc-950"
    >
        <div class="mx-auto max-w-7xl px-4 pt-2.5 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-semibold tabular-nums">
                        Together <span data-found-count>0</span> of {{ $this->totalPlates }}
                    </p>
                    <p
                        class="mt-0.5 hidden items-center gap-x-3 text-sm text-zinc-600 min-[400px]:flex dark:text-zinc-400"
                    >
                        <span class="flex items-center gap-1.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-car-blue" aria-hidden="true"></span>
                            Blue spotted <span class="tabular-nums" data-blue-count>0</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2.5 shrink-0 rounded-full bg-car-red" aria-hidden="true"></span>
                            Red spotted <span class="tabular-nums" data-red-count>0</span>
                        </span>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        data-car-chip
                        class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-full px-4 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                    >
                        <span data-car-chip-label>Blue</span>
                    </button>
                    <button type="button" data-undo disabled class="{{ $secondaryButton }} disabled:cursor-not-allowed disabled:opacity-40">
                        Undo
                    </button>
                    <button type="button" data-open-help class="{{ $secondaryButton }} min-w-11" aria-label="How to play">
                        ?
                    </button>
                    <button type="button" data-open-chat class="relative {{ $secondaryButton }}">
                        Chat
                        <span
                            data-unread
                            hidden
                            class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-car-red px-1 text-xs font-semibold tabular-nums text-white"
                        >0</span>
                    </button>
                </div>
            </div>

            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-950/10 dark:bg-white/15">
                <div
                    data-progress-bar
                    class="h-full w-0 rounded-full bg-linear-to-r from-car-blue to-car-red"
                    role="progressbar"
                    aria-label="Plates found by both cars"
                    aria-valuemin="0"
                    aria-valuemax="{{ $this->totalPlates }}"
                    aria-valuenow="0"
                ></div>
            </div>

            <div role="group" aria-label="Filter plates" class="mt-2.5 grid grid-cols-4 gap-2 pb-2.5">
                <x-filter-chip filter="all" label="All" />
                <x-filter-chip filter="mine" label="My paint" />
                <x-filter-chip filter="ours" label="Found" />
                <x-filter-chip filter="todo" label="To find" />
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 pt-4 pb-24 sm:px-6">
        <div
            data-cheer
            hidden
            class="cheer relative mb-5 rounded-3xl bg-linear-to-br from-car-blue via-purple-600 to-car-red p-6 text-center text-white"
            role="status"
        >
            <p class="text-3xl font-semibold tracking-tight">All 108 plates found!</p>
            <p class="mx-auto mt-2 max-w-[40ch] text-lg text-pretty text-white/90">
                Both cars together spotted every single one. What a trip.
            </p>
            <button
                type="button"
                data-dismiss-cheer
                class="mt-4 inline-flex min-h-11 cursor-pointer items-center rounded-full bg-white/20 px-5 text-base font-semibold text-white ring-1 ring-white/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            >
                Nice one
            </button>
        </div>

        <section data-group>
            <h2 class="text-lg font-semibold tracking-tight">Metropolitan France</h2>
            <p data-group-note class="mt-1 max-w-[56ch] text-base text-pretty text-zinc-600 sm:text-sm dark:text-zinc-400">
                96 plates — 01 to 95, with Corsica splitting 20 into 2A and 2B.
            </p>

            <div class="plate-grid mt-3">
                @foreach ($this->metropolitanDecades as $decade)
                    <div data-section class="plate-decade">
                        <h3
                            class="decade-label bg-white pt-2 pb-1 text-sm font-semibold tabular-nums text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300"
                        >{{ $decade['label'] }}</h3>
                        @foreach ($decade['plates'] as $plate)
                            <x-plate-tile :code="$plate['code']" :name="$plate['name']" />
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        <section data-group class="mt-10">
            <h2 class="text-lg font-semibold tracking-tight">Rare and overseas</h2>
            <p data-group-note class="mt-1 max-w-[56ch] text-base text-pretty text-zinc-600 sm:text-sm dark:text-zinc-400">
                12 long shots from the islands and the far south. Spot one of these and you win the day.
            </p>
            <div data-section class="mt-4">
                <div class="plate-grid">
                    @foreach ($this->overseasPlates as $plate)
                        <x-plate-tile :code="$plate['code']" :name="$plate['name']" />
                    @endforeach
                </div>
            </div>
        </section>

        <p
            data-empty-state
            hidden
            class="mx-auto mt-10 max-w-[40ch] text-center text-lg text-pretty text-zinc-600 dark:text-zinc-400"
        ></p>
    </main>

    <footer class="mx-auto w-full max-w-7xl px-4 pb-10 text-center sm:px-6">
        <div class="border-t border-zinc-950/10 pt-6 dark:border-white/10">
            <p class="text-base font-medium">Welcome to the French Number Plate Game</p>
            <div
                class="mx-auto mt-3 flex h-1.5 w-24 overflow-hidden rounded-full outline outline-black/10 dark:outline-white/15"
                aria-hidden="true"
            >
                <div class="flex-1 bg-tricolore-blue"></div>
                <div class="flex-1 bg-zinc-50 dark:bg-white"></div>
                <div class="flex-1 bg-tricolore-red"></div>
            </div>
            <p class="mt-3 text-base text-zinc-600 sm:text-sm dark:text-zinc-400">Chesterton Wilkins</p>
        </div>
    </footer>

    <div
        data-toast
        hidden
        class="toast pointer-events-none fixed inset-x-0 bottom-[calc(1rem+env(safe-area-inset-bottom))] z-40 flex justify-center px-4"
    >
        <p
            data-toast-text
            class="max-w-full rounded-2xl bg-zinc-950 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-lg dark:bg-white dark:text-zinc-950"
            role="status"
        ></p>
    </div>

    <div
        data-offline
        hidden
        class="pointer-events-none fixed inset-x-0 bottom-[calc(1rem+env(safe-area-inset-bottom))] z-40 flex justify-center px-4"
    >
        <p
            data-offline-text
            class="rounded-2xl bg-amber-400 px-4 py-2.5 text-center text-sm font-semibold text-zinc-950 shadow-lg ring-1 ring-black/10"
            role="status"
        >
            Offline — reconnecting
        </p>
    </div>

    <div data-chat hidden class="fixed inset-0 z-50">
        <div data-chat-backdrop class="absolute inset-0 bg-zinc-950/50"></div>
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="chat-title"
            class="absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] ring-1 ring-zinc-950/10 sm:inset-x-auto sm:right-4 sm:bottom-4 sm:max-h-[36rem] sm:w-96 sm:rounded-3xl dark:bg-zinc-800 dark:ring-white/15"
        >
            <div class="flex items-center justify-between gap-3 border-b border-zinc-950/10 px-4 py-3 dark:border-white/10">
                <h2 id="chat-title" class="text-base font-semibold">Chat</h2>
                <button type="button" data-close-chat class="{{ $secondaryButton }}">Close</button>
            </div>

            <div data-chat-log class="flex min-h-40 flex-1 flex-col gap-2 overflow-y-auto px-4 py-3"></div>

            <form data-chat-form class="flex items-center gap-2 border-t border-zinc-950/10 px-4 py-3 dark:border-white/10">
                <input
                    type="text"
                    name="message"
                    data-chat-input
                    aria-label="Your message"
                    placeholder="Say something…"
                    maxlength="240"
                    autocomplete="off"
                    class="min-h-11 min-w-0 flex-1 rounded-full bg-white px-4 text-base text-zinc-950 ring-1 ring-zinc-950/15 outline-none placeholder:text-zinc-500 focus-visible:outline-2 focus-visible:-outline-offset-1 focus-visible:outline-blue-500 dark:bg-zinc-900 dark:text-white dark:ring-white/20 dark:placeholder:text-zinc-400"
                />
                <button
                    type="submit"
                    data-chat-send
                    class="inline-flex min-h-11 shrink-0 cursor-pointer items-center rounded-full px-5 text-base font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                >
                    Send
                </button>
            </form>
        </div>
    </div>

    <div data-help hidden class="fixed inset-0 z-50">
        <div data-help-backdrop class="absolute inset-0 bg-zinc-950/50"></div>
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="help-title"
            class="absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] ring-1 ring-zinc-950/10 sm:inset-0 sm:m-auto sm:h-fit sm:max-w-lg sm:rounded-3xl dark:bg-zinc-800 dark:ring-white/15"
        >
            <div class="flex items-center justify-between gap-3 border-b border-zinc-950/10 px-4 py-3 dark:border-white/10">
                <h2 id="help-title" class="text-base font-semibold">How to play</h2>
                <button type="button" data-close-help class="{{ $secondaryButton }}">Close</button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4 text-base text-pretty">
                <p>
                    Every French car has a <strong>number</strong> on the right-hand end of its plate. That number says
                    which part of France the car comes from.
                </p>
                <div class="flex items-center justify-center">
                    <span
                        class="flex items-center gap-3 rounded-lg bg-white px-3 py-2 text-2xl font-semibold tabular-nums text-zinc-950 ring-1 ring-zinc-950/20"
                        aria-label="Example plate: A B dash 1 2 3 dash C D, then 33"
                    >
                        <span class="inline-block h-8 w-2 rounded-sm bg-tricolore-blue" aria-hidden="true"></span>
                        AB-123-CD
                        <span class="rounded-sm bg-tricolore-blue px-1.5 py-0.5 text-lg text-white">33</span>
                    </span>
                </div>
                <p>Spot a number on a real car, then <strong>tap that square</strong> to splat your paint on it.</p>
                <ul role="list" class="space-y-2">
                    <li><strong>Tap again</strong> to add more paint, up to four splats.</li>
                    <li><strong>Press and hold</strong> a square to wipe your own paint off it.</li>
                    <li><strong>Undo</strong> removes the very last splat you made.</li>
                </ul>
                <p>
                    The other car sees your paint within about five seconds, and you see theirs. Blue paint is the Blue
                    car, red paint is the Red car. A little blue-and-red badge means <strong>you both found it</strong>.
                </p>
                <ul role="list" class="space-y-2">
                    <li><strong>All</strong> — every plate.</li>
                    <li><strong>My paint</strong> — the ones you have splatted.</li>
                    <li><strong>Found</strong> — the ones either car has splatted.</li>
                    <li><strong>To find</strong> — the ones nobody has spotted yet.</li>
                </ul>
                <p>Win the game by finding all 108 between the two cars.</p>
            </div>
        </div>
    </div>

    <div
        data-car-picker
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="picker-title"
        class="fixed inset-0 z-60 overflow-y-auto bg-white dark:bg-zinc-950"
    >
        <div class="flex min-h-full flex-col items-center justify-center gap-8 px-6 py-10">
            <div class="text-center">
                <h1 id="picker-title" class="mx-auto max-w-[24ch] text-3xl font-semibold tracking-tight text-balance sm:text-5xl">
                    Welcome to the French Number Plate Game
                </h1>
                <div
                    class="mx-auto mt-5 flex h-1.5 w-28 overflow-hidden rounded-full outline outline-black/10 dark:outline-white/15"
                    aria-hidden="true"
                >
                    <div class="flex-1 bg-tricolore-blue"></div>
                    <div class="flex-1 bg-zinc-50 dark:bg-white"></div>
                    <div class="flex-1 bg-tricolore-red"></div>
                </div>
                <p class="mx-auto mt-5 max-w-[56ch] text-base text-pretty text-zinc-600 dark:text-zinc-400">
                    Chesterton Wilkins
                </p>
            </div>

            <div class="w-full max-w-md">
                <h2 data-picker-prompt class="text-center text-2xl font-semibold tracking-tight">
                    Which car are you in?
                </h2>
                <p class="mx-auto mt-3 max-w-[44ch] text-center text-base text-pretty text-zinc-600 dark:text-zinc-400">
                    Tap a plate the moment you spot it. The other car sees your paint within a few seconds.
                </p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <button
                        type="button"
                        data-choose-car="1"
                        aria-pressed="false"
                        class="relative cursor-pointer rounded-3xl bg-car-blue px-6 py-7 text-2xl font-semibold text-white ring-4 ring-transparent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 aria-pressed:ring-zinc-950 dark:aria-pressed:ring-white"
                    >
                        Blue car
                        <span data-current-car="1" hidden class="mt-1 block text-sm font-medium text-white/90">That's you</span>
                    </button>
                    <button
                        type="button"
                        data-choose-car="2"
                        aria-pressed="false"
                        class="relative cursor-pointer rounded-3xl bg-car-red px-6 py-7 text-2xl font-semibold text-white ring-4 ring-transparent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 aria-pressed:ring-zinc-950 dark:aria-pressed:ring-white"
                    >
                        Red car
                        <span data-current-car="2" hidden class="mt-1 block text-sm font-medium text-white/90">That's you</span>
                    </button>
                </div>
                <div class="mt-6 text-center">
                    <button
                        type="button"
                        data-dismiss-picker
                        hidden
                        class="inline-flex min-h-11 cursor-pointer items-center rounded-full px-5 text-base font-semibold text-zinc-800 ring-1 ring-zinc-950/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:text-zinc-100 dark:ring-white/25"
                    >
                        Never mind, keep playing
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
