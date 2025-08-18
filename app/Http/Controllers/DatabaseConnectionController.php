<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrUpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\DatabaseConnectionResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Crypt;

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
        $inputs = $request->validated();
        $inputs['password'] = Crypt::encryptString($inputs['password']);

        $db_connection = DatabaseConnection::create($inputs);

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
        $inputs = $request->validated();
        $inputs['password'] = Crypt::encryptString($inputs['password']);

        $databaseConnection->update($inputs);

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
