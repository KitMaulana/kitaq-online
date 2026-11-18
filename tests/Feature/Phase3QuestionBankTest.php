<?php

use App\Livewire\Admin\BankDetail;
use App\Livewire\Admin\QuestionEditor;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Services\Questions\HtmlCleaner;
use App\Services\Questions\ImageOptimizer;
use App\Services\Questions\QuestionImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/** Salin template_soal.xlsx dengan tanda "#" di kolom no dihapus (sesuai petunjuk template). */
function templateSoalAktif(): string
{
    $out = tempnam(sys_get_temp_dir(), 'kqt') . '.xlsx';
    $reader = new Reader();
    $reader->open(base_path('templates/template_soal.xlsx'));
    $writer = new Writer();
    $writer->openToFile($out);
    $first = true;
    foreach ($reader->getSheetIterator() as $sheet) {
        if (! $first) {
            $writer->addNewSheetAndMakeItCurrent();
        }
        $writer->getCurrentSheet()->setName($sheet->getName());
        $first = false;
        foreach ($sheet->getRowIterator() as $row) {
            $vals = array_map(fn ($c) => $c->getValue(), $row->getCells());
            if (isset($vals[0]) && is_string($vals[0]) && str_starts_with($vals[0], '#') && $sheet->getName() === 'soal') {
                $vals[0] = ltrim($vals[0], '#');
            }
            $writer->addRow(Row::fromValues($vals));
        }
    }
    $writer->close();
    $reader->close();

    return $out;
}

beforeEach(function () {
    Storage::fake('local');
    $this->subject = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia']);
    $this->bank = QuestionBank::query()->create(['subject_id' => $this->subject->id, 'name' => 'Bank BIN']);
});

it('mengimport template_soal.xlsx + contoh_gambar_soal.zip (6 tipe, stimulus, gambar WebP)', function () {
    $importer = app(QuestionImporter::class);
    $res = $importer->analyze(templateSoalAktif(), base_path('templates/contoh_gambar_soal.zip'));
    expect($res['rows'])->toHaveCount(8)
        ->and(collect($res['rows'])->filter(fn ($r) => $r['errors'])->all())->toBe([])
        ->and($res['stimuli'])->toHaveKey('TX01');

    $n = $importer->import($this->bank, $res['rows'], $res['stimuli'], base_path('templates/contoh_gambar_soal.zip'));
    expect($n)->toBe(8);

    $types = Question::query()->pluck('type')->sort()->values()->all();
    expect($types)->toBe(['bs', 'isian', 'jodoh', 'pg', 'pg', 'pg', 'pgk', 'uraian']);

    $bs = Question::query()->where('type', 'bs')->first();
    expect($bs->answer_key)->toBe(['1' => true, '2' => false, '3' => true]);
    $jodoh = Question::query()->where('type', 'jodoh')->with('options')->first();
    expect($jodoh->answer_key)->toBe(['1' => 'c', '2' => 'a', '3' => 'b'])
        ->and($jodoh->options->where('side', 'left')->pluck('content')->all())->toContain('Sinonim');
    $isian = Question::query()->where('type', 'isian')->first();
    expect($isian->answer_key)->toBe(['Serang', 'Kota Serang']);

    $withImage = Question::query()->whereNotNull('image')->first();
    expect($withImage->image)->toEndWith('.webp');
    Storage::disk('local')->assertExists('media/' . $withImage->image);
    expect(Question::query()->whereNotNull('stimulus_id')->count())->toBe(3);
});

it('menolak ZIP berisi path traversal (zip-slip) dan berkas bukan gambar', function () {
    $zipPath = tempnam(sys_get_temp_dir(), 'kqz') . '.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('../../evil.png', 'x');
    $zip->addFromString('folder/a.png', 'x');
    $zip->addFromString('skrip.php', '<?php echo 1;');
    $zip->addFromString('ok.png', file_get_contents(base_path('public/icons/icon-72.png')));
    $zip->close();

    [$names, $errors] = app(QuestionImporter::class)->inspectZip($zipPath);
    expect(array_keys($names))->toBe(['ok.png'])->and($errors)->toHaveCount(3);
    expect(QuestionImporter::safeName('../x.png'))->toBeFalse()
        ->and(QuestionImporter::safeName('C:\\x.png'))->toBeFalse()
        ->and(QuestionImporter::safeName('grafik_01.png'))->toBeTrue();
});

