<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Models\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Models\Customer;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => 'Factory Customer'];
    }
}
