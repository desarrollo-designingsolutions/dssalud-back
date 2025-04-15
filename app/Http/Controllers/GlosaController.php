<?php

namespace App\Http\Controllers;

use App\Http\Requests\Glosa\GlosaMasiveStoreRequest;
use App\Http\Requests\Glosa\GlosaStoreRequest;
use App\Http\Requests\Glosa\GlosaUploadCsvRequest;
use App\Http\Resources\Glosa\GlosaFormResource;
use App\Http\Resources\Glosa\GlosaPaginateResource;
use App\Imports\GlosaImport;
use App\Models\CodeGlosa;
use App\Models\User;
use App\Repositories\CodeGlosaRepository;
use App\Repositories\GlosaRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Services\CacheService;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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

            $this->cacheService->clearByPrefix($this->key_redis_project . 'string:glosas*');

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

            $this->cacheService->clearByPrefix($this->key_redis_project . 'string:glosas*');

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

                $this->cacheService->clearByPrefix($this->key_redis_project . 'string:glosas*');

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

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $users = $this->userRepository->list([
                "is_active" => 1,
                "company_id" => $company_id,
                "typeData" => "all",
            ]);

            $codeGlosas = $this->codeGlosaRepository->list([
                "is_active" => 1,
                "typeData" => "all",
            ]);

          return  $services = $this->serviceRepository->getServicesToImportGlosas($request->all());

            $csv = Excel::import(new GlosaImport($user_id, $company_id, $services,$users,$codeGlosas), $request->file('archiveCsv'));

            return [
                'code' => 200,
                'csv' => $csv,
            ];
        });
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

            foreach ($servicesIDs as $key => $serviceId) {
                $service = $this->serviceRepository->find($serviceId);

                foreach ($request->input('glosas') as $key => $value) {
                    $data = [
                        'user_id' => $value['user_id'],
                        'service_id' => $service->id,
                        'code_glosa_id' => $value['code_glosa_id'],
                        'glosa_value' => $value['partialValue'] * $service->total_value / 100,
                        'observation' => $value['observation'],
                    ];
                    $this->glosaRepository->store($data);
                }

                changeServiceData($serviceId);
            }

            $this->cacheService->clearByPrefix($this->key_redis_project . 'string:glosas*');

            return [
                'code' => 200,
                'message' => 'Glosa/s agregada/s correctamente',
            ];
        });
    }
}
