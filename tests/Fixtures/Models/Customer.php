<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Models\Factories\CustomerFactory;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
