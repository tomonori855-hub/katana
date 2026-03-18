<?php

namespace Kura\Http\Batch;

final readonly class BatchSummary
{
    public function __construct(
        public string $id,
        public int $totalJobs,
        public int $pendingJobs,
        public int $failedJobs,
        public bool $finished,
        public bool $cancelled,
    ) {}
}
