<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestDBConnectionRequest;
use App\Services\TestDatabaseConnectionService;
use App\Traits\ApiResponseTrait;
use App\Exceptions\DatabaseConnectionException;

class TestDBConnectionController extends Controller
{
    use ApiResponseTrait;

    function testConnection(TestDBConnectionRequest $request)
    {
        $db_id = $request->validated()['db_id'];
        $service = new TestDatabaseConnectionService();

        try{
            $service->testConnection($db_id);
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
}