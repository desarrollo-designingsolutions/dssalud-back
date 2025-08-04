<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentBatche\StatusAssignmentBatcheEnum;
use App\Http\Requests\AssignmentBatche\AssignmentBatcheStoreRequest;
use App\Http\Resources\AssignmentBatche\AssignmentBatcheFormResource;
use App\Http\Resources\AssignmentBatche\AssignmentBatchePaginateResource;
use App\Repositories\AssignmentBatcheRepository;
use App\Repositories\CompanyRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

class AssignmentBatcheController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected QueryController $queryController,
        protected AssignmentBatcheRepository $assignmentBatcheRepository,
        protected CompanyRepository $companyRepository,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->assignmentBatcheRepository->paginate($request->all());
            $tableData = AssignmentBatchePaginateResource::collection($data);

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

    public function store(AssignmentBatcheStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->all();
            $post['status'] = StatusAssignmentBatcheEnum::ASSIGNMENT_BATCHE_EST_001;

            $data = $this->assignmentBatcheRepository->store($post);

            return [
                'code' => 200,
                'message' => 'Paquete agregado correctamente',
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {

            $assignmentBatche = $this->assignmentBatcheRepository->find($id);
            $form = new AssignmentBatcheFormResource($assignmentBatche);

            return [
                'code' => 200,
                'form' => $form,
            ];
        });
    }

    public function update(AssignmentBatcheStoreRequest $request, $id)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->all();

            $data = $this->assignmentBatcheRepository->store($post);

            return [
                'code' => 200,
                'message' => 'Paquete agregado correctamente',
            ];
        });
    }

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {
            $assignmentBatche = $this->assignmentBatcheRepository->find($id);
            if ($assignmentBatche) {
                $assignmentBatche->delete();
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
}
