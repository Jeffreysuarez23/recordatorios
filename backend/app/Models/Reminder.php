<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Reminder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'category_id', 'titulo', 'descripcion', 
        'fecha', 'hora', 'lugar', 'es_recurrente', 
        'frecuencia_recurrencia', 'prioridad', 'estado', 'recordatorio_enviado'
    ];

    protected $casts = [
        'fecha' => 'date',
        'es_recurrente' => 'boolean',
        'recordatorio_enviado' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function settings()
    {
        return $this->hasMany(ReminderSetting::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
