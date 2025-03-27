<?php

namespace App\Http\Controllers;

use App\Helpers\Constants;
use App\Http\Requests\Glosa\GlosaStoreRequest;
use App\Http\Requests\Glosa\GlosaUploadCsvRequest;
use App\Http\Resources\Glosa\GlosaFormResource;
use App\Http\Resources\Glosa\GlosaPaginateResource;
use App\Imports\GlosaImport;
use App\Repositories\GlosaRepository;
use App\Repositories\ServiceRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


class GlosaController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected GlosaRepository $glosaRepository,
        protected QueryController $queryController,
        protected ServiceRepository $serviceRepository,
    ) {}

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

            $data = $this->glosaRepository->store($request->all());

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
        return $this->runTransaction(function () use ($request, $id) {
            $post = $request->except([]);

            $glosa = $this->glosaRepository->store($post);

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

                $glosa->delete();
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

            $csv = Excel::import(new GlosaImport($user_id), $request->file('archiveCsv'));

            return [
                'request' => $request->all(),
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

    public function storeMasive(Request $request)
    {

        return $this->runTransaction(function () use ($request) {

            $servicesIDs = $request->input('servicesIds');

            foreach($servicesIDs as $key => $serviceId){
                $service = $this->serviceRepository->find($serviceId);

                foreach ($request->input('glosas') as $key => $value) {
                    $data = [
                        'user_id' => $value['user_id'],
                        'service_id' => $service->id,
                        'code_glosa_id' => $value['code_glosa_id'],
                        'glosa_value' => $value['partialValue'] * $service->total_value/100,
                        'observation' => $value['observation'],
                    ];
                    $this->glosaRepository->store($data);
                }
            }


            return [
                'code' => 200,
                'message' => 'Glosa/s agregada/s correctamente',
            ];
        });
    }
}
