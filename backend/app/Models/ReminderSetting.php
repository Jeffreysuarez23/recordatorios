<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ReminderSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['reminder_id', 'anticipacion_minutos', 'canal'];

    public function reminder()
    {
        return $this->belongsTo(Reminder::class);
    }
}
