<?php

use App\Support\PlateCatalogue;

test('the catalogue holds 96 metropolitan plates and 12 rare ones', function () {
    expect(PlateCatalogue::metropolitan())->toHaveCount(96)
        ->and(PlateCatalogue::overseas())->toHaveCount(12)
        ->and(PlateCatalogue::all())->toHaveCount(108)
        ->and(PlateCatalogue::count())->toBe(108);
});

test('every code is unique', function () {
    $codes = PlateCatalogue::codes();

    expect($codes)->toHaveCount(108)
        ->and(array_unique($codes))->toHaveCount(108);
});

test('corsica replaces department 20', function () {
    $codes = PlateCatalogue::codes();

    expect($codes)->toContain('2A')
        ->toContain('2B')
        ->not->toContain('20');
});

test('codes that are not real departments are absent', function () {
    $codes = PlateCatalogue::codes();

    expect($codes)->not->toContain('96')
        ->not->toContain('00')
        ->not->toContain('970')
        ->not->toContain('979')
        ->not->toContain('985');
});

test('single digit departments keep their leading zero', function () {
    $codes = PlateCatalogue::codes();

    expect($codes)->toContain('01')
        ->toContain('09')
        ->not->toContain('1')
        ->not->toContain('9');
});

test('codes are ordered with corsica sitting between 19 and 21', function () {
    $codes = PlateCatalogue::codes();

    expect(array_slice($codes, 18, 4))->toBe(['19', '2A', '2B', '21']);
});

test('metropolitan plates split into ten decades covering all 96', function () {
    $decades = PlateCatalogue::metropolitanDecades();

    expect($decades)->toHaveCount(10);

    $codes = [];

    foreach ($decades as $decade) {
        expect($decade['plates'])->not->toBeEmpty()
            ->and($decade['label'])->toMatch('/^\S+–\S+$/');

        foreach ($decade['plates'] as $plate) {
            $codes[] = $plate['code'];
        }
    }

    expect($codes)->toHaveCount(96)
        ->and($codes)->toBe(array_column(PlateCatalogue::metropolitan(), 'code'));
});

test('corsica is grouped with the twenties because 2A and 2B replaced 20', function () {
    $decades = collect(PlateCatalogue::metropolitanDecades())->keyBy('label');

    expect($decades)->toHaveKey('2A–29');

    $codes = array_column($decades['2A–29']['plates'], 'code');

    expect($codes)->toBe(['2A', '2B', '21', '22', '23', '24', '25', '26', '27', '28', '29']);
});

test('every plate carries a department name and a group', function () {
    foreach (PlateCatalogue::all() as $plate) {
        expect($plate['name'])->not->toBe('')
            ->and($plate['group'])->toBeIn([
                PlateCatalogue::GROUP_METROPOLITAN,
                PlateCatalogue::GROUP_OVERSEAS,
            ]);
    }
});

test('well known departments map to the right names', function (string $code, string $name) {
    $byCode = collect(PlateCatalogue::all())->keyBy('code');

    expect($byCode[$code]['name'])->toBe($name);
})->with([
    ['75', 'Paris'],
    ['33', 'Gironde'],
    ['13', 'Bouches-du-Rhône'],
    ['2A', 'Corse-du-Sud'],
    ['90', 'Territoire de Belfort'],
    ['974', 'La Réunion'],
    ['988', 'Nouvelle-Calédonie'],
]);
