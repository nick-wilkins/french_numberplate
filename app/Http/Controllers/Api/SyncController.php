<?php

namespace App\Http\Controllers\Api;

use App\Actions\ApplyGameSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StateRequest;
use App\Http\Requests\Api\SyncRequest;
use App\Support\GameSnapshot;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(
        private readonly GameSnapshot $snapshot,
        private readonly ApplyGameSync $applySync,
    ) {}

    public function show(StateRequest $request): JsonResponse
    {
        return response()->json($this->snapshot->build($request->messagesSince()));
    }

    public function store(SyncRequest $request): JsonResponse
    {
        $wrote = $this->applySync->handle(
            $request->addedSightings(),
            $request->removedSightingUids(),
            $request->postedMessages(),
        );

        $state = $this->snapshot->build($request->messagesSince());

        if (! $wrote && $state['messages'] === [] && $state['revision'] === $request->clientRevision()) {
            return response()->json([
                'unchanged' => true,
                'server_time' => $state['server_time'],
            ]);
        }

        return response()->json($state);
    }
}
