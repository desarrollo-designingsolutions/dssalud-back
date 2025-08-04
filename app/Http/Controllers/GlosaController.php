<?php

namespace App\Http\Controllers;

use App\Events\ModalError;
use App\Exports\Glosa\GlosaExcelErrorsValidationExport;
use App\Helpers\Common\ErrorCollector;
use App\Helpers\Common\ImportCsvValidator;
use App\Helpers\Constants;
use App\Http\Requests\Glosa\GlosaMasiveStoreRequest;
use App\Http\Requests\Glosa\GlosaStoreRequest;
use App\Http\Requests\Glosa\GlosaUploadCsvRequest;
use App\Http\Resources\Glosa\GlosaFormResource;
use App\Http\Resources\Glosa\GlosaPaginateResource;
use App\Imports\GlosaImport;
use App\Jobs\BrevoProcessSendEmail;
use App\Models\User;
use App\Notifications\BellNotification;
use App\Repositories\CodeGlosaRepository;
use App\Repositories\GlosaRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Services\CacheService;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GlosaController extends Controller
{
    use HttpResponseTrait;

    private $key_redis_project;

    public function __construct(
        protected UserRepository $userRepository,
        protected CodeGlosaRepository $codeGlosaRepository,
        protected GlosaRepository $glosaRepository,
        protected QueryController $queryController,
        protected ServiceRepository $serviceRepository,
        protected CacheService $cacheService,
    ) {
        $this->key_redis_project = env('KEY_REDIS_PROJECT');
    }

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->glosaRepository->paginate($request->all());
            $tableData = GlosaPaginateResource::collection($data);

            return [
                'code' => 200,
                'tableData' => $tableData,
                'lastPage' => $data->lastPage(),
                'totalData' => $data->total(),
                'totalPage' => $data->perPage(),
                'currentPage' => $data->currentPage(),
            ];
        });
    }

    public function create()
    {
        return $this->execute(function () {

            return [
                'code' => 200,
            ];
        });
    }

    public function store(GlosaStoreRequest $request)
    {

        return $this->runTransaction(function () use ($request) {

            $glosa = $this->glosaRepository->store($request->all());

            changeServiceData($glosa->service_id);

            $this->cacheService->clearByPrefix($this->key_redis_project.'string:glosas*');

            return [
                'code' => 200,
                'message' => 'Glosa agregada correctamente',
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {

            $glosa = $this->glosaRepository->find($id);
            $form = new GlosaFormResource($glosa);

            return [
                'code' => 200,
                'form' => $form,
            ];
        });
    }

    public function update(GlosaStoreRequest $request, $id)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->except([]);

            $glosa = $this->glosaRepository->store($post);

            changeServiceData($glosa->service_id);

            $this->cacheService->clearByPrefix($this->key_redis_project.'string:glosas*');

            return [
                'code' => 200,
                'message' => 'Glosa modificada correctamente',
            ];
        });
    }

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {
            $glosa = $this->glosaRepository->find($id);
            if ($glosa) {

                $service_id = $glosa->service_id;

                $glosa->delete();

                changeServiceData($service_id);

                $this->cacheService->clearByPrefix($this->key_redis_project.'string:glosas*');

                $msg = 'Registro eliminado correctamente';
            } else {
                $msg = 'El registro no existe';
            }

            DB::commit();

            return [
                'code' => 200,
                'message' => $msg,
            ];
        }, 200);
    }

    public function uploadCsvGlosa(GlosaUploadCsvRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $keyErrorRedis = 'list:glosas_import_errors_'.$request->input('user_id');

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $users = $this->userRepository->list([
                'is_active' => 1,
                'company_id' => $company_id,
                'typeData' => 'all',
            ]);

            $codeGlosas = $this->codeGlosaRepository->list([
                'is_active' => 1,
                'typeData' => 'all',
            ]);

            $services = $this->serviceRepository->getServicesToImportGlosas($request->all());

            $file = $request->file('archiveCsv');

            $file_path = $file->getRealPath();

            if (! ImportCsvValidator::validate($user_id, $keyErrorRedis, $file_path, 5, 'glosa')) {
                $errors = ErrorCollector::getErrors($keyErrorRedis);  // Obtener lista de errores

                // Convert array to JSON
                $routeJson = null;
                if (count($errors) > 0) {
                    $nameFile = 'error_'.$user_id.'.json';
                    $routeJson = 'companies/company_'.$company_id.'/assignment/errors/'.$nameFile; // Ruta donde se guardará la carpeta
                    Storage::disk(Constants::DISK_FILES)->put($routeJson, json_encode($errors, JSON_PRETTY_PRINT));
                }

                // Enviar notificación al usuario
                $title = 'Importación de glosas';
                $subtitle = 'Se encontraron errores en la estructura del archivo que esta intentando importar.';

                $this->sendNotification(
                    $user_id,
                    [
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'data_import' => $errors,
                    ]
                );

                // Emitir errores al front
                ModalError::dispatch("glosaStructureModalErrors.{$user_id}", $routeJson);

                return [
                    'code' => 422,
                    'errors' => $errors,
                ];
            } else {
                $csv = Excel::import(new GlosaImport($user_id, $company_id, $services, $users, $codeGlosas), $request->file('archiveCsv'));

                return [
                    'code' => 200,
                    'csv' => $csv,
                ];
            }
        });
    }

    private function sendNotification($userId, $data)
    {
        // Obtener el objeto User a partir del ID
        $user = User::find($userId);

        if ($user) {
            // Enviar notificación
            $user->notify(new BellNotification($data));

            // Enviar el correo usando el job de Brevo
            BrevoProcessSendEmail::dispatch(
                emailTo: [
                    [
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ],
                ],
                subject: $data['title'],
                templateId: 11,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    'full_name' => $user->full_name,
                    'subtitle' => $data['subtitle'],
                    'bussines_name' => $user->company?->name,
                    'data_import' => $data['data_import'],
                    'show_table_errors' => count($data['data_import']) > 0 ? true : false,
                ],
            );
        }
    }

    private function excelErrorsValidationStructure($data)
    {

        $excel = Excel::raw(new GlosaExcelErrorsValidationExport($data), \Maatwebsite\Excel\Excel::XLSX);

        return $excel;
    }

    private function exportCsvErrorsValidationStructure($data)
    {
        // Agrupar por 'row'
        $groupedErrors = collect($data)->groupBy('row');

        // Obtener un solo 'data' por grupo (el primero, por ejemplo)
        $result = $groupedErrors->map(function ($group) {
            // Tomar el primer elemento del grupo y devolver solo su 'data'
            return $group->first()['data'] ?? null;
        })->values();

        // Generar el CSV con Laravel Excel
        $csv = Excel::raw(new GlosaExcelErrorsValidationExport($result), \Maatwebsite\Excel\Excel::CSV);

        return $csv;
    }

    public function createMasive()
    {
        return $this->execute(function () {
            $selectInfiniteCodeGlosas = $this->queryController->selectInfiniteCodeGlosa(request());

            return [
                'code' => 200,
                ...$selectInfiniteCodeGlosas,
            ];
        });
    }

    public function storeMasive(GlosaMasiveStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $servicesIDs = $request->input('servicesIds');

            $company_id = $request->input('company_id');

            foreach ($servicesIDs as $key => $serviceId) {
                $service = $this->serviceRepository->find($serviceId);

                foreach ($request->input('glosas') as $key => $value) {
                    $data = [
                        'user_id' => $value['user_id'],
                        'company_id' => $company_id,
                        'service_id' => $service->id,
                        'code_glosa_id' => $value['code_glosa_id'],
                        'glosa_value' => $value['partialValue'] * $service->total_value / 100,
                        'observation' => $value['observation'],
                    ];
                    $this->glosaRepository->store($data);
                }

                changeServiceData($serviceId);
            }

            $this->cacheService->clearByPrefix($this->key_redis_project.'string:glosas*');

            return [
                'code' => 200,
                'message' => 'Glosa/s agregada/s correctamente',
            ];
        });
    }

    public function getContentJson(Request $request)
    {
        return $this->execute(function () use ($request) {
            // Obtener el contenido del archivo

            $jsonContent = openFileJson($request['url_json']);

            return [
                'code' => 200,
                'data' => $jsonContent,
            ];
        });
    }
}
