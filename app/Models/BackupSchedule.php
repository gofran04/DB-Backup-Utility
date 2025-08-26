<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BackupSchedule extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'db_connection_id',
        'profile_name',
        'frequency',
        'cron_expression',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function dbConnection()
    {
        return $this->belongsTo(DatabaseConnection::class);
    }
}
