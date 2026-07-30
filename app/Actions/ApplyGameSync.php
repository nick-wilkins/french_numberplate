<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\Sighting;
use Illuminate\Support\Facades\DB;

/**
 * Writes a batch of queued client changes.
 *
 * Every row is keyed by a client-generated uid so a device that has been offline
 * can replay its whole outbox without creating duplicates.
 */
class ApplyGameSync
{
    /**
     * @param  list<array{uid: string, code: string, car: int, variant: int, x: int, y: int, rotation: int, scale: int}>  $addedSightings
     * @param  list<string>  $removedSightingUids
     * @param  list<array{uid: string, car: int, body: string}>  $postedMessages
     */
    public function handle(array $addedSightings, array $removedSightingUids, array $postedMessages): bool
    {
        if ($addedSightings === [] && $removedSightingUids === [] && $postedMessages === []) {
            return false;
        }

        DB::transaction(function () use ($addedSightings, $removedSightingUids, $postedMessages): void {
            if ($addedSightings !== []) {
                Sighting::upsert(
                    $addedSightings,
                    ['uid'],
                    ['code', 'car', 'variant', 'x', 'y', 'rotation', 'scale'],
                );
            }

            if ($removedSightingUids !== []) {
                Sighting::query()->whereIn('uid', $removedSightingUids)->delete();
            }

            if ($postedMessages !== []) {
                Message::upsert($postedMessages, ['uid'], ['body']);
            }
        });

        return true;
    }
}
