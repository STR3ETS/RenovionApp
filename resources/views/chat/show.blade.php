@php
    // Wie er in dit gesprek aan het woord is geweest, voor de regel onder de naam.
    $leden = collect($messages)->map(fn ($m) => $m['nova'] !== null ? 'Nova' : str($m['user']['name'] ?? '')->before(' ')->toString())->filter()->unique()->values();
    $onderregel = $leden->isNotEmpty()
        ? $leden->take(5)->implode(', ').($leden->count() > 5 ? ' +'.($leden->count() - 5) : '')
        : ($channel->isProjectChannel() ? 'Projectchat, intern: de klant leest niet mee' : 'Teamkanaal');
@endphp

<x-layouts.app :title="$channel->displayName()">

    {{-- Teamchat in de opzet van het prototype: links de gesprekken, rechts het gesprek --}}
    <div class="wa {{ ($listFirst ?? false) ? 'showlist' : '' }}">

        @include('chat.partials.list', ['channels' => $channels, 'user' => $user, 'active' => $channel->id])

        <section class="wa-main"
                 x-data="chat({
                     messages: @js($messages),
                     messagesUrl: @js(route('chat.messages', $channel)),
                     confirmUrl: @js(route('chat.nova.confirm', ['message' => '__ID__'])),
                     taskUrl: @js(route('chat.task', ['message' => '__ID__'])),
                 })">

            <header class="wa-h">
                <a href="{{ route('chat.index') }}" class="iconbtn lg:hidden" aria-label="Terug naar chats"><x-icon name="chevron-left" /></a>
                <span class="wa-av" style="--ac: {{ $channel->isProjectChannel() ? '#d98a0b' : '#3e6ba8' }}">
                    <x-icon :name="$channel->isProjectChannel() ? 'folder' : 'hashtag'" />
                </span>
                <div class="wa-ht">
                    <b>{{ $channel->handle() }}</b>
                    <small>{{ $onderregel }}</small>
                </div>
                @if ($channel->isProjectChannel() && $channel->project)
                    <a href="{{ route('projects.show', $channel->project) }}" class="btn sm"><x-icon name="folder" /><span class="hidden sm:inline">{{ $channel->project->number() }}</span></a>
                @endif
                @can('manage-crm')
                    <button type="button" class="btn sm" title="Vraag Nova iets in dit gesprek"
                            @click="input = '@Nova ' + input; $refs.chatInput.focus()"><x-icon name="sparkles" /><span class="hidden sm:inline">Nova</span></button>
                @endcan
            </header>

            <div x-ref="chatScroll" class="wa-msgs">
                <template x-for="(message, i) in messages" :key="message.id">
                    <div class="contents">
                        {{-- Een nieuwe dag begint met een labeltje --}}
                        <div class="wa-day" x-show="i === 0 || messages[i - 1].date !== message.date"><span x-text="message.day"></span></div>

                        {{-- Nova: een gewone bubbel met een voorstelkaart erin --}}
                        <template x-if="message.nova !== null || message.user === null">
                            <div class="wa-m them">
                                <div class="wa-b">
                                    <b class="wa-n" style="color: #e8590c">Nova <span class="agtag">assistent</span></b>
                                    <div class="wa-t" x-html="format(message.body)"></div>
                                    <template x-if="message.nova?.preview">
                                        <div class="wa-card">
                                            <p x-text="message.nova.preview"></p>
                                            <div class="mt-2 flex flex-wrap gap-2" x-show="!message.nova.executed">
                                                <button type="button" @click="confirmNova(message)" class="btn pri sm">Ja, doe maar</button>
                                                <button type="button" @click="$refs.chatInput.focus()" class="btn sm">Eerst aanpassen</button>
                                            </div>
                                            <p x-show="message.nova.executed" class="mt-2 flex items-center gap-1 text-xs font-semibold text-ok"><x-icon name="check" class="h-3.5 w-3.5" /> Uitgevoerd</p>
                                        </div>
                                    </template>
                                    <template x-if="message.nova?.url && message.nova?.executed">
                                        <a :href="message.nova.url" class="wa-tag">Bekijken</a>
                                    </template>
                                    <span class="wa-time" x-text="message.clock"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Teamlid: eigen berichten rechts, de rest links met de naam in kleur --}}
                        <template x-if="message.nova === null && message.user !== null">
                            <div class="wa-m" :class="[message.mine ? 'me' : 'them', (i > 0 && messages[i - 1].date === message.date && messages[i - 1].user && messages[i - 1].user.name === message.user.name && messages[i - 1].mine === message.mine) ? 'cont' : '']">
                                <div class="wa-b">
                                    <b class="wa-n" :style="'color:' + message.color" x-text="message.user.name"
                                       x-show="! message.mine && ! (i > 0 && messages[i - 1].date === message.date && messages[i - 1].user && messages[i - 1].user.name === message.user.name)"></b>
                                    <div class="wa-t" x-show="message.body" x-html="format(message.body)"></div>
                                    <template x-if="message.attachment_url">
                                        <a :href="message.attachment_url" target="_blank">
                                            <img :src="message.attachment_url" :alt="message.attachment_name ?? 'Bijlage'" class="mt-1 max-h-56 rounded-lg object-cover">
                                        </a>
                                    </template>
                                    <span class="wa-time"><span x-text="message.clock"></span><i class="wa-tick read" x-show="message.mine">✓✓</i></span>
                                </div>
                                <div class="wa-acts" role="toolbar" aria-label="Acties bij bericht" x-show="message.body">
                                    <button type="button" @click="toTask(message)" title="Taak maken" aria-label="Taak maken"><x-icon name="clipboard-check" /></button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="wa-sys" x-show="messages.length === 0">
                    Nog geen berichten. Trap af.{{ auth()->user()->can('manage-crm') ? ' Noem @Nova om iets te laten klaarzetten.' : '' }}
                </div>
            </div>

            <div x-show="toast" x-cloak class="border-t border-line2 bg-ok-soft px-4 py-2 text-xs font-semibold text-ok" x-text="toast"></div>

            <form @submit.prevent="send()" class="wa-comp">
                <label class="wa-ib" title="Foto meesturen">
                    <x-icon name="paper-clip" />
                    <input type="file" accept="image/*" class="hidden" @change="attach($event)">
                </label>
                <button type="button" @click="toggleMic()" x-show="speechSupported" class="wa-ib" :class="listening && 'listening'" title="Inspreken">
                    <x-icon name="microphone" />
                </button>
                <input x-ref="chatInput" x-model="input" type="text" :disabled="busy" maxlength="5000" aria-label="Bericht"
                       placeholder="Bericht, @naam{{ auth()->user()->can('manage-crm') ? ' of @Nova' : '' }}">
                <button type="submit" class="wa-send" :disabled="busy || !input.trim()" aria-label="Versturen">
                    <x-icon name="arrow-right" />
                </button>
            </form>
        </section>
    </div>

</x-layouts.app>
