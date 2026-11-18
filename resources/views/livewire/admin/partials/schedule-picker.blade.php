<div class="card card-body mb-4 flex gap-3 items-end flex-wrap">
    <div class="field flex-1" style="min-width:260px">
        <label for="pick-schedule">Jadwal ujian</label>
        <select id="pick-schedule" class="select" wire:model.live="scheduleId">
            @forelse ($options as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@empty<option value="">Belum ada ujian yang dikerjakan</option>@endforelse
        </select>
    </div>
    {{ $slot ?? '' }}
</div>
