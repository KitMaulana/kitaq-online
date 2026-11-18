@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <div class="card-head">
            <div>
                @if ($title)<h2>{{ $title }}</h2>@endif
                @if ($subtitle)<p>{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="actions">{{ $actions }}</div>@endisset
        </div>
    @endif
    @if ($padding)
        <div class="card-body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
