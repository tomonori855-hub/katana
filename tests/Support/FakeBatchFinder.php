<?php

namespace Kura\Tests\Support;

use Kura\Http\Batch\BatchFinderInterface;
use Kura\Http\Batch\BatchSummary;

final class FakeBatchFinder implements BatchFinderInterface
{
    /** @var array<string, BatchSummary> */
    private array $batches = [];

    public function add(BatchSummary $summary): void
    {
        $this->batches[$summary->id] = $summary;
    }

    public function find(string $batchId): ?BatchSummary
    {
        return $this->batches[$batchId] ?? null;
    }
}
