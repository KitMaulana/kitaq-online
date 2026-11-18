@props(['tone' => 'info', 'title' => null, 'icon' => null])
<div {{ $attributes->class(['alert', $tone]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <x-ui.icon :name="$icon ?? ($tone === 'info' ? 'info' : 'warn')" />
    <div class="flex-1">@if($title)<b>{{ $title }}</b>@endif<p>{{ $slot }}</p></div>
    @isset($action){{ $action }}@endisset
</div>