it('membersihkan HTML soal dari skrip, event, dan gambar eksternal', function () {
    $clean = app(HtmlCleaner::class)->clean('<p onclick="x()">Halo <b>dunia</b><script>alert(1)</script></p><img src="https://jahat.test/a.png"><img src="/admin/media/questions/a.webp"><iframe src="x"></iframe>');
    expect($clean)->toContain('<b>dunia</b>')->toContain('/admin/media/questions/a.webp')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('jahat.test')->not->toContain('iframe');
});

it('mengubah gambar besar menjadi WebP maksimal 1600 px', function () {
    $img = imagecreatetruecolor(3200, 1000);
    ob_start();
    imagepng($img);
    $png = ob_get_clean();
    $path = app(ImageOptimizer::class)->storeFromString($png);
    $stored = Storage::disk('local')->get('media/' . $path);
    [$w, $h] = getimagesizefromstring($stored);
    expect($path)->toEndWith('.webp')->and($w)->toBe(1600)->and($h)->toBe(500);
});

it('editor menyimpan soal PG dengan kunci dan pratinjau tidak bocor ke siswa', function () {
    Livewire::actingAs(staff('super_admin'))->test(QuestionEditor::class, ['bank' => $this->bank])
        ->set('type', 'pg')
        ->set('content', '<p>Berapa 2 + 2? $x^2$</p><script>x</script>')
        ->set('options.0.content', '3')->set('options.1.content', '4')->set('options.2.content', '5')->set('options.3.content', '6')->set('options.4.content', '7')
        ->set('keyPg', 'B')
        ->call('save', false)
        ->assertHasNoErrors();
    $q = Question::query()->with('options')->firstOrFail();
    expect($q->answer_key)->toBe('B')->and($q->content)->not->toContain('script')->and($q->options)->toHaveCount(5)
        ->and($q->toArray())->not->toHaveKey('answer_key');
});

it('editor menolak PG tanpa kunci dan isian tanpa jawaban', function () {
    $c = Livewire::actingAs(staff())->test(QuestionEditor::class, ['bank' => $this->bank])
        ->set('content', '<p>Soal</p>')
        ->set('options.0.content', 'a')->set('options.1.content', 'b')->set('options.2.content', 'c')->set('options.3.content', 'd')->set('options.4.content', 'e')
        ->call('save', false)->assertHasErrors('keyPg');
    $c->set('type', 'isian')->set('content', '<p>Ibu kota?</p>')->call('save', false)->assertHasErrors('keyIsian');
});

it('guru hanya mengelola bank soal mapel yang diampu', function () {
    $guru = staff('guru');
    $this->actingAs($guru)->get(route('admin.banks.show', $this->bank))->assertForbidden();
    $guru->subjects()->attach($this->subject->id);
    $this->actingAs($guru)->get(route('admin.banks.show', $this->bank))->assertOk();
    $this->actingAs(staff('pengawas'))->get(route('admin.banks'))->assertForbidden();
});

it('halaman bank soal dan editor tampil', function () {
    bankWithQuestions();
    $admin = staff();
    $this->actingAs($admin)->get(route('admin.banks'))->assertOk()->assertSee('Bank Soal');
    $bank = QuestionBank::query()->where('name', 'Bank Uji')->first();
    $this->actingAs($admin)->get(route('admin.banks.show', $bank))->assertOk()->assertSee('Soal pg');
    $q = $bank->questions()->where('type', 'jodoh')->first();
    $this->actingAs($admin)->get(route('admin.banks.question', [$bank, $q]))->assertOk()->assertSee('Pratinjau tampilan siswa');
});

it('import lewat Livewire BankDetail', function () {
    $x = UploadedFile::fake()->createWithContent('soal.xlsx', file_get_contents(templateSoalAktif()));
    $z = UploadedFile::fake()->createWithContent('gambar.zip', file_get_contents(base_path('templates/contoh_gambar_soal.zip')));
    Livewire::actingAs(staff())->test(BankDetail::class, ['bank' => $this->bank])
        ->set('xlsx', $x)->set('zip', $z)->call('analyzeImport')->assertSet('importStep', 2)
        ->call('runImport')->assertSet('importStep', 3);
    expect(Question::query()->where('question_bank_id', $this->bank->id)->count())->toBe(8);
});
