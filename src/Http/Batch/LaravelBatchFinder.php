<?php

namespace Kura\Http\Batch;

use Illuminate\Support\Facades\Bus;

final class LaravelBatchFinder implements BatchFinderInterface
{
    public function find(string $batchId): ?BatchSummary
    {
        $batch = Bus::findBatch($batchId);

        if ($batch === null) {
            return null;
        }

        return new BatchSummary(
            id: $batch->id,
            totalJobs: $batch->totalJobs,
            pendingJobs: $batch->pendingJobs,
            failedJobs: $batch->failedJobs,
            finished: $batch->finished(),
            cancelled: $batch->cancelled(),
        );
    }
}
