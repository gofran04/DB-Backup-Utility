<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrUpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\DatabaseConnectionResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Exceptions\DatabaseConnectionException;
use App\Traits\ApiResponseTrait;

class DatabaseConnectionController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $db_connections = DatabaseConnection::all(); 

        return $this->successResponse(
            DatabaseConnectionResource::collection($db_connections),
            'Database connections retrieved',Response::HTTP_OK);
    }

    public function store(CreateOrUpdateDatabaseConnectionRequest $request)
    {
        $db_connection = DatabaseConnection::create($request->validated());

        return $this->successResponse(
            new DatabaseConnectionResource($db_connection),
            'Database connection created',201);
    }

    public function show(DatabaseConnection $databaseConnection)
    {
        return $this->successResponse(
            new DatabaseConnectionResource($databaseConnection),
            'Database connection retrieved.',Response::HTTP_OK);
    }

    public function update(CreateOrUpdateDatabaseConnectionRequest $request, DatabaseConnection $databaseConnection)
    {
        $databaseConnection->update($request->validated());

        return $this->successResponse(
            new DatabaseConnectionResource($databaseConnection->refresh()),
            'Database connection updated successfully',202);
    }

    public function destroy(DatabaseConnection $databaseConnection)
    {
        $databaseConnection->delete();
    
        return $this->successResponse(
            null,
            'Database connection deleted successfully.',204);
    }

    public function testConnection($db_id) 
    {
        try{
            $this->createDynamicConnection($db_id);
            return $this->successResponse(null,'Database connection successful',200);
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

}
