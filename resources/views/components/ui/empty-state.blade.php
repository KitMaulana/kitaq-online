@props(['icon' => 'layers', 'title' => 'Belum ada data', 'text' => null])
<div {{ $attributes->class(['empty']) }}>
    <div class="ic"><x-ui.icon :name="$icon" size="lg" /></div>
    <b>{{ $title }}</b>
    @if ($text)<p class="text-sm max-w-md">{{ $text }}</p>@endif
    @if (trim($slot) !== '')<div class="mt-4 flex gap-2">{{ $slot }}</div>@endif
</div>
