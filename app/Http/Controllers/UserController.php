<?php

namespace App\Http\Controllers;

use App\Helpers\Constants;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Resources\User\UserFormResource;
use App\Http\Resources\User\UserPaginateResource;
use App\Models\ProcessBatch;
use App\Repositories\CompanyRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected QueryController $queryController,
        protected UserRepository $userRepository,
        protected RoleRepository $roleRepository,
        protected CompanyRepository $companyRepository,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->userRepository->paginate($request->all());
            $tableData = UserPaginateResource::collection($data);

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
            $roles = $this->roleRepository->selectList(request());
            $companies = $this->companyRepository->selectList();

            return [
                'code' => 200,
                'roles' => $roles,
                'companies' => $companies,
            ];
        });
    }

    public function store(UserStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->except(['confirmedPassword']);

            $data = $this->userRepository->store($post, withCompany: false);

            $data->syncRoles($request->input('role_id'));

            return [
                'code' => 200,
                'message' => 'Usuario agregado correctamente',
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {
            $roles = $this->roleRepository->selectList(request());
            $companies = $this->companyRepository->selectList();

            $user = $this->userRepository->find($id);
            $form = new UserFormResource($user);

            return [
                'code' => 200,
                'form' => $form,
                'roles' => $roles,
                'companies' => $companies,
            ];
        });
    }

    public function update(UserStoreRequest $request, $id)
    {
        return $this->runTransaction(function () use ($request, $id) {
            $post = $request->except(['confirmedPassword']);

            $data = $this->userRepository->store($post, $id, withCompany: false);

            $data->syncRoles($request->input('role_id'));

            clearCacheLaravel();

            return [
                'code' => 200,
                'message' => 'Usuario modificado correctamente',
            ];
        });
    }

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {
            $user = $this->userRepository->find($id);
            if ($user) {
                $user->delete();
                $msg = 'Registro eliminado correctamente';
            } else {
                $msg = 'El registro no existe';
            }

            return [
                'code' => 200,
                'message' => $msg,
            ];
        });
    }

    public function changeStatus(Request $request)
    {
        return $this->runTransaction(function () use ($request) {
            $model = $this->userRepository->changeState($request->input('id'), strval($request->input('value')), $request->input('field'));

            ($model->is_active == 1) ? $msg = 'habilitada' : $msg = 'inhabilitada';

            return [
                'code' => 200,
                'message' => 'User '.$msg.' con éxito',
            ];
        });
    }

    public function changePassword(Request $request)
    {
        return $this->execute(function () use ($request) {
            // Obtener el usuario autenticado
            $user = $this->userRepository->find($request->input('id'));

            // Cambiar la contraseña
            $user->password = $request->input('new_password');
            $user->first_time = false;
            $user->save();

            return [
                'code' => 200,
                'message' => 'Contraseña modificada con éxito.',
            ];
        });
    }

    public function changePhoto(Request $request)
    {
        return $this->runTransaction(function () use ($request) {
            $user = $this->userRepository->find($request->input('user_id'));

            // Cambiar la photo
            if ($request->file('photo')) {
                $file = $request->file('photo');
                $ruta = 'companies/company_'.$user->company_id.'/'.$user->id.$request->input('photo');
                $photo = $file->store($ruta, Constants::DISK_FILES);
                $user->photo = $photo;
                $user->save();
            }

            return [
                'code' => 200,
                'message' => 'Foto modificada con éxito.',
                'photo' => $user->photo,
            ];
        });
    }

    public function getUserProcesses(Request $request, $id)
    {
        $processes = ProcessBatch::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($batch) {
                $progress = $batch->total_records > 0 ? ($batch->processed_records / $batch->total_records) * 100 : 0;
                if ($batch->status == 'completed' || $batch->status == 'failed') { // Asegurar 100% para completados/fallidos
                    $progress = 100;
                }

                $metadata = json_decode($batch->metadata, true);

                // Determinar current_action basado en el estado
                $currentAction = 'Carga inicial';
                if ($batch->status === 'active') {
                    $currentAction = 'Procesando datos';
                } elseif ($batch->status === 'queued') {
                    $currentAction = 'En cola de espera';
                } elseif ($batch->status === 'completed') {
                    $currentAction = 'Importación finalizada';
                } elseif ($batch->status === 'failed') {
                    $currentAction = 'Importación fallida';
                }

                // Mapear el estado del backend al estado esperado por el frontend
                $frontendStatus = $this->mapBackendStatusToFrontend($batch->status);

                return [
                    'batch_id' => $batch->batch_id,
                    'file_name' => $metadata ? $metadata['file_name'] : 'Archivo desconocido',
                    'progress' => round($progress, 2),
                    'current_element' => (string) $batch->processed_records, // Mapear processed_records a current_element
                    'current_action' => $currentAction, // Establecer acción apropiada
                    'status' => $frontendStatus, // Usar el estado mapeado
                    'started_at' => $batch->created_at->toIso8601String(),
                    // completed_at debe establecerse para los estados 'completed' y 'failed'
                    'completed_at' => in_array($batch->status, ['completed', 'failed']) ? $batch->updated_at->toIso8601String() : null,
                    'metadata' => [
                        'total_records' => $batch->total_records,
                        'processed_records' => $batch->processed_records,
                        'errors_count' => $batch->error_count,
                        'processing_start_time' => $batch->created_at->toIso8601String(),
                        'connection_status' => 'disconnected', // Siempre desconectado para carga histórica
                        // Añadir otros campos de metadata si son necesarios por el frontend
                        'file_size' => $metadata['file_size'] ?? 0, // Asumiendo que file_size está en metadata
                        'current_sheet' => 1, // Valor por defecto para histórico
                        'total_sheets' => 1, // Valor por defecto para histórico
                        'warnings_count' => 0, // Valor por defecto para histórico
                        'processing_speed' => 0, // Valor por defecto para histórico
                        'estimated_time_remaining' => 0, // Valor por defecto para histórico
                    ],
                ];
            });

        return response()->json(['processes' => $processes], 200);
    }

    // Helper function to map backend status to frontend status
    private function mapBackendStatusToFrontend(string $backendStatus): string
    {
        return match ($backendStatus) {
            'active', 'finalizing' => 'active',
            'queued' => 'queued',
            'completed', 'completed_with_errors' => 'completed',
            'failed' => 'error',
            default => 'active', // Default to active if unknown
        };
    }
}
