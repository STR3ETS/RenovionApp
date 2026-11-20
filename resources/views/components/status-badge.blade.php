@props(['status'])

{{-- Statuspil uit het prototype: zacht vlak, dunne rand in dezelfde kleur en
     een stipje ervoor. De kleur komt van de status zelf (badgeClasses). --}}
<span {{ $attributes->merge(['class' => 'pill '.$status->badgeClasses()]) }}>
    <span class="d"></span>{{ $status->label() }}
</span>
