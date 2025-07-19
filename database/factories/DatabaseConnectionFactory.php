<?php

namespace Database\Factories;
use Illuminate\Support\Facades\Crypt;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DatabaseConnection>
 */
class DatabaseConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainPassword = 'newuserpass';

        return [
             'connection_name' => fake()->name(),
            'type'            => 'mysql',
            'host'            => '127.0.0.1',
            'port'            => 3306,
            'db_name'         => 'TechFlex',
            'username'        => 'newuser',
            'password'        => Crypt::encryptString($plainPassword),
        ];
    }
}
