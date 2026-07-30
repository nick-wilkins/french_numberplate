<?php

use App\Models\Message;
use App\Models\Sighting;

function splodge(array $overrides = []): array
{
    return array_merge([
        'uid' => (string) Str::uuid(),
        'code' => '33',
        'car' => 1,
        'variant' => 2,
        'x' => 50,
        'y' => 50,
        'rotation' => -20,
        'scale' => 100,
    ], $overrides);
}

test('the state endpoint starts empty', function () {
    $this->getJson('/api/state')
        ->assertOk()
        ->assertJson([
            'revision' => '0:0',
            'sightings' => [],
            'messages' => [],
            'messages_cursor' => 0,
        ]);
});

test('a splodge can be recorded and comes back in the state', function () {
    $splodge = splodge(['code' => '2A', 'car' => 2]);

    $this->postJson('/api/sync', ['add' => [$splodge]])
        ->assertOk()
        ->assertJsonPath('sightings.0.code', '2A')
        ->assertJsonPath('sightings.0.car', 2)
        ->assertJsonPath('revision', '1:1');

    expect(Sighting::query()->count())->toBe(1);
});

test('replaying the same batch never duplicates rows', function () {
    $splodge = splodge();
    $message = ['uid' => (string) Str::uuid(), 'car' => 1, 'body' => 'hello'];

    foreach (range(1, 4) as $ignored) {
        $this->postJson('/api/sync', ['add' => [$splodge], 'messages' => [$message]])->assertOk();
    }

    expect(Sighting::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(1);
});

test('an offline queue of many splodges lands in one request', function () {
    $batch = [
        splodge(['code' => '01']),
        splodge(['code' => '02']),
        splodge(['code' => '988', 'car' => 2]),
    ];

    $this->postJson('/api/sync', ['add' => $batch])
        ->assertOk()
        ->assertJsonCount(3, 'sightings');

    expect(Sighting::query()->count())->toBe(3);
});

test('a splodge can be undone by uid', function () {
    $splodge = splodge();

    $this->postJson('/api/sync', ['add' => [$splodge]])->assertOk();
    $this->postJson('/api/sync', ['remove' => [$splodge['uid']]])
        ->assertOk()
        ->assertJsonCount(0, 'sightings');

    expect(Sighting::query()->count())->toBe(0);
});

test('a poll with nothing new is answered with a tiny unchanged response', function () {
    $this->postJson('/api/sync', ['add' => [splodge()]])->assertOk();

    $this->postJson('/api/sync', ['revision' => '1:1', 'messages_since' => 0])
        ->assertOk()
        ->assertExactJsonStructure(['unchanged', 'server_time'])
        ->assertJsonPath('unchanged', true);
});

test('a poll with a stale revision receives the full board', function () {
    $this->postJson('/api/sync', ['add' => [splodge()]])->assertOk();

    $this->postJson('/api/sync', ['revision' => '0:0'])
        ->assertOk()
        ->assertJsonCount(1, 'sightings');
});

test('chat messages arrive incrementally by cursor', function () {
    $first = ['uid' => (string) Str::uuid(), 'car' => 1, 'body' => 'first'];
    $second = ['uid' => (string) Str::uuid(), 'car' => 2, 'body' => 'second'];

    $this->postJson('/api/sync', ['messages' => [$first]])->assertOk();

    $response = $this->postJson('/api/sync', ['messages' => [$second], 'messages_since' => 1])
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'second');

    expect($response->json('messages_cursor'))->toBe(2);
});

test('message bodies are trimmed and blank ones are dropped without failing the sync', function () {
    $this->postJson('/api/sync', [
        'messages' => [
            ['uid' => (string) Str::uuid(), 'car' => 1, 'body' => '   spotted a 33!   '],
            ['uid' => (string) Str::uuid(), 'car' => 2, 'body' => '     '],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'spotted a 33!');

    expect(Message::query()->count())->toBe(1);
});

test('codes that are not real french departments are rejected', function (string $code) {
    $this->postJson('/api/sync', ['add' => [splodge(['code' => $code])]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('add.0.code');

    expect(Sighting::query()->count())->toBe(0);
})->with([['20'], ['96'], ['00'], ['999'], ['XX']]);

test('only the two cars in the game can paint', function (mixed $car) {
    $this->postJson('/api/sync', ['add' => [splodge(['car' => $car])]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('add.0.car');
})->with([[0], [3], [99]]);

test('splodge placement is bounded so paint cannot escape its plate', function (array $overrides, string $field) {
    $this->postJson('/api/sync', ['add' => [splodge($overrides)]])
        ->assertStatus(422)
        ->assertJsonValidationErrors("add.0.{$field}");
})->with([
    [['x' => 101], 'x'],
    [['y' => -1], 'y'],
    [['rotation' => 400], 'rotation'],
    [['scale' => 500], 'scale'],
    [['variant' => 9], 'variant'],
]);

test('a malformed uid is rejected so sync stays idempotent', function () {
    $this->postJson('/api/sync', ['add' => [splodge(['uid' => 'not-a-uuid'])]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('add.0.uid');
});

test('an over long chat message is rejected', function () {
    $this->postJson('/api/sync', [
        'messages' => [['uid' => (string) Str::uuid(), 'car' => 1, 'body' => str_repeat('a', 241)]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('messages.0.body');
});

test('an empty sync payload is accepted', function () {
    $this->postJson('/api/sync', [])->assertOk();
});

test('sync errors render as json even without an accept header', function () {
    $this->post('/api/sync', ['add' => [splodge(['code' => 'nope'])]])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/json');
});
