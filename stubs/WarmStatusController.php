<?php

namespace App\Http\Controllers\Kura;

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
     * Customize this controller freely — add authorization,
     * transform the response format, add extra metadata, etc.
     *
     * Register your customized controller in config/kura.php:
     *   'warm' => ['status_controller' => \App\Http\Controllers\Kura\WarmStatusController::class]
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
