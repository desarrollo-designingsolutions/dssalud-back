<?php

namespace App\Http\Controllers;

use App\Repositories\ReconciliationGroupRepository;
use App\Repositories\ReconciliationNotificationRepository;
use App\Repositories\ThirdRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReconciliationGroupWebController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ReconciliationGroupRepository $reconciliationGroupRepository,
        protected ReconciliationNotificationRepository $reconciliationNotificationRepository,
        protected ThirdRepository $thirdRepository,
    ) {}

    public function index(Request $request, $id)
    {
        $reconciliationGroup = $this->reconciliationGroupRepository->find($id, ['third', 'reconciliationNotification']);

        $third = $reconciliationGroup?->third;

        $invoices = $reconciliationGroup->invoices;

        $sum_value_glosa = $invoices->sum(function ($invoice) {
            return $invoice->sumValorGlosa();
        });

        $reconciliationNotification_status = $reconciliationGroup->reconciliationNotification ? 'true' : 'false';

        return view('ReconciliationGroup.index', [
            'reconciliation_notification' => $reconciliationNotification_status,
            'reconciliationGroup' => $reconciliationGroup,
            'reconciliation_group_id' => $reconciliationGroup->id,
            'third' => $third,
            'invoices_count' => $invoices->count(),
            'sum_value_glosa' => formatNumber($sum_value_glosa),
        ]);
    }

    public function saveNotification(Request $request)
    {
        try {

            $reconciliationGroup = $this->reconciliationGroupRepository->find(
                $request->input('reconciliation_group_id'),
                ['reconciliationNotification']
            );
            $reconciliationNotification_status = $reconciliationGroup->reconciliationNotification ? 'true' : 'false';

            // NEW: Normalización defensiva por si llegan como string (coma o ;)
            $payload = $request->all();

            if (isset($payload['emails']) && is_string($payload['emails'])) { 
                $payload['emails'] = preg_split('/[;,]/', $payload['emails']);
            }
            if (isset($payload['phones']) && is_string($payload['phones'])) { 
                $payload['phones'] = preg_split('/[;,]/', $payload['phones']);
            }

            // Limpieza básica de arrays (trim + filtrar vacíos)
            $payload['emails'] = array_values(array_filter(array_map('trim', (array)($payload['emails'] ?? []))));
            $payload['phones'] = array_values(array_filter(array_map('trim', (array)($payload['phones'] ?? []))));

            // Reinyectar al Request para que el validador los use
            $request->merge($payload);

            // Define validation rules with a custom rule for emails
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
                'emails' => 'required|array|min:1',
                'emails.*' => [
                    function ($attribute, $value, $fail) {
                        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fail("El correo '$value' debe ser válido.");
                        }
                    },
                ],
                // NEW: Validación de teléfonos como array y cada item con exactamente 10 dígitos
                'phones' => 'required|array|min:1',
                'phones.*' => ['required', 'regex:/^\d{10}$/'],
            ], [
                'name.required' => 'El campo nombre es obligatorio.',
                'message.required' => 'El campo mensaje es obligatorio.',
                'emails.required' => 'El campo correos electrónicos es obligatorio.',
                // NEW: Mensajes para teléfonos
                'phones.required' => 'El campo teléfonos es obligatorio.',
                'phones.min' => 'Debe ingresar al menos un teléfono.',
                'phones.*.required' => 'Cada teléfono es obligatorio.',
                'phones.*.regex' => 'Cada teléfono debe tener exactamente 10 dígitos (solo números).',
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            if ($reconciliationNotification_status === 'true') {
                return response()->json([
                    'success' => false,
                    'already_sent' => true,
                    'message' => 'La notificación ya fue enviada previamente.',
                ], 200); // 200 para que el frontend pueda manejarlo como "éxito ya procesado"
            }

            DB::beginTransaction();

            $data = $request->except('_token');

            // Emails (array) -> normalizar y deduplicar case-insensitive
            $emails = array_map('strtolower', (array)$data['emails']);        // NEW: normalizar
            $emails = array_unique($emails);                                   // Eliminar duplicados
            $data['emails'] = json_encode($emails);                            // Convertir a JSON para guardar

            // NEW: Phones (array) -> dejar solo dígitos, deduplicar y guardar como JSON
            $phones = array_map(function ($p) {
                return preg_replace('/\D+/', '', $p); // por si llegan con espacios o separadores
            }, (array)$data['phones']);
            $phones = array_filter($phones);            // quitar vacíos por seguridad
            $phones = array_unique($phones);            // quitar duplicados
            $data['phones'] = json_encode($phones);     // guardar como JSON

            $notification = $this->reconciliationNotificationRepository->store($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada exitosamente',
                'notification' => $notification,
            ], 200);
        } catch (ValidationException $e) {
            // Manejar errores de validación
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación: ' . $e->getMessage(),
            ], 500);
        }
    }
}
