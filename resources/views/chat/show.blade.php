<x-layouts.app :title="$channel->displayName()">

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Kanalen (desktop) --}}
        <div class="hidden overflow-y-auto rounded-2xl border border-gray-200 bg-white lg:block lg:h-[calc(100vh-12rem)]">
            @include('chat.partials.channels', ['channels' => $channels, 'user' => $user, 'active' => $channel->id])
        </div>

        {{-- Gesprek: op de telefoon schermvullend, zonder app-kop en bottom-nav --}}
        <div class="min-w-0 lg:col-span-2"
             x-data="chat({
                 messages: @js($messages),
                 messagesUrl: @js(route('chat.messages', $channel)),
                 confirmUrl: @js(route('chat.nova.confirm', ['message' => '__ID__'])),
                 taskUrl: @js(route('chat.task', ['message' => '__ID__'])),
             })">
            <div data-no-enter x-ref="panel" class="chat-panel fixed inset-0 z-50 flex flex-col overflow-hidden bg-white pt-safe lg:static lg:z-auto lg:h-[calc(100vh-12rem)] lg:rounded-2xl lg:border lg:border-gray-200 lg:pt-0">

                {{-- Kop --}}
                <header class="flex shrink-0 items-center gap-2 border-b border-gray-100 px-2 py-2 lg:px-4">
                    <a href="{{ route('chat.index') }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-600 transition active:bg-gray-100 lg:hidden" title="Alle kanalen">
                        <x-icon name="chevron-right" class="h-5 w-5 rotate-180" />
                    </a>
                    <x-chat-avatar :channel="$channel" class="h-9 w-9" />
                    <span class="min-w-0 flex-1 pl-1">
                        <span class="block truncate text-sm font-bold text-navy-950">{{ $channel->displayName() }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ $channel->isProjectChannel() ? 'Projectchat, de klant leest niet mee' : 'Teamkanaal' }}</span>
                    </span>
                    @if ($channel->isProjectChannel() && $channel->project)
                        <a href="{{ route('projects.show', $channel->project) }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 active:bg-gray-100" title="Naar het project">
                            <x-icon name="building-office" class="h-5 w-5" />
                        </a>
                    @endif
                </header>

                {{-- Berichten --}}
                <div x-ref="chatScroll" class="chat-scroll flex-1 overflow-y-auto px-3 pt-1 pb-3 lg:px-4">
                    <template x-for="item in thread" :key="item.message.id">
                        <div :class="item.startsGroup ? 'mt-4' : 'mt-0.5'">
                            <p x-show="item.newDay" class="mb-4 text-center">
                                <span class="chat-day rounded-full bg-gray-100 px-3 py-1 text-[11px] font-semibold text-gray-600" x-text="item.message.day"></span>
                            </p>

                            {{-- Nova --}}
                            <template x-if="item.message.nova !== null || item.message.user === null">
                                <div class="flex items-end gap-2">
                                    <x-nova-avatar class="h-7 w-7 shrink-0" />
                                    <div class="max-w-[85%] rounded-2xl bg-navy-950 px-3.5 py-2.5 text-sm text-white">
                                        <p class="mb-0.5 text-xs font-bold text-brand-300" x-show="item.startsGroup">Nova</p>
                                        <p class="whitespace-pre-line" x-html="format(item.message.body)"></p>
                                        <template x-if="item.message.nova?.preview">
                                            <div class="mt-2 rounded-xl bg-navy-900 p-3">
                                                <p class="text-xs text-navy-100" x-text="item.message.nova.preview"></p>
                                                <div class="mt-2.5 flex gap-2" x-show="!item.message.nova.executed">
                                                    <button type="button" @click="confirmNova(item.message)"
                                                            class="rounded-lg bg-brand-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-600 active:bg-brand-600">Ja, doe maar</button>
                                                    <button type="button" @click="$refs.chatInput.focus()"
                                                            class="rounded-lg border border-navy-700 px-4 py-2 text-xs font-semibold text-navy-200 transition hover:bg-navy-800">Eerst aanpassen</button>
                                                </div>
                                                <p x-show="item.message.nova.executed" class="mt-2 flex items-center gap-1 text-xs font-semibold text-green-400"><x-icon name="check" class="h-3.5 w-3.5" /> Uitgevoerd</p>
                                            </div>
                                        </template>
                                        <template x-if="item.message.nova?.url && item.message.nova?.executed">
                                            <a :href="item.message.nova.url" class="mt-1.5 block text-xs font-semibold text-brand-300 hover:text-brand-200">Bekijken →</a>
                                        </template>
                                        <p class="mt-1 text-right text-[10px] text-navy-300" x-text="item.message.time"></p>
                                    </div>
                                </div>
                            </template>

                            {{-- Teamlid: naam en avatar één keer per reeks, eigen berichten zonder --}}
                            <template x-if="item.message.nova === null && item.message.user !== null">
                                <div class="flex items-end gap-2" :class="item.message.mine && 'justify-end'">
                                    <span x-show="!item.message.mine" :class="!item.endsGroup && 'invisible'"
                                          class="chat-who mb-4 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy-100 text-[11px] font-bold text-navy-700 app:mb-0"
                                          :style="'--who:' + nameColor(item.message.user.name)"
                                          x-text="item.message.user.initial"></span>
                                    <div class="min-w-0 max-w-[80%]">
                                        <p x-show="!item.message.mine && item.startsGroup" class="chat-name mb-0.5 ml-1 text-xs font-semibold text-gray-500" :style="'--who:' + nameColor(item.message.user.name)" x-text="item.message.user.name"></p>
                                        <div @click="toggleActions(item.message)" title="Tik voor acties"
                                             class="chat-bubble w-fit cursor-pointer rounded-2xl px-3.5 py-2 text-sm break-words"
                                             :class="item.message.mine ? 'mine ml-auto bg-navy-950 text-white' : 'bg-gray-100 text-navy-950'">
                                            <template x-if="item.message.attachment_url">
                                                <button type="button" class="-mx-1 mb-1 block cursor-zoom-in first:-mt-0.5" title="Foto bekijken"
                                                        :data-photo="item.message.attachment_url" :data-caption="item.message.body || item.message.attachment_name || ''"
                                                        :data-meta="item.message.user.name + ', ' + item.message.day + ' ' + item.message.time">
                                                    <img :src="item.message.attachment_url" :alt="item.message.attachment_name ?? 'Bijlage'" class="max-h-56 rounded-xl object-cover">
                                                </button>
                                            </template>
                                            <p x-show="item.message.body" x-html="format(item.message.body)"></p>
                                            <span class="mt-0.5 hidden text-right text-[10.5px] leading-none text-gray-500 app:block" x-text="item.message.time"></span>
                                        </div>

                                        {{-- Tijd onder de laatste van een reeks; taak maken pas na tikken op een bericht --}}
                                        <div x-show="{{ \App\Support\NativeApp::active() ? '' : 'item.endsGroup || ' }}actionsFor === item.message.id" class="mt-0.5 flex h-4 items-center gap-3 px-1" :class="item.message.mine && 'justify-end'">
                                            <button type="button" x-show="actionsFor === item.message.id && item.message.body" @click="toTask(item.message)"
                                                    class="tap flex items-center gap-1 text-[11px] font-semibold text-brand-700">
                                                <x-icon name="clipboard-check" class="h-3.5 w-3.5" /> Taak maken
                                            </button>
                                            <span class="text-[10px] text-gray-500 app:hidden" x-text="item.message.time"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div x-show="messages.length === 0" class="flex h-full flex-col items-center justify-center px-6 text-center">
                        <p class="text-sm font-semibold text-navy-900">Nog geen berichten</p>
                        @can('manage-crm')
                            <p class="mt-1 text-sm text-gray-500">Noem @Nova om iets te laten klaarzetten.</p>
                        @endcan
                    </div>
                </div>

                {{-- Toast --}}
                <div x-show="toast" x-cloak class="shrink-0 bg-green-50 px-4 py-2 text-center text-xs font-semibold text-green-800" x-text="toast"></div>

                {{-- Invoer --}}
                <form @submit.prevent="send()" class="flex shrink-0 items-center gap-2 border-t border-gray-100 px-2 pt-2 pb-[calc(0.5rem_+_env(safe-area-inset-bottom))] lg:px-3 lg:pb-2">
                    <div class="flex min-w-0 flex-1 items-center rounded-full bg-gray-100 pr-1 pl-1 focus-within:ring-2 focus-within:ring-brand-500">
                        <label class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full text-gray-600 transition active:bg-gray-200" title="Foto meesturen">
                            <x-icon name="camera" class="h-5 w-5" />
                            <input type="file" accept="image/*" capture="environment" class="hidden" aria-label="Foto meesturen" @change="attach($event)">
                        </label>
                        <input x-ref="chatInput" x-model="input" type="text" :disabled="busy" maxlength="5000"
                               placeholder="Bericht{{ auth()->user()->can('manage-crm') ? ' of @Nova' : '' }}" aria-label="Bericht"
                               class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2.5 text-base placeholder:text-gray-500 focus:ring-0 lg:text-sm">
                        <button type="button" @click="toggleMic()" x-show="speechSupported && !input.trim()"
                                :class="listening ? 'animate-pulse bg-red-500 text-white' : 'text-gray-600 active:bg-gray-200'"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition" title="Inspreken">
                            <x-icon name="microphone" class="h-5 w-5" />
                        </button>
                    </div>
                    <button type="submit" x-show="input.trim()" x-cloak :disabled="busy"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 active:bg-brand-600 disabled:opacity-40" title="Versturen">
                        <x-icon name="paper-airplane" class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>
    </div>

</x-layouts.app>
