<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\DatabaseConnection;

class DBConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_all_db_connections()
    {
        DatabaseConnection::factory()->count(10)->create();
        $response = $this->get('api/database-connections');

        $response->assertOk();
        $this->assertDatabaseCount('database_connections', 10);
    }


}