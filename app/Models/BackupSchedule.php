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
        'frequency',
        'cron_expression',
        'enabled',
    ];

    public function dbConnection()
    {
        return $this->belongsTo(DatabaseConnection::class);
    }
}
