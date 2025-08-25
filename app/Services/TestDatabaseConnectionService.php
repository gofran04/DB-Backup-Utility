<?php
namespace App\Services;

use App\Exceptions\DatabaseConnectionException;
use App\Traits\ApiResponseTrait;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

class TestDatabaseConnectionService
{
    use ApiResponseTrait;

    public function testConnection($db_id) 
    {
        $this->createDynamicConnection($db_id);
    }

    public static function createDynamicConnection($db_id)
    {
        $databaseConnection = DatabaseConnection::findOrFail($db_id);

        $connectionName = 'temp_' . uniqid();

        DB::purge($connectionName);

        Config::set("database.connections.{$connectionName}", [
            'driver'    => 'mysql',
            'host'      => $databaseConnection->host,
            'port'      => $databaseConnection->port,
            'database'  => $databaseConnection->db_name,
            'username'  => $databaseConnection->username,
            'password'  => Crypt::decryptString($databaseConnection['password']),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ]);

        try {
            DB::reconnect($connectionName);
            DB::connection($connectionName)->getPdo();

            return [
                'status'         => true,
                'connectionName' => $connectionName,
            ];
        } catch (\PDOException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Access denied')) {
                throw new DatabaseConnectionException('Invalid database credentials.', 'invalid_credentials');
            }
            if (str_contains($message, 'Unknown database')) {
                throw new DatabaseConnectionException('Database does not exist.', 'database_missing');
            }
            if (str_contains($message, 'Connection refused') || str_contains($message, 'php_network_getaddresses')) {
                throw new DatabaseConnectionException('Database host unreachable.', 'host_unreachable');
            }
            if (str_contains($message, 'timed out')) {
                throw new DatabaseConnectionException('Connection timed out.', 'connection_timeout');
            }
            throw new DatabaseConnectionException('Unknown database connection error.', 'unknown');
        }finally { // clean up DB connection 
            DB::disconnect($connectionName);
            DB::purge($connectionName);
        }
    }

    public static function testProfileConnection(array $profile)
    {
        $connectionName = 'profile_' . uniqid();

        DB::purge($connectionName);

        $driver = match ($profile['driver'] ?? 'mysql') {
            'postgres', 'postgresql' => 'pgsql',
            default => $profile['driver'],
        };

        $config = [
            'driver'   => $driver,
            'host'     => $profile['host'],
            'port'     => $profile['port'],
            'database' => $profile['database'],
            'username' => $profile['username'],
            'password' => Crypt::decryptString($profile['password']),
            'prefix'   => '',
        ];

        if ($driver === 'mysql') {
            $config += [
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict'    => true,
                'engine'    => null,
            ];
        } elseif ($driver === 'pgsql')  {
            $config += [
                'charset' => 'utf8',
                'schema'  => 'public',
                'sslmode' => 'prefer',
            ];
        }

        Config::set("database.connections.{$connectionName}", $config);

        try {
            DB::reconnect($connectionName);
            DB::connection($connectionName)->getPdo();

            return [
                'status' => true,
                'connectionName' => $connectionName,
            ];
        } catch (\PDOException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'password authentication failed') || str_contains($message, 'Access denied')) {
                throw new DatabaseConnectionException('Invalid database credentials.', 'invalid_credentials');
            }
            if (str_contains($message, 'does not exist')) {
                throw new DatabaseConnectionException('Database does not exist.', 'database_missing');
            }
            if (str_contains($message, 'Connection refused') || str_contains($message, 'php_network_getaddresses')) {
                throw new DatabaseConnectionException('Database host unreachable.', 'host_unreachable');
            }
            if (str_contains($message, 'timed out')) {
                throw new DatabaseConnectionException('Connection timed out.', 'connection_timeout');
            }

            throw new DatabaseConnectionException('Unknown database connection error.', 'unknown');
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
        }
    }

}
