<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrUpdateDatabaseConnectionRequest;
use App\Http\Requests\UpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\DatabaseConnectionResource;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseConnectionController extends Controller
{
    public function index()
    {
        $db_connections = DatabaseConnection::all(); // You can use pagination if needed

        return DatabaseConnectionResource::collection($db_connections);
    }

    public function store(CreateOrUpdateDatabaseConnectionRequest $request)
    {
        $db_connection = DatabaseConnection::create($request->validated());

        return (new DatabaseConnectionResource($db_connection))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(DatabaseConnection $databaseConnection)
    {
        return new DatabaseConnectionResource($databaseConnection);
    }

    public function update(CreateOrUpdateDatabaseConnectionRequest $request, DatabaseConnection $databaseConnection)
    {
        $databaseConnection->update($request->validated());

        return (new DatabaseConnectionResource($databaseConnection->refresh()))
                ->response()
                ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(DatabaseConnection $databaseConnection)
    {
        $databaseConnection->delete();
        return response('The DB Connection has been deleted');
    }

    public function testConnection($db_id) 
    {
        $databaseConnection = DatabaseConnection::findOrFail($db_id);
    
        $connectionName = 'temp_' . uniqid();
        DB::purge($connectionName); // Ensure it's clean

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

        //  Test it
        try {
            DB::connection($connectionName)->getPdo();
            return response()->json([
                    'message' => 'Connected to database',
                ], 200 );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to connect to database',
            ], 422);
        } finally { // clean up DB connection 
            DB::disconnect($connectionName);
            DB::purge($connectionName);
        }
    }
}
