<?php

use App\Models\Room;
use App\Models\User;
use Livewire\Livewire;

it('form ruang & sesi memakai id unik sehingga tombol Simpan mengirim form yang benar', function () {
    $this->actingAs(staff());

    $html = $this->get(route('admin.rooms'))->getContent();

    preg_match_all('/<form[^>]*id="(crud-form-[^"]+)"/', $html, $forms);
    preg_match_all('/form="(crud-form-[^"]+)"/', $html, $buttons);

    expect($forms[1])->toHaveCount(2)
        ->and(array_unique($forms[1]))->toHaveCount(2)
        ->and($html)->not->toContain('id="crud-form"');
    foreach ($buttons[1] as $id) {
        expect($forms[1])->toContain($id);
    }
});

it('menyimpan ruang baru lewat komponen Rooms', function () {
    $this->actingAs(staff());

    Livewire::test(\App\Livewire\Admin\Rooms::class)
        ->call('openCreate')
        ->set('form.name', 'LAB 1')->set('form.capacity', '40')
        ->call('save')->assertHasNoErrors();

    expect(Room::where('name', 'LAB 1')->exists())->toBeTrue();
});
