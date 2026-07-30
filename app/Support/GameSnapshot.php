<?php

namespace App\Support;

use App\Models\Message;
use App\Models\Sighting;

/**
 * Builds the authoritative game state that every device syncs against.
 *
 * Splodges are always sent in full — the whole board is only ever a few hundred
 * rows, and a full snapshot means clients never have to reconcile deletions.
 * Chat is incremental because it only ever grows.
 */
class GameSnapshot
{
    private const MESSAGE_PAGE_SIZE = 200;

    /**
     * @return array{
     *     revision: string,
     *     sightings: list<array{uid: string, code: string, car: int, variant: int, x: int, y: int, rotation: int, scale: int}>,
     *     messages: list<array{uid: string, car: int, body: string, at: string}>,
     *     messages_cursor: int,
     *     server_time: string
     * }
     */
    public function build(int $messagesSince): array
    {
        $sightings = Sighting::query()
            ->orderBy('id')
            ->get(['id', 'uid', 'code', 'car', 'variant', 'x', 'y', 'rotation', 'scale']);

        $messages = Message::query()
            ->where('id', '>', $messagesSince)
            ->orderBy('id')
            ->limit(self::MESSAGE_PAGE_SIZE)
            ->get(['id', 'uid', 'car', 'body', 'created_at']);

        $sightingIds = $sightings->pluck('id')->all();
        $messageIds = $messages->pluck('id')->all();

        return [
            'revision' => $sightings->count().':'.($sightingIds === [] ? 0 : (int) end($sightingIds)),
            'sightings' => array_values($sightings
                ->map(fn (Sighting $sighting): array => [
                    'uid' => (string) $sighting->uid,
                    'code' => (string) $sighting->code,
                    'car' => (int) $sighting->car,
                    'variant' => (int) $sighting->variant,
                    'x' => (int) $sighting->x,
                    'y' => (int) $sighting->y,
                    'rotation' => (int) $sighting->rotation,
                    'scale' => (int) $sighting->scale,
                ])
                ->all()),
            'messages' => array_values($messages
                ->map(fn (Message $message): array => [
                    'uid' => (string) $message->uid,
                    'car' => (int) $message->car,
                    'body' => (string) $message->body,
                    'at' => $message->created_at?->toIso8601String() ?? '',
                ])
                ->all()),
            'messages_cursor' => $messageIds === [] ? $messagesSince : (int) end($messageIds),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
