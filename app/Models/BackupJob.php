<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BackupJob extends Model
{
    /** @use HasFactory<\Database\Factories\BackupJobFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'database_connection_id',
        'profile_name',
        'backup_path',
        'status',
        'mechanism',
        'started_at',
        'completed_at',
        'file_size',
        'error_message'
    ];

    public function databaseConnection()
    {
        return $this->belongsTo(DatabaseConnection::class);
    }
}
