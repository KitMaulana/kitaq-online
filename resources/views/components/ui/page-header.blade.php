@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="page-head">
    <div>
        @if ($crumbs)
            <div class="crumbs">
                @foreach ($crumbs as $label => $url)
                    @if (is_string($label))<a href="{{ $url }}" class="muted">{{ $label }}</a><x-ui.icon name="chev" size="sm" />@else<span>{{ $url }}</span>@endif
                @endforeach
            </div>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="actions">{{ $actions }}</div>@endisset
</div>
