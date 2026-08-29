<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Reminder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'nombre' => 'María Gómez',
            'email' => 'maria@ejemplo.com',
            'password_hash' => Hash::make('password123'),
            'telefono' => '123456789'
        ]);

        $categories = [
            ['nombre' => 'Médico', 'color' => '#00E676', 'icono' => 'medico', 'es_predeterminada' => true],
            ['nombre' => 'Personal', 'color' => '#D500F9', 'icono' => 'personal', 'es_predeterminada' => true],
            ['nombre' => 'Trabajo', 'color' => '#FF9100', 'icono' => 'trabajo', 'es_predeterminada' => true],
            ['nombre' => 'Membresías', 'color' => '#2979FF', 'icono' => 'document', 'es_predeterminada' => true],
            ['nombre' => 'Pagos', 'color' => '#FF1744', 'icono' => 'pagos', 'es_predeterminada' => true],
            ['nombre' => 'Estudio', 'color' => '#AEEA00', 'icono' => 'estudio', 'es_predeterminada' => true],
            ['nombre' => 'Otro', 'color' => '#F50057', 'icono' => 'otro', 'es_predeterminada' => true]
        ];

        $createdCategories = [];
        foreach ($categories as $cat) {
            $cat['user_id'] = null; // Predeterminadas
            $createdCategories[$cat['nombre']] = Category::create($cat);
        }

        // Crear algunos recordatorios para este mes (Marzo 2026, u hoy)
        $now = Carbon::now();

        $remindersData = [
            [
                'category_id' => $createdCategories['Médico']->id,
                'titulo' => 'Control con cardiología',
                'descripcion' => 'Llevar exámenes de sangre recientes y lista de medicamentos actuales.',
                'fecha' => $now->copy()->addDays(2)->format('Y-m-d'),
                'hora' => '09:30',
                'lugar' => 'Clínica del Country — Consultorio 402',
                'es_recurrente' => false,
                'anticipacion_minutos' => 1440, // 1 día
            ],
            [
                'category_id' => $createdCategories['Pagos']->id,
                'titulo' => 'Pago de arriendo',
                'fecha' => $now->copy()->addDays(3)->format('Y-m-d'),
                'es_recurrente' => true,
                'frecuencia_recurrencia' => 'mensual',
            ],
            [
                'category_id' => $createdCategories['Trabajo']->id,
                'titulo' => 'Entrega informe mensual',
                'fecha' => $now->copy()->addDays(5)->format('Y-m-d'),
                'hora' => '14:00',
                'lugar' => 'Oficina',
                'es_recurrente' => false,
            ],
        ];

        foreach ($remindersData as $data) {
            $anticipacion = $data['anticipacion_minutos'] ?? null;
            unset($data['anticipacion_minutos']);
            
            $data['user_id'] = $user->id;
            $reminder = Reminder::create($data);

            if ($anticipacion) {
                $reminder->settings()->create([
                    'anticipacion_minutos' => $anticipacion,
                    'canal' => 'email'
                ]);
            }
        }
    }
}
