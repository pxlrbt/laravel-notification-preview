<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately has no factory, so the resolver has to fall back to the database.
 */
class Ticket extends Model
{
    protected $guarded = [];
}
