<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatabaseConnection extends Model
{
    /** @use HasFactory<\Database\Factories\DatabaseConnectionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'connection_name', 
        'type',
        'host',
        'port',
        'db_name',
        'username',
        'password',
    ];

     protected $hidden = [
        'password',
    ];

    public function backupJobs()
    {
        return $this->hasMany(BackupJob::class);
    }

    public function taskSchedules()
    {
        return $this->hasMany(BackupSchedule::class);
    }
}
