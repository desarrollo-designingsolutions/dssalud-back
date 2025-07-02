<?php

namespace App\Http\Controllers;

use App\Events\FilingInvoiceRowUpdated;
use App\Helpers\Constants;
use App\Http\Requests\File\FileStoreRequest;
use App\Http\Resources\File\FileFormResource;
use App\Http\Resources\File\FileListResource;
use App\Http\Resources\File\FileListTableV2Resource;
use App\Jobs\File\ProcessMassUpload;
use App\Repositories\FileRepository;
use App\Repositories\ReconciliationGroupRepository;
use App\Repositories\ThirdRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class ReconciliationGroupController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ReconciliationGroupRepository $reconciliationGroupRepository,
        protected ThirdRepository $thirdRepository,
    ) {}

    public function index(Request $request, $id)
    {
        $reconciliationGroup = $this->reconciliationGroupRepository->find($id, ['third']);

        $third = $reconciliationGroup->third;
        $invoices = $reconciliationGroup->invoices;
        return view('ReconciliationGroup.index', [
            'reconciliationGroup' => $reconciliationGroup,
            'third' => $third,
            'invoices_count' => $invoices->count(),
        ]);
    }
}
