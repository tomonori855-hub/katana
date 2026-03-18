<?php

namespace Kura\Exceptions;

final class RecordsNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No records found.');
    }
}
