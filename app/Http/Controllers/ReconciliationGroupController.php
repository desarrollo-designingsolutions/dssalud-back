<?php

namespace App\Http\Controllers;

use App\Enums\ReconciliationGroup\ReconciliationGroupStatusEnum;
use App\Events\FilingInvoiceRowUpdated;
use App\Helpers\Constants;
use App\Http\Requests\File\FileStoreRequest;
use App\Http\Resources\File\FileFormResource;
use App\Http\Resources\File\FileListResource;
use App\Http\Resources\File\FileListTableV2Resource;
use App\Jobs\File\ProcessMassUpload;
use App\Models\ReconciliationNotification;
use App\Repositories\FileRepository;
use App\Repositories\ReconciliationGroupRepository;
use App\Repositories\ReconciliationNotificationRepository;
use App\Repositories\ThirdRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReconciliationGroupController extends Controller
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

        $third = $reconciliationGroup->third;

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

            $reconciliationGroup = $this->reconciliationGroupRepository->find($request->input('reconciliation_group_id'), ['reconciliationNotification']);
            $reconciliationNotification_status = $reconciliationGroup->reconciliationNotification ? 'true' : 'false';


            // Define validation rules with a custom rule for emails
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
                'emails' => 'required|array',
                'emails.*' => [
                    function ($attribute, $value, $fail) {
                        // Check if the value is a valid email
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fail("El correo '$value' debe ser válido.");
                        }
                    },
                ],
                'reconciliation_group_id' => 'required|string|exists:reconciliation_groups,id',
            ], [
                'name.required' => 'El campo nombre es obligatorio.',
                'message.required' => 'El campo mensaje es obligatorio.',
                'emails.required' => 'El campo correos electrónicos es obligatorio.',
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
            $emails = $data['emails']; // Array de correos
            $emails = array_unique($emails); // Eliminar correos duplicados
            $data['emails'] = json_encode($emails); // Convertir el array a JSON

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
                'errors' => $e->errors(), // Devuelve los mensajes de error por campo
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
