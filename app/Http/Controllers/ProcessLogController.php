<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProcessBatcheError\ProcessBatcheErrorPaginateResource;
use App\Repositories\ProcessBatcheErrorRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

class ProcessLogController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ProcessBatcheErrorRepository $processBatcheErrorRepository,
    ) {}

    public function paginate(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->processBatcheErrorRepository->paginate($request->all());
            $tableData = ProcessBatcheErrorPaginateResource::collection($data);

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
}
