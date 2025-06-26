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
}
