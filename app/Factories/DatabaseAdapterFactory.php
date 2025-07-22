<?php

namespace App\Factories;

use App\Models\DatabaseConnection;
use App\Services\Contracts\DatabaseAdapterInterface;
use App\Services\Adapters\MySQLDatabaseAdapter;
use App\Services\Adapters\PostgreSQLDatabaseAdapter;
use InvalidArgumentException;

class DatabaseAdapterFactory
{
    public function make(DatabaseConnection $connection): DatabaseAdapterInterface
    {
        return match (strtolower($connection->type)) {
            'mysql' => new MySQLDatabaseAdapter($connection),
            'pgsql', 'postgres', 'postgresql' => new PostgreSQLDatabaseAdapter($connection),
            default => throw new InvalidArgumentException("Unsupported database type: {$connection->type}")
        };
    }

    public function makeFromProfile(array $profile): DatabaseAdapterInterface
    {
        return match (strtolower($profile['driver'])) {
            'mysql' => new MySQLDatabaseAdapter($profile),
            'pgsql', 'postgres', 'postgresql' => new PostgreSQLDatabaseAdapter($profile),
            default => throw new \InvalidArgumentException("Unsupported database type: {$profile['type']}")
        };
    }
}
