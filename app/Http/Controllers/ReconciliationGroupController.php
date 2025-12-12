<?php

namespace App\Http\Controllers;

use App\Exports\ReconciliationGroup\ReconciliationGroupExcelExport;
use App\Http\Requests\ReconciliationGroup\ReconciliationGroupStoreRequest;
use App\Http\Resources\ReconciliationGroup\ReconciliationGroupFormResource;
use App\Http\Resources\ReconciliationGroup\ReconciliationGroupPaginateResource;
use App\Repositories\ReconciliationGroupRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ReconciliationGroupController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ReconciliationGroupRepository $reconciliationGroupRepository,
        protected QueryController $queryController,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->reconciliationGroupRepository->paginate($request->all());
            $tableData = ReconciliationGroupPaginateResource::collection($data);

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
        try {

            $thirds = $this->queryController->selectInfiniteThird(request());

            return response()->json([
                'code' => 200,
                ...$thirds,
            ]);
        } catch (Throwable $th) {

            return response()->json(['code' => 500, $th->getMessage(), $th->getLine()]);
        }
    }

    public function store(ReconciliationGroupStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $post = $request->all();

            $reconciliationGroup = $this->reconciliationGroupRepository->store($post);

            DB::commit();

            return response()->json(['code' => 200, 'message' => 'Grupo de conciliación agregado correctamente', 'data' => $reconciliationGroup]);
        } catch (Throwable $th) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function edit($id)
    {
        try {
            $reconciliationGroup = $this->reconciliationGroupRepository->find($id);
            $form = new ReconciliationGroupFormResource($reconciliationGroup);

            $thirds = $this->queryController->selectInfiniteThird(request());

            return response()->json([
                'code' => 200,
                'form' => $form,
                ...$thirds,
            ]);
        } catch (Throwable $th) {

            return response()->json(['code' => 500, $th->getMessage(), $th->getLine()]);
        }
    }

    public function update(ReconciliationGroupStoreRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $post = $request->all();

            $reconciliationGroup = $this->reconciliationGroupRepository->store($post);

            DB::commit();

            return response()->json(['code' => 200, 'message' => 'Grupo de conciliación modificado correctamente', 'data' => $reconciliationGroup]);
        } catch (Throwable $th) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $reconciliationGroup = $this->reconciliationGroupRepository->find($id);
            if ($reconciliationGroup) {

                $reconciliationGroup->delete();
                $msg = 'Registro eliminado correctamente';
            } else {
                $msg = 'El registro no existe';
            }
            DB::commit();

            return response()->json(['code' => 200, 'message' => $msg]);
        } catch (Throwable $th) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => $th->getMessage(),
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function changeStatus(Request $request)
    {
        try {
            DB::beginTransaction();

            $model = $this->reconciliationGroupRepository->changeState($request->input('id'), strval($request->input('value')), $request->input('field'));

            ($model->is_active == 1) ? $msg = 'habilitada' : $msg = 'inhabilitada';

            DB::commit();

            return response()->json(['code' => 200, 'message' => 'Grupo de conciliación '.$msg.' con éxito']);
        } catch (Throwable $th) {
            DB::rollback();

            return response()->json(['code' => 500, 'message' => $th->getMessage()]);
        }
    }

    public function excelExport(Request $request)
    {
        return $this->execute(function () use ($request) {
            $request['typeData'] = 'all';

            $data = $this->reconciliationGroupRepository->paginate($request->all());

            $excel = Excel::raw(new ReconciliationGroupExcelExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
