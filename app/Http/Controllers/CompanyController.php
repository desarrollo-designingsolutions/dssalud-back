<?php

namespace App\Http\Controllers;

use App\Helpers\Constants;
use App\Http\Requests\Company\CompanyStoreRequest;
use App\Http\Resources\Company\CompanyFormResource;
use App\Http\Resources\Company\CompanyPaginateResource;
use App\Repositories\CompanyRepository;
use App\Traits\HttpResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected CompanyRepository $companyRepository,
        protected QueryController $queryController,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->companyRepository->paginate($request->all());
            $tableData = CompanyPaginateResource::collection($data);

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
            $selectInfiniteCountries = $this->queryController->selectInfiniteCountries(request());

            $form['start_date'] = Carbon::now()->format('Y-m-d');

            return [
                'code' => 200,
                'form' => $form,
                ...$selectInfiniteCountries,
            ];
        });
    }

    public function store(CompanyStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->except(['start_date']);
            $company = $this->companyRepository->store($post);

            if ($request->file('logo')) {
                $file = $request->file('logo');
                $ruta = 'companies/company_'.$company->id.$request->input('logo');

                $logo = $file->store($ruta, Constants::DISK_FILES);
                $company->logo = $logo;
                $company->save();
            }

            return [
                'code' => 200,
                'message' => 'Compañia agregada correctamente',
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {
            $selectInfiniteCountries = $this->queryController->selectInfiniteCountries(request());

            $company = $this->companyRepository->find($id);
            $form = new CompanyFormResource($company);

            return [
                'code' => 200,
                'form' => $form,
                ...$selectInfiniteCountries,
            ];
        });
    }

    public function update(CompanyStoreRequest $request, $id)
    {
        return $this->runTransaction(function () use ($request, $id) {
            $post = $request->except(['start_date']);

            $company = $this->companyRepository->store($post, $id);

            if ($request->file('logo')) {
                $file = $request->file('logo');
                $ruta = 'companies/company_'.$company->id.$request->input('logo');
                $logo = $file->store($ruta, Constants::DISK_FILES);
                $company->logo = $logo;
                $company->save();
            }

            return [
                'code' => 200,
                'message' => 'Compañia modificada correctamente',
            ];
        });
    }

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {
            $company = $this->companyRepository->find($id);
            if ($company) {
                // Verificar si hay registros relacionados
                if ($company->users()->exists()) {
                    throw new \Exception(json_encode([
                        'message' => 'No se puede eliminar la compañía, porque tiene relación de datos en otros módulos',
                    ]));
                }

                $company->deletex();
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

    public function changeStatus(Request $request)
    {
        return $this->runTransaction(function () use ($request) {
            $model = $this->companyRepository->changeState($request->input('id'), strval($request->input('value')), $request->input('field'));

            ($model->is_active == 1) ? $msg = 'habilitada' : $msg = 'inhabilitada';

            DB::commit();

            return [
                'code' => 200,
                'message' => 'Compañia '.$msg.' con éxito',
            ];
        });
    }
}
