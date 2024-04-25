<x-app-layout>
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div class="relative min-h-full flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
            <div class="mt-3 relative w-full max-w-2xl px-6 lg:max-w-7xl">

                <div class="grid gap-2 lg:grid-cols-2 lg:gap-2">
                    <div
                        id="docs-card"
                        class="flex flex-col h-fit items-start gap-2 overflow-hidden rounded-lg bg-white p-3 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1
                        ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] md:row-span-3
                        dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"
                    >
                        <div class="w-full h-28 flex justify-center items-center">
                            <x-primary-button class="text-3xl">Вхід</x-primary-button>
                        </div>

                        <div id="screenshot-container" class="relative flex w-full flex-1 items-stretch">
                            <img
                                src="{{asset('images/ev-futura-1.opti.webp')}}"
                                alt="EV future"
                                class="aspect-video h-full w-full flex-1 rounded-[10px] object-top object-cover drop-shadow-[0px_4px_34px_rgba(0,0,0,0.06)] dark:hidden"
                                onerror="
                                            document.getElementById('screenshot-container').classList.add('!hidden');
                                            document.getElementById('docs-card').classList.add('!row-span-1');
                                            document.getElementById('docs-card-content').classList.add('!flex-row');
                                            document.getElementById('background').classList.add('!hidden');
                                        "
                            />
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-lg bg-white p-3 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300
                        hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70
                        dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"
                        >
                            <img src="{{asset('images/subaru_solterra_ev.opti.webp')}}" alt="Subaru Solterra">
                        </div>

                    </div>

                    <div
                        class="flex flex-col h-fit items-start gap-2 overflow-hidden rounded-lg bg-white p-3 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1
                        ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] md:row-span-3
                        dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"
                    >
                        <div class="relative flex items-center gap-2 lg:items-end">
                            <img src="{{asset('images/lucid-air.opti.webp')}}" alt="Lucid Air">
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-lg bg-white p-3 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300
                        hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70
                        dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"
                        >
                            <img src="{{asset('images/Toyota-bZ3-EV.webp')}}" alt="Toyota Z3">
                        </div>
                        <div
                            class="flex items-start gap-2 rounded-lg bg-white p-3 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300
                        hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70
                        dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]">
                            <img src="{{asset('images/aehra.opti.webp')}}" alt="Aehra">
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
