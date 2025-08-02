<?php

namespace Tests\Feature\APITests;

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
        $response = $this->getJson('api/database-connections');

        $response->assertOk();
        $this->assertDatabaseCount('database_connections', 10);
    }

    public function test_return_specific_db_connection()
    {
        $db_connection = DatabaseConnection::factory()->create();
        $response = $this->getJson('api/database-connections/'.$db_connection->id);

        $response->assertOk();
        $this->assertDatabaseCount('database_connections', 1);
    }

    public function test_store_new_db_connection()
    {
        $data = [
            'connection_name' => 'fake name for testing',
            'type'            => 'mysql',
            'host'            => '127.0.0.1',
            'port'            => 3306,
            'db_name'         => 'TechFlex',
            'username'        => 'newuser',
            'password'        => 'newuserpass',
        ];

        $response = $this->postJson('api/database-connections/',$data);

        $response->assertStatus(201);
        $this->assertDatabaseCount('database_connections', 1);
        $this->assertDatabaseHas('database_connections', [
            'connection_name' => 'fake name for testing',
        ]);
        $response->assertJsonFragment([
            'connection_name' => 'fake name for testing',
        ]);
    }

    public function test_update_specific_db_connection()
    {
        $db_connection = DatabaseConnection::factory()->create(['connection_name' => 'fake name']);
        $data = [
            'connection_name' => 'updated name',
            'type'            => $db_connection->type,
            'host'            => $db_connection->host,
            'port'            => $db_connection->port,
            'db_name'         => $db_connection->db_name,
            'username'        => $db_connection->username,
            'password'        => $db_connection->password, 
        ];

        $response = $this->putJson('api/database-connections/'.$db_connection->id,$data);

        $response->assertStatus(202);
        $response->assertJsonFragment([
            'connection_name' => 'updated name',
        ]);

        $this->assertDatabaseHas('database_connections', [
            'connection_name' => 'updated name',
        ]);
    }

     public function test_delete_specific_db_connection()
    {
        $db_connection = DatabaseConnection::factory()->create(['connection_name' => 'fake name for testing']);
        $response = $this->deleteJson('api/database-connections/'.$db_connection->id);

        $response->assertStatus(204);
        $this->assertSoftDeleted($db_connection);
    }

    public function test_prevent_store_new_db_connection_with_invalid_inputs()
    {
        $data = [
            'connection_name' => 'fake name for testing',
            'type'            => 'wrong type',
            'host'            => '127.0.0.1',
            'port'            => 3306,
            'db_name'         => 'TechFlex',
            'username'        => 'newuser',
            'password'        => 'newuserpass',
        ];

        $response = $this->postJson('api/database-connections/',$data);

        $response->assertJsonValidationErrorFor('type');
    }

    public function test_prevent_store_new_db_connection_with_missing_inputs()
    {
        $data = [
            'port'            => 3306,
            'db_name'         => 'TechFlex',
            'username'        => 'newuser',
            'password'        => 'newuserpass',
        ];

        $response = $this->postJson('api/database-connections/',$data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['connection_name', 'type', 'host']);
    }
    
}