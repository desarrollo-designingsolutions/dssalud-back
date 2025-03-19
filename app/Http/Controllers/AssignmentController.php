<?php

namespace App\Http\Controllers;

use App\Helpers\Constants;
use App\Http\Requests\Assignment\AssignmentUploadCsvRequest;
use App\Http\Requests\Assignment\AssignmentStoreRequest;
use App\Http\Resources\Assignment\AssignmentFormResource;
use App\Http\Resources\Assignment\AssignmentPaginateResource;
use App\Imports\AssingmentImport;
use App\Models\Assignment;
use App\Models\Third;
use App\Repositories\CompanyRepository;
use App\Repositories\RoleRepository;
use App\Repositories\AssignmentRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AssignmentController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected QueryController $queryController,
        protected AssignmentRepository $assignmentRepository,
        protected CompanyRepository $companyRepository,
    ) {}

    public function paginate(Request $request, $id)
    {
        return $this->execute(function () use ($request, $id) {

            $request['id'] = $id;

            $data = $this->assignmentRepository->paginateThirds($request->all());
             $tableData = AssignmentPaginateResource::collection($data);

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

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {
            $assignment = $this->assignmentRepository->find($id);
            if ($assignment) {
                $assignment->delete();
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

    public function uploadCsv(AssignmentUploadCsvRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $user_id = $request->input('user_id');
            $company_id = $request->input('company_id');

            $assignment = $request->all();

            $csv = Excel::import(new AssingmentImport($user_id, $company_id), $request->file('archiveCsv'));

            return [
                'request' => $request->all(),
                'csv' => $csv,
            ];
        }, debug: false);
    }
}
