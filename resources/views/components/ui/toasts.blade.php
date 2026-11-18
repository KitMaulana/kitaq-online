{{-- Wadah toast (dirender sekali di layout). --}}
<div class="toasts" aria-live="polite" x-data>
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="toast" :style="'--c:' + t.color" role="status">
            <svg class="ico ic"><use :href="'#i-' + t.icon"/></svg>
            <div class="flex-1"><b x-text="t.title"></b><small x-show="t.text" x-text="t.text"></small></div>
            <button type="button" class="icon-btn" style="width:28px;height:28px" aria-label="Tutup" x-on:click="$store.toasts.remove(t.id)"><svg class="ico sm"><use href="#i-x"/></svg></button>
        </div>
    </template>
</div>
@foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'status' => 'info'] as $key => $type)
    @if (session($key))<span hidden data-flash="{{ session($key) }}" data-type="{{ $type }}"></span>@endif
@endforeach
