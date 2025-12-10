<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contract\ContractStoreRequest;
use App\Http\Resources\Contract\ContractFormResource;
use App\Http\Resources\Contract\ContractListResource;
use App\Http\Resources\Contract\ContractPaginateResource;
use App\Repositories\ContractRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ContractController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ContractRepository $contractRepository,
        protected QueryController $queryController,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->contractRepository->paginate($request->all());
            $tableData = ContractPaginateResource::collection($data);

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

            $thirds = $this->queryController->selectInfiniteThird(request());
            return [
                'code' => 200,
                ...$thirds,
            ];
        });
    }

    public function store(ContractStoreRequest $request)
    {

        return $this->runTransaction(function () use ($request) {

            $post = $request->all();

            $contract = $this->contractRepository->store($post);

            return [
                'code' => 200,
                'message' => 'Contrato agregado correctamente',
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {

            $contract = $this->contractRepository->find($id);
            $form = new ContractFormResource($contract);

            $thirds = $this->queryController->selectInfiniteThird(request());

            return [
                'code' => 200,
                'form' => $form,
                ...$thirds,
            ];
        });
    }

    public function update(ContractStoreRequest $request, $id)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->all();

            $contract = $this->contractRepository->store($post);

            return [
                'code' => 200,
                'message' => 'Contrato modificada correctamente',
            ];
        });
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $contract = $this->contractRepository->find($id);
            if ($contract) {

                $contract->delete();
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
}
