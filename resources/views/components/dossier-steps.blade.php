@props(['dossier'])

{{-- De negen stappen van een dossier als één doorlopende lijn, zoals in het
     prototype: wat geweest is, is oranje met een vinkje, de huidige stap heeft
     een oranje ring, de rest is grijs. --}}
<section {{ $attributes->merge(['class' => 'card dsteps mb-[22px]']) }} aria-label="Waar het dossier staat">
    <ol>
        @foreach ($dossier->steps() as $step)
            <li class="{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif>
                <span class="dot">
                    @if ($step['state'] === 'done')
                        <x-icon name="check" />
                    @else
                        {{ $step['nr'] }}
                    @endif
                </span>
                <span class="nm">{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>
</section>
