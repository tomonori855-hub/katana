<?php

namespace Kura\Exceptions;

final class MultipleRecordsFoundException extends \RuntimeException
{
    public function __construct(int $count)
    {
        parent::__construct("{$count} records were found.");
    }
}
