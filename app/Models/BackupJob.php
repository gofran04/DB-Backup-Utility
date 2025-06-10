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
        'backup_path',
        'status',
        'started_at',
        'completed_at',
        'file_size',
        'error_message'
    ];
}
