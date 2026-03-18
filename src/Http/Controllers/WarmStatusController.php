<?php

namespace Kura\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Kura\Http\Batch\BatchFinderInterface;

class WarmStatusController
{
    public function __construct(
        private readonly BatchFinderInterface $batchFinder,
    ) {}

    /**
     * Return the current status of a warm batch.
     *
     * GET /kura/warm/status/{batchId}
     *
     * Response:
     * {
     *   "batch_id": "550e8400-...",
     *   "total": 3,
     *   "pending": 1,
     *   "failed": 0,
     *   "finished": false,
     *   "cancelled": false
     * }
     */
    public function __invoke(string $batchId): JsonResponse
    {
        $batch = $this->batchFinder->find($batchId);

        if ($batch === null) {
            return new JsonResponse(['message' => 'Batch not found.'], 404);
        }

        return new JsonResponse([
            'batch_id' => $batch->id,
            'total' => $batch->totalJobs,
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'finished' => $batch->finished,
            'cancelled' => $batch->cancelled,
        ]);
    }
}
