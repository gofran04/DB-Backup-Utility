<?php

namespace Tests\Feature\APITests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\File;
use App\Services\DatabaseBackupService;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Crypt;

class RestoreDBTest extends TestCase
{
    use DatabaseMigrations;

    protected string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test-specific backup path
        $this->storagePath = storage_path('app/test-backups');

        // Create the directory if it doesn't exist
        if (!File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0777, true, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up the test-backups folder
        if (File::exists($this->storagePath)) {
            File::cleanDirectory($this->storagePath);
        }

        parent::tearDown();
    }

    public function test_restore_db_by_passing_db_id()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $compressor = app(CompressionServiceInterface::class);
        $adapter = app(DatabaseAdapterFactory::class)->make($db_connection);

        $backupService = new DatabaseBackupService($adapter,$compressor);
        $backupJob = $backupService->backupUsingDbId($db_connection, $this->storagePath);

        $fullPath = storage_path('app/' . $backupJob['relative_path']);
        $data2 = [
            'db_id' => $db_connection->id,
            'file'  => $backupJob['relative_path']
        ];

        $response = $this->postJson('api/restore',$data2);
        $response->assertOk();
        $response->assertJson([
            'message' => 'Restore process started in background.',
        ]);
        $this->assertNotNull($fullPath, 'Backup path should not be null');
        $this->assertTrue(file_exists($fullPath), "Backup file does not exist: $fullPath");
    }

    public function test_restore_db_by_profile()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $compressor = app(CompressionServiceInterface::class);
        $adapter = app(DatabaseAdapterFactory::class)->make($db_connection);

        $backupService = new DatabaseBackupService($adapter,$compressor);
        $backupJob = $backupService->backupUsingDbId($db_connection, $this->storagePath);

        $fullPath = storage_path('app/' . $backupJob['relative_path']);

        // add new profile to config.json for testing
        $configService = app(ConfigService::class);
        $profiles = $configService->loadProfiles();

        $profiles['prof_temp'] = [
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => 'testrestore',
            'username' => 'newuser',
            'password' => Crypt::encryptString(env('DB_PASSWORD')), 
        ];

        $configService->saveProfiles($profiles);

        $data2 = [
            'db_profile' => 'prof_temp', 
            'file'       => $backupJob['relative_path']
        ];

        $response = $this->postJson('api/restore',$data2);

        //cleanup config.json by removing profile:prof_temp
        unset($profiles['prof_temp']);
        $configService->saveProfiles($profiles);
        
        $response->assertOk();
        $response->assertJson([
            'message' => 'Restore process started in background.',
        ]);
        $this->assertNotNull($fullPath, 'Backup path should not be null');
        $this->assertTrue(file_exists($fullPath), "Backup file does not exist: $fullPath");
    }

    public function test_restore_fails_when_both_id_and_profile_provided()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $data2 = [
            'db_profile' => 'some_profile', 
            'db_id'      => $db_connection->id,
            'file'       => 'file.sql'
        ];

        $response = $this->postJson('api/restore',$data2);

        $response->assertStatus(422); // Laravel returns 422 on validation failure
        $response->assertJsonValidationErrors(['db_id','db_profile']);
        $response->assertJsonFragment([
            'db_id'      => ['Provide either db_profile or db_id, not both.'],
            'db_profile' => ['Provide either db_profile or db_id, not both.']
        ]);
    }

    public function test_restore_fails_when_neither_id_or_profile_provided()
    {
        $data = [
            'file'       => 'file.sql'
        ];

        $response = $this->postJson('api/restore',$data);

        $response->assertStatus(422); // Laravel returns 422 on validation failure
        $response->assertJsonValidationErrors(['id_profile']);
        $response->assertJsonFragment([
            'id_profile' => ['Either db_profile or db_id is required.']
        ]);
    }

    public function test_restore_fails_when_dump_file_not_exist()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $data = [
            'db_id' => $db_connection->id,
            'file'  => 'not_found_file.sql'
        ];

        $response = $this->postJson('api/restore',$data);

        $this->assertFalse(file_exists($data['file']));
        $response->assertJsonFragment([
            'errors' => [
                'message' => "Backup file not found: " .  storage_path("app/{$data['file']}"),
            ],
        ]);
    }

    public function test_restore_fails_if_file_not_provided()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $data = [
            'db_id' => $db_connection->id,
        ];

        $response = $this->postJson('api/restore',$data);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The file field is required.',
        ]);
    }

    public function test_restore_fails_with_invalid_db_id()
    {
        $data = [
            'db_id' => 99999, // invalid db_id
            'file'  => 'file.sql'
        ];

        $response = $this->postJson('api/restore', $data);
        
        $response->assertStatus(422); // or whatever your handler returns
        $response->assertJsonFragment([
            'message' => 'The selected db id is invalid.',
        ]);
    }

    public function test_restore_fails_with_invalid_profile()
    {
        $data = [
            'db_profile' => 'nonexistent_profile',
            'file'       => 'file.sql'
        ];

        $response = $this->postJson('api/restore', $data);
        
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'errors' => [
                'message' => 'Profile: '. $data['db_profile']. ' does not exist in config.json',
            ]
        ]);
    }
}