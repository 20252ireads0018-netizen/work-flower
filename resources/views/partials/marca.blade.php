{{-- Marca do produto: mini flor + nome. Uso: @include('partials.marca', ['texto' => 'text-xl']) --}}
<span class="inline-flex items-center gap-2.5">
    <svg class="w-6 h-6 shrink-0" viewBox="-12 -12 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true">
        @foreach (range(0, 5) as $i)
            <path transform="rotate({{ $i * 60 }})" d="M0-3.6 L3.4-7.6 L0-11.4 L-3.4-7.6Z"/>
        @endforeach
        <circle r="2" fill="var(--prim)" stroke="none"/>
    </svg>
    <span class="titulo leading-none {{ $texto ?? 'text-xl' }}">Work Flower</span>
</span>