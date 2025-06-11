<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrUpdateDatabaseConnectionRequest;
use App\Http\Requests\UpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\DatabaseConnectionResource;


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
        //
    }
}
