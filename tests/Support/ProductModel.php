<?php

namespace Katana\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    protected $table = 'products';

    public $timestamps = false;
}
