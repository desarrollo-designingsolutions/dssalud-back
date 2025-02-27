<?php

namespace App\Http\Controllers;

use App\Enums\StatusRipsEnum;
use App\Enums\TypeRipsEnum;
use App\Exports\Rips\ExcelErrorsValidationExport;
use App\Http\Resources\Rip\RipListResource;
use App\Jobs\ProcessSendEmail;
use App\Jobs\Rips\ProcessSaveRips;
use App\Jobs\Rips\ProcessValidationZip;
use App\Repositories\RipRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class RipAutomaticController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected RipRepository $ripRepository,
    ) {}

    public function uploadZip(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $company_id = $request->input("company_id");
            $user_id = $request->input("user_id");


            //guardo el registro del rip en la bd
            $rip = $this->ripRepository->store([
                'numeration' =>  $this->ripRepository->numerationGenerate($company_id),
                'company_id' => $company_id,
                'user_id' => $user_id,
                'numInvoices' => 0,
                'successfulInvoices' => 0,
                'failedInvoices' => 0,
                'sumVr' => 0,
                'status' => StatusRipsEnum::IN_PROCESS,
                'type' => TypeRipsEnum::AUTOMATIC,
            ]);

            if ($request->hasFile('archiveZip')) {
                $file = $request->file('archiveZip');
                $ruta = '/companies/company_' . $company_id . '/rips/automatic/rip_' . $rip->numeration; // Ruta donde se guardará la carpeta
                $nombreArchivo = $file->getClientOriginalName(); // Obtiene el nombre original del archivo
                $path_zip = $file->storeAs($ruta, $nombreArchivo, 'public'); // Guarda el archivo con el nombre original
                $rip->path_zip = $path_zip;
                $rip->save();
            }


            $auth = $this->userRepository->find($user_id);

            ProcessSendEmail::dispatch($auth->email, 'Mails.Rips.RipsCreated', 'Rips #' . $rip->id . ' iniciado', [
                'rips' => $rip,
            ]);

            //VALIDACION ZIP
            ProcessValidationZip::dispatch($rip->id, $auth, $company_id);


            return $rip;
        });
    }

    public function list(Request $request)
    {
        return $this->execute(function () use ($request) {

            $request["type"] = "Automatic";
            $rips = $this->ripRepository->list($request->all());
            $listRips = RipListResource::collection($rips);

            return [
                'code' => 200,
                'tableData' => $listRips,
                'lastPage' => $rips->lastPage(),
                'totalData' => $rips->total(),
                'totalPage' => $rips->perPage(),
                'currentPage' => $rips->currentPage(),
            ];
        });
    }

    public function storeJson(Request $request)
    {
        return $this->runTransaction(function () use ($request) {

            $user_id = $request->input("user_id");

            $rip = $this->ripRepository->find($request->input('id'));
            $rip->status = StatusRipsEnum::IN_PROCESS;
            $rip->save();

            $auth = $this->userRepository->find($user_id);

            ProcessSaveRips::dispatch($rip->id, $auth);

            return [
                'code' => 200,
                'message' => 'El rips se esta procesando',
            ];
        });
    }

    public function showErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            // Obtener los mensajes de errores de las validaciones
            $data = $this->ripRepository->getValidationsErrorMessages($request->input('id'));

            return [
                'code' => 200,
                ...$data,
            ];
        });
    }

    public function excelErrorsValidation(Request $request)
    {
        return $this->execute(function () use ($request) {

            // Obtener los mensajes de errores de las validaciones
            $data = $this->ripRepository->getValidationsErrorMessages($request->input('id'));

            $excel = Excel::raw(new ExcelErrorsValidationExport($data["errorMessages"]), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);


            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
