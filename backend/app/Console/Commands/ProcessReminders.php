<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReminderSetting;
use App\Models\Notification;
use App\Mail\ReminderNotificationMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ProcessReminders extends Command
{
    protected $signature = 'reminders:process';
    protected $description = 'Procesa los recordatorios pendientes y envía notificaciones según anticipación';

    public function handle()
    {
        $now = Carbon::now();
        
        // Find all settings where the related reminder is pending and not yet sent
        $settings = ReminderSetting::with('reminder.user')
            ->whereHas('reminder', function($query) {
                $query->where('estado', 'pendiente')
                      ->where('recordatorio_enviado', false);
            })->get();

        foreach ($settings as $setting) {
            $reminder = $setting->reminder;
            $reminderDateTimeStr = $reminder->fecha . ' ' . ($reminder->hora ?? '00:00:00');
            $reminderDate = Carbon::parse($reminderDateTimeStr);
            
            $sendAt = $reminderDate->copy()->subMinutes($setting->anticipacion_minutos);

            if ($now->greaterThanOrEqualTo($sendAt)) {
                // Crear notificación
                Notification::create([
                    'reminder_id' => $reminder->id,
                    'canal' => $setting->canal,
                    'fecha_envio' => $now,
                    'estado' => 'enviado'
                ]);

                if ($setting->canal === 'email' && $reminder->user->email) {
                    try {
                        Mail::to($reminder->user->email)->send(new ReminderNotificationMail($reminder));
                    } catch (\Exception $e) {
                        $this->error('Error enviando correo: ' . $e->getMessage());
                    }
                }

                $reminder->update(['recordatorio_enviado' => true]);
                $this->info("Notificación enviada para el recordatorio: {$reminder->id}");
            }
        }
    }
}
