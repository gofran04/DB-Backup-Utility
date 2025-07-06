<?php

namespace App\Factories;

use App\Models\DatabaseConnection;
use App\Services\Contracts\DatabaseAdapterInterface;
use App\Services\Adapters\MySQLDatabaseAdapter;
use InvalidArgumentException;

class DatabaseAdapterFactory
{
    public function make(DatabaseConnection $connection): DatabaseAdapterInterface
    {
        return match (strtolower($connection->type)) {
            'mysql' => new MySQLDatabaseAdapter($connection),
            default => throw new InvalidArgumentException("Unsupported database type: {$connection->type}")
        };
    }
}
