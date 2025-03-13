<?php

namespace App\Http\Controllers;

use App\Http\Resources\Audit\AuditResource;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function timeLine(Request $request)
    {
        return $this->execute(function () use ($request) {

            $request['typeData'] = 'all';

            $request['sortBy'] = json_encode([
                [
                    'key' => 'created_at',
                    'order' => 'desc',
                ],
            ]);

            switch ($request->input('auditable_type')) {
                case 'User':
                    $audits = $this->userRepository->timeLine($request->all());
                default:
                    // code...
                    break;
            }

            $audits = AuditResource::collection($audits);

            return [
                'code' => 200,
                'audits' => $audits,
            ];
        });
    }

    public function count(Request $request)
    {
        return $this->execute(function () use ($request) {
            switch ($request->input('auditable_type')) {
                case 'User':
                    $count = $this->userRepository->timeLine(['record_id' => $request['record_id'], 'typeData' => 'count']);
                default:
                    // code...
                    break;
            }

            return [
                'count' => $count,
            ];
        });
    }
}
