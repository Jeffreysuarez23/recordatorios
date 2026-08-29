<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $query = Reminder::with('category')->where('user_id', Auth::id());

        if ($request->has('mes') && $request->has('anio')) {
            $query->whereMonth('fecha', $request->mes)->whereYear('fecha', $request->anio);
        }
        if ($request->has('categoria')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->categoria . '%');
            });
        }
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->has('prioridad')) {
            $query->where('prioridad', $request->prioridad);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('fecha')->orderBy('hora')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date|after_or_equal:today',
            'hora' => 'nullable|date_format:H:i',
            'lugar' => 'nullable|string|max:255',
            'es_recurrente' => 'boolean',
            'frecuencia_recurrencia' => 'required_if:es_recurrente,true|in:diaria,semanal,mensual,anual',
            'prioridad' => 'in:baja,media,alta',
            'anticipacion_minutos' => 'nullable|integer'
        ]);

        DB::beginTransaction();
        try {
            $reminder = Reminder::create(array_merge($validated, [
                'user_id' => Auth::id(),
                'estado' => 'pendiente',
                'recordatorio_enviado' => false
            ]));

            if (isset($validated['anticipacion_minutos'])) {
                $reminder->settings()->create([
                    'anticipacion_minutos' => $validated['anticipacion_minutos'],
                    'canal' => 'email'
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio creado',
                'data' => $reminder->load('category', 'settings')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear recordatorio: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $reminder = Reminder::with('category', 'settings')->where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        return response()->json([
            'success' => true,
            'data' => $reminder
        ]);
    }

    public function update(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        
        $validated = $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'sometimes|required|date',
            'hora' => 'nullable|date_format:H:i',
            'lugar' => 'nullable|string|max:255',
            'es_recurrente' => 'boolean',
            'frecuencia_recurrencia' => 'required_if:es_recurrente,true|in:diaria,semanal,mensual,anual',
            'prioridad' => 'in:baja,media,alta',
        ]);

        $reminder->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Recordatorio actualizado',
            'data' => $reminder->load('category')
        ]);
    }

    public function destroy($id)
    {
        $reminder = Reminder::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $reminder->delete();
        return response()->json([
            'success' => true,
            'message' => 'Recordatorio eliminado'
        ]);
    }

    public function updateEstado(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $validated = $request->validate([
            'estado' => 'required|in:pendiente,completado,cancelado'
        ]);
        
        $reminder->update(['estado' => $validated['estado']]);

        // Manejar recurrencia
        if ($validated['estado'] === 'completado' && $reminder->es_recurrente) {
            $nextDate = Carbon::parse($reminder->fecha);
            switch ($reminder->frecuencia_recurrencia) {
                case 'diaria': $nextDate->addDay(); break;
                case 'semanal': $nextDate->addWeek(); break;
                case 'mensual': $nextDate->addMonth(); break;
                case 'anual': $nextDate->addYear(); break;
            }
            
            $newReminder = $reminder->replicate();
            $newReminder->fecha = $nextDate->format('Y-m-d');
            $newReminder->estado = 'pendiente';
            $newReminder->recordatorio_enviado = false;
            $newReminder->save();
            
            // Replicar configuraciones
            foreach ($reminder->settings as $setting) {
                $newReminder->settings()->create([
                    'anticipacion_minutos' => $setting->anticipacion_minutos,
                    'canal' => $setting->canal
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado',
            'data' => $reminder
        ]);
    }

    public function calendario($anio, $mes)
    {
        $reminders = Reminder::with('category')
            ->where('user_id', Auth::id())
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->get();
            
        $grouped = $reminders->groupBy(function($item) {
            return Carbon::parse($item->fecha)->format('j');
        });

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    public function proximos(Request $request)
    {
        $limit = $request->get('limit', 10);
        $reminders = Reminder::with('category')
            ->where('user_id', Auth::id())
            ->where('estado', 'pendiente')
            ->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')->orderBy('hora')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reminders
        ]);
    }

    public function hoy()
    {
        $reminders = Reminder::with('category')
            ->where('user_id', Auth::id())
            ->where('estado', 'pendiente')
            ->where('fecha', now()->toDateString())
            ->orderBy('hora')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reminders
        ]);
    }

    public function vencidos()
    {
        $reminders = Reminder::with('category')
            ->where('user_id', Auth::id())
            ->where('estado', 'pendiente')
            ->where('fecha', '<', now()->toDateString())
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reminders
        ]);
    }
}
