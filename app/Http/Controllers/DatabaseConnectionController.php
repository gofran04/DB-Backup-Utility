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

    public function testConnection() 
    {
        $connection = DatabaseConnection::find(1);

        $connectionName = 'temp_' . uniqid();
        DB::purge($connectionName); // Ensure it's clean

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->db_name,
            'username' => $connection->username,
            'password' => $connection->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
         ]);

        DB::reconnect($connectionName);

        //  Test it
        try {
            DB::connection($connectionName)->getPdo();
            echo " Connected to DB";

            $roles = DB::connection($connectionName)->table('roles')->limit(10)->get();

            foreach ($roles as $role) {
                echo $role->id . ' - ' . $role->name . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo " Connection failed: " . $e->getMessage();
        }

    }
}
