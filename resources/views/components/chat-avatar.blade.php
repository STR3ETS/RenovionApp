@props(['channel'])

{{-- Teamkanaal = eerste letter, projectkanaal = gebouw. --}}
@nativeapp
<span {{ $attributes->class('app-chat-av flex shrink-0 items-center justify-center rounded-full') }}>
    @if ($channel->isProjectChannel())
        <x-icon name="folder" class="h-5 w-5" />
    @else
        <span class="text-lg leading-none font-semibold">#</span>
    @endif
</span>
@else
<span {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-full', $channel->isProjectChannel() ? 'bg-navy-950 text-white' : 'bg-navy-100 text-sm font-bold text-navy-700']) }}>
    @if ($channel->isProjectChannel())
        <x-icon name="building-office" class="h-5 w-5" />
    @else
        {{ str($channel->name)->substr(0, 1)->upper() }}
    @endif
</span>
@endnativeapp
