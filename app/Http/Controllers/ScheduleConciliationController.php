<?php

namespace App\Http\Controllers;

use App\Enums\Schedule\ScheduleResponseStatusEnum;
use App\Enums\TypeEvent\TypeEventEnum;
use App\Exports\Schedule\ScheduleAgendaListExport;
use App\Http\Requests\Schedule\ScheduleConciliationStoreRequest;
use App\Http\Resources\Schedule\ScheduleAcceptFormResource;
use App\Http\Resources\Schedule\ScheduleConciliationFormResource;
use App\Http\Resources\Schedule\SchedulePaginateResource;
use App\Jobs\BrevoProcessSendEmail;
use App\Repositories\ScheduleConciliationRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ScheduleConciliationController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ScheduleRepository $scheduleRepository,
        protected ScheduleConciliationRepository $scheduleConciliationRepository,
        protected UserRepository $userRepository,
        protected QueryController $queryController,
    ) {}

    public function create()
    {
        return $this->execute(function () {
            $users = $this->queryController->selectInfiniteUser(request());
            $thirds = $this->queryController->selectInfiniteThird(request());
            $reconciliationGroup = $this->queryController->selectInfiniteReconciliationGroup(request());

            return [
                'code' => 200,
                ...$users,
                ...$thirds,
                ...$reconciliationGroup,
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {

            $schedule = $this->scheduleRepository->find($id);

            $form = new ScheduleConciliationFormResource($schedule);

            $users = $this->queryController->selectInfiniteUser(request());
            $typeEvents = $this->queryController->selectTypeEventEnum(request());
            $thirds = $this->queryController->selectInfiniteThird(request());

            return [
                'code' => 200,
                'form' => $form,
                ...$users,
                ...$typeEvents,
                ...$thirds,
            ];
        });
    }

    public function store(ScheduleConciliationStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $post = [
                'user_id' => $request['user_id'],
                'third_id' => $request['third_id'],
                'link' => $request['link'],
                'reconciliation_group_id' => $request['reconciliation_group_id'],
                'response_status' => ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_001->value,
                'response_date' => null,
            ];

            $scheduleConciliation = $this->scheduleConciliationRepository->store($post);

            $post = [
                'company_id' => $request['company_id'],
                'title' => $request['title'],
                'description' => $request['description'],
                'start_date' => $request['start_date'],
                'start_hour' => $request['start_hour'],
                'end_date' => $request['end_date'],
                'end_hour' => $request['end_hour'],
                'emails' => json_encode($request['emails'] ?? []),
                'all_day' => $request['all_day'] ?? 0,
                'scheduleable_id' => $scheduleConciliation->id,
                'scheduleable_type' => 'App\\Models\\ScheduleConciliation',
                'type_event' => TypeEventEnum::TYPE_EVENT_001->value,
            ];

            $schedule = $this->scheduleRepository->store($post);

            foreach ($request['emails'] as $key => $user) {

                BrevoProcessSendEmail::dispatch(
                    emailTo: [
                        [
                            'name' => 'Invitado',
                            'email' => $user,
                        ],
                    ],
                    subject: 'Invitacion a evento.',
                    templateId: 13,
                    params: [
                        'full_name' => 'Invitado',
                        'name' => $schedule->title,
                        'start_date' => $schedule->start_date,
                        'start_hour' => $schedule->start_hour,
                        'end_date' => $schedule->end_date,
                        'end_hour' => $schedule->end_hour,
                        'description' => $schedule->description,
                        'link' => $schedule->scheduleable->link,
                        'linkAccept' => env('SYSTEM_URL_FRONT').'ViewEventConciliationResponse/'.$schedule->id,
                        'bussines_name' => $schedule->third?->company?->name,
                    ],
                );
            }

            return [
                'code' => 200,
                'message' => 'Evento agregado correctamente',
            ];
        });
    }

    public function update(ScheduleConciliationStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $post = [
                'user_id' => $request['user_id'],
                'third_id' => $request['third_id'],
                'link' => $request['link'],
                'reconciliation_group_id' => $request['reconciliation_group_id'],
                'response_status' => ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_001->value,
                'response_date' => null,
            ];

            $scheduleConciliation = $this->scheduleConciliationRepository->store($post);

            $post = [
                'id' => $request['id'],
                'company_id' => $request['company_id'],
                'title' => $request['title'],
                'description' => $request['description'],
                'start_date' => $request['start_date'],
                'start_hour' => $request['start_hour'],
                'end_date' => $request['end_date'],
                'end_hour' => $request['end_hour'],
                'emails' => json_encode($request['emails'] ?? []),
                'all_day' => $request['all_day'] ?? 0,
                'scheduleable_id' => $scheduleConciliation->id,
                'scheduleable_type' => 'App\\Models\\ScheduleConciliation',
                'type_event' => TypeEventEnum::TYPE_EVENT_001->value,
            ];

            $schedule = $this->scheduleRepository->store($post);

            foreach ($request['emails'] as $key => $user) {
                BrevoProcessSendEmail::dispatch(
                    emailTo: [
                        [
                            'name' => 'Invitado',
                            'email' => $user,
                        ],
                    ],
                    subject: 'Invitacion a evento.',
                    templateId: 13,
                    params: [
                        'full_name' => 'Invitado',
                        'name' => $schedule->title,
                        'start_date' => $schedule->start_date,
                        'start_hour' => $schedule->start_hour,
                        'end_date' => $schedule->end_date,
                        'end_hour' => $schedule->end_hour,
                        'description' => $schedule->description,
                        'link' => $schedule->link,
                        'linkAccept' => env('SYSTEM_URL_FRONT').'ViewEventConciliationResponse/'.$schedule->id,
                        'bussines_name' => $schedule->third?->company?->name,
                    ],
                );
            }

            return [
                'code' => 200,
                'message' => 'Evento modificado correctamente',
            ];
        });
    }

    public function show($event_id)
    {
        return $this->execute(function () use ($event_id) {
            $data = $this->scheduleRepository->find($event_id, [
                'scheduleable.user:id,name,surname,photo,role_id',
                'scheduleable.user.role:id,description',
                'scheduleable.third:id,nit,name',
                'scheduleable.reconciliation_group:id,name',
            ]);

            $user = $data->scheduleable?->user;

            $user = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'photo' => $user->photo,
                'role' => $user->role,
            ];

            $third = $data->scheduleable?->third;

            $third = [
                'id' => $third->id,
                'nit' => $third->nit,
                'name' => $third->name,
            ];

            $reconciliation_group = $data->scheduleable?->reconciliation_group;

            if ($reconciliation_group) {
                $reconciliation_group = [
                    'id' => $reconciliation_group->id,
                    'name' => $reconciliation_group->name,
                ];
            } else {
                $reconciliation_group = [
                    'id' => null,
                    'name' => null,
                ];
            }

            $schedule = [
                'id' => $data->id,
                'title' => $data->title,
                'typeEvent_name' => $data->typeEvent?->name,
                'start_date' => $data->start_date,
                'start_hour' => $data->start_hour,
                'end_date' => $data->end_date,
                'end_hour' => $data->end_hour,
                'description' => $data->description,
                'user' => $user,
                'third' => $third,
                'reconciliation_group' => $reconciliation_group,
                'guests' => $data->emails ? json_decode($data->emails, true) : [],
                'response_status' => $data->scheduleable?->response_status,
                'link' => $data->scheduleable?->link,
            ];

            return [
                'code' => 200,
                'schedule' => $schedule,
            ];
        });
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $data = $this->scheduleRepository->find($id);
            if ($data) {
                $data->scheduleable()->delete();
                $data->delete();
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
                'message' => 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function getAcceptDataEvent($id)
    {
        return $this->execute(function () use ($id) {

            $schedule = $this->scheduleRepository->find($id);

            $event_data = new ScheduleAcceptFormResource($schedule);

            return [
                'code' => 200,
                'event_data' => $event_data,
            ];
        });
    }

    public function acceptInvitation($id)
    {
        return $this->execute(function () use ($id) {

            $schedule = $this->scheduleRepository->find($id);

            $schedule = $this->scheduleConciliationRepository->store([
                'id' => $schedule->scheduleable->id,
                'response_status' => ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_002,
                'response_date' => now(),
            ]);

            $event_data = [
                'response_status' => $schedule->response_status,
                'response_date' => Carbon::parse($schedule->response_date)->format('Y-m-d H:i:s'),
            ];

            return [
                'code' => 200,
                'event_data' => $event_data,
                'message' => 'Se ha aceptado el evento correctamente',
            ];
        });
    }

    public function rejectInvitation($id)
    {
        return $this->execute(function () use ($id) {

            $schedule = $this->scheduleRepository->find($id);

            $schedule = $this->scheduleConciliationRepository->store([
                'id' => $schedule->scheduleable->id,
                'response_status' => ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_003->value,
                'response_date' => now(),
            ]);

            $event_data = [
                'response_status' => $schedule->response_status,
                'response_date' => Carbon::parse($schedule->response_date)->format('Y-m-d H:i:s'),
            ];

            return [
                'code' => 200,
                'event_data' => $event_data,
                'message' => 'Se ha aceptado el evento correctamente',
            ];
        });
    }

    public function paginateAgenda(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->scheduleRepository->paginateAgenda($request->all());
            $tableData = SchedulePaginateResource::collection($data);

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

    public function excelExport(Request $request)
    {
        return $this->execute(function () use ($request) {
            $request['typeData'] = 'all';

            $data = $this->scheduleRepository->paginateAgenda($request->all());

            $excel = Excel::raw(new ScheduleAgendaListExport($data), \Maatwebsite\Excel\Excel::XLSX);

            $excelBase64 = base64_encode($excel);

            return [
                'code' => 200,
                'excel' => $excelBase64,
            ];
        });
    }
}
