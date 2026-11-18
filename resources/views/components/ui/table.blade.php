@props(['sticky' => true])
<div {{ $attributes->class(['table-wrap']) }} @if(! $sticky) style="max-height:none" @endif>
    <table>
        @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
    @isset($empty){{ $empty }}@endisset
</div>
