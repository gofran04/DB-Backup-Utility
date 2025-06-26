<?php
namespace App\Services;

use App\Exceptions\DatabaseConnectionException;
use App\Traits\ApiResponseTrait;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TestDatabaseConnectionService
{
    use ApiResponseTrait;

    public function testConnection($db_id) 
    {
        try{
            $this->createDynamicConnection($db_id);
            return $this->successResponse(null,'Database connected successfully',200);
        }catch(DatabaseConnectionException $e){
            return $this->errorResponse(
            'Database connection failed',
            [
                'type'    => $e->getType(),
                'details' => $e->getMessage()
            ], 422);
        }
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
            'password'  => $databaseConnection->password,
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

    public static function testProfileConnection(array $config)
    {
        $connectionName = 'profile_' . uniqid();

        \Illuminate\Support\Facades\DB::purge($connectionName);

        \Illuminate\Support\Facades\Config::set("database.connections.{$connectionName}", [
            'driver'    => $config['driver'] ?? 'mysql',
            'host'      => $config['host'],
            'port'      => $config['port'],
            'database'  => $config['database'],
            'username'  => $config['username'],
            'password'  => $config['password'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ]);

        try {
            \Illuminate\Support\Facades\DB::reconnect($connectionName);
            \Illuminate\Support\Facades\DB::connection($connectionName)->getPdo();

            return [
                'status' => true,
                'connectionName' => $connectionName,
            ];
        } catch (\PDOException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Access denied')) {
                throw new \App\Exceptions\DatabaseConnectionException('Invalid database credentials.', 'invalid_credentials');
            }
            if (str_contains($message, 'Unknown database')) {
                throw new \App\Exceptions\DatabaseConnectionException('Database does not exist.', 'database_missing');
            }
            if (str_contains($message, 'Connection refused') || str_contains($message, 'php_network_getaddresses')) {
                throw new \App\Exceptions\DatabaseConnectionException('Database host unreachable.', 'host_unreachable');
            }
            if (str_contains($message, 'timed out')) {
                throw new \App\Exceptions\DatabaseConnectionException('Connection timed out.', 'connection_timeout');
            }

            throw new \App\Exceptions\DatabaseConnectionException('Unknown database connection error.', 'unknown');
        } finally {
            \Illuminate\Support\Facades\DB::disconnect($connectionName);
            \Illuminate\Support\Facades\DB::purge($connectionName);
        }
    }
}
