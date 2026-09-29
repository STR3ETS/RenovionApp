<x-layouts.app :title="$channel->displayName()">

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Kanalen (desktop) --}}
        <div class="hidden rounded-2xl border border-gray-200 bg-white p-4 lg:block">
            @include('chat.partials.channels', ['channels' => $channels, 'user' => $user, 'active' => $channel->id])
        </div>

        {{-- Gesprek --}}
        <div class="lg:col-span-2"
             x-data="chat({
                 messages: @js($messages),
                 messagesUrl: @js(route('chat.messages', $channel)),
                 confirmUrl: @js(route('chat.nova.confirm', ['message' => '__ID__'])),
                 taskUrl: @js(route('chat.task', ['message' => '__ID__'])),
             })">
            <div class="flex h-[calc(100dvh-14rem)] min-h-96 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white lg:h-[calc(100vh-12rem)]">

                {{-- Kop --}}
                <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
                    <a href="{{ route('chat.index') }}" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 lg:hidden" title="Alle kanalen">
                        <x-icon name="chevron-right" class="h-4 w-4 rotate-180" />
                    </a>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-navy-900">{{ $channel->displayName() }}</span>
                        <span class="block text-xs text-gray-400">{{ $channel->isProjectChannel() ? 'Projectchat — intern, de klant leest niet mee' : 'Teamkanaal' }}</span>
                    </span>
                    @if ($channel->isProjectChannel() && $channel->project)
                        <a href="{{ route('projects.show', $channel->project) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Project →</a>
                    @endif
                </div>

                {{-- Berichten --}}
                <div x-ref="chatScroll" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <template x-for="message in messages" :key="message.id">
                        <div>
                            {{-- Nova --}}
                            <template x-if="message.nova !== null || message.user === null">
                                <div class="flex gap-2">
                                    <x-nova-avatar class="mt-0.5 h-8 w-8 shrink-0" />
                                    <div class="max-w-[85%] rounded-2xl bg-navy-950 px-4 py-3 text-sm text-white">
                                        <p class="mb-1 text-xs font-bold text-brand-300">Nova</p>
                                        <p class="whitespace-pre-line" x-html="format(message.body)"></p>
                                        <template x-if="message.nova?.preview">
                                            <div class="mt-2 rounded-xl bg-navy-900 p-3">
                                                <p class="text-xs text-navy-100" x-text="message.nova.preview"></p>
                                                <div class="mt-2.5 flex gap-2" x-show="!message.nova.executed">
                                                    <button type="button" @click="confirmNova(message)"
                                                            class="rounded-lg bg-brand-500 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-brand-600">Ja, doe maar</button>
                                                    <button type="button" @click="$refs.chatInput.focus()"
                                                            class="rounded-lg border border-navy-700 px-4 py-1.5 text-xs font-semibold text-navy-200 transition hover:bg-navy-800">Eerst aanpassen</button>
                                                </div>
                                                <p x-show="message.nova.executed" class="mt-2 flex items-center gap-1 text-xs font-semibold text-green-400"><x-icon name="check" class="h-3.5 w-3.5" /> Uitgevoerd</p>
                                            </div>
                                        </template>
                                        <template x-if="message.nova?.url && message.nova?.executed">
                                            <a :href="message.nova.url" class="mt-1.5 block text-xs font-semibold text-brand-300 hover:text-brand-200">Bekijken →</a>
                                        </template>
                                        <p class="mt-1.5 text-[10px] text-navy-400" x-text="message.time"></p>
                                    </div>
                                </div>
                            </template>

                            {{-- Teamlid --}}
                            <template x-if="message.nova === null && message.user !== null">
                                <div class="flex gap-2" :class="message.mine ? 'flex-row-reverse' : ''">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                          :class="message.mine ? 'bg-brand-100 text-brand-700' : 'bg-navy-100 text-navy-700'"
                                          x-text="message.user.initial"></span>
                                    <div class="group max-w-[85%]">
                                        <div class="rounded-2xl px-4 py-2.5 text-sm" :class="message.mine ? 'bg-navy-950 text-white' : 'bg-gray-100 text-navy-900'">
                                            <p class="mb-0.5 text-xs font-bold" :class="message.mine ? 'text-navy-300' : 'text-gray-500'" x-text="message.user.name"></p>
                                            <p x-show="message.body" x-html="format(message.body)"></p>
                                            <template x-if="message.attachment_url">
                                                <a :href="message.attachment_url" target="_blank">
                                                    <img :src="message.attachment_url" :alt="message.attachment_name ?? 'Bijlage'" class="mt-1.5 max-h-48 rounded-xl object-cover">
                                                </a>
                                            </template>
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-2 px-1" :class="message.mine ? 'justify-end' : ''">
                                            <span class="text-[10px] text-gray-400" x-text="message.time"></span>
                                            <button type="button" x-show="message.body" @click="toTask(message)"
                                                    class="text-[10px] font-semibold text-gray-400 opacity-0 transition group-hover:opacity-100 hover:text-brand-600">→ Taak</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <p x-show="messages.length === 0" class="py-10 text-center text-sm text-gray-400">
                        Nog geen berichten — trap af!{{ auth()->user()->can('manage-crm') ? ' Tip: noem @Nova om iets te laten klaarzetten.' : '' }}
                    </p>
                </div>

                {{-- Toast --}}
                <div x-show="toast" x-cloak class="border-t border-gray-100 bg-green-50 px-4 py-2 text-xs font-semibold text-green-800" x-text="toast"></div>

                {{-- Invoer --}}
                <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-gray-100 p-3">
                    <label class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200" title="Foto meesturen">
                        <x-icon name="camera" class="h-5 w-5" />
                        <input type="file" accept="image/*" capture="environment" class="hidden" @change="attach($event)">
                    </label>
                    <button type="button" @click="toggleMic()" x-show="speechSupported"
                            :class="listening ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition" title="Inspreken">
                        <x-icon name="microphone" class="h-5 w-5" />
                    </button>
                    <input x-ref="chatInput" x-model="input" type="text" :disabled="busy" maxlength="5000"
                           placeholder="Typ een bericht{{ auth()->user()->can('manage-crm') ? ', of @Nova om iets te laten klaarzetten' : '' }}…"
                           class="min-w-0 flex-1 rounded-xl border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="submit" :disabled="busy || !input.trim()"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-white transition hover:bg-brand-600 disabled:opacity-40" title="Versturen">
                        <x-icon name="paper-airplane" class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>
    </div>

</x-layouts.app>
