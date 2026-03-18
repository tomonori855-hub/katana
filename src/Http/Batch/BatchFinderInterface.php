<?php

namespace Kura\Http\Batch;

interface BatchFinderInterface
{
    public function find(string $batchId): ?BatchSummary;
}
