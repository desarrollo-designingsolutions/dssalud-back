<?php

namespace App\Http\Controllers;

use App\Enums\Schedule\ScheduleResponseStatusEnum;
use App\Enums\TypeEvent\TypeEventEnum;
use App\Exports\ScheduleExport;
use App\Http\Requests\Schedule\ScheduleStoreRequest;
use App\Http\Resources\AppointmentType\AppointmentTypeSelectResource;
use App\Http\Resources\Cie10\Cie10SelectResource;
use App\Http\Resources\Consultory\ConsultorySelectResource;
use App\Http\Resources\Holidays\HolidaysListResource;
use App\Http\Resources\Patient\PatientSelectResource;
use App\Http\Resources\Schedule\ScheduleAcceptFormResource;
use App\Http\Resources\Schedule\ScheduleAgendaListResource;
use App\Http\Resources\Schedule\ScheduleFormResource;
use App\Http\Resources\Schedule\ScheduleInfoResource;
use App\Http\Resources\Schedule\ScheduleListResource;
use App\Http\Resources\User\UserSelectInfiniteResource;
use App\Jobs\BrevoProcessSendEmail;
use App\Jobs\ProcessSendEmail;
use App\Models\Schedule;
use App\Repositories\AppointmentTypeRepository;
use App\Repositories\Cie10Repository;
use App\Repositories\ConsultoryRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\StatusRepository;
use App\Repositories\TypeEventRepository;
use App\Repositories\UserGroupRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Aveonline\CalendarioColombia\CalendarioColombia;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ScheduleController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected ScheduleRepository $scheduleRepository,
        protected UserRepository $userRepository,
        protected QueryController $queryController,
    ) {}


    public function index(Request $request)
    {

        $dateStart = request()->query('dateStart');
        $dateFinal = request()->query('dateFinal');
        if ($dateStart && $dateFinal) {
            $dateStart = new \DateTime($dateStart);
            $dateStart = $dateStart->format('Y-m-d');
            $dateFinal = new \DateTime($dateFinal);
            $dateFinal = $dateFinal->format('Y-m-d');
        }

        $filter = [
            'user_id' => request()->query('user_id'),
            'dateStart' => $dateStart ?? null,
            'dateFinal' => $dateFinal ?? null,
        ];

        $data = $this->scheduleRepository->getEventsCalendar($filter);
        $schedules = ScheduleListResource::collection($data);

        $events = collect($schedules);

        $request['typeData'] = 'all';
        $typeEvents = $this->queryController->selectTypeEventEnum($request);

        return [
            'code' => 200,
            'events' => $events,
            ...$typeEvents,
        ];
    }

    public function getDataEvent($event_id)
    {
        return $this->execute(function () use ($event_id) {
            $data = $this->scheduleRepository->find($event_id);

            $user = $data->user;

            $user = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'photo' => $user->photo,
                'role' => $user->role,
            ];

            $schedule = [
                'id' => $data->id,
                'title' => $data->title,
                'typeEvent_name' => $data->typeEvent?->name,
                'start_date' => $data->start_date,
                'start_hour' => $data->start_hour,
                'end_date' => $data->end_date,
                'end_hour' => $data->end_hour,
                'description' => $data->description,
                'user' => [$user],
                'guests' => $data->emails ? json_decode($data->emails, true) : [],
            ];

            return [
                'code' => 200,
                'schedule' => $schedule,
            ];
        });
    }

    public function dataView()
    {
        return $this->execute(function () {
            $typeEvents = $this->queryController->selectTypeEventEnum(request());

            return [
                'code' => 200,
                ...$typeEvents,
            ];
        });
    }

    public function dataViewForm()
    {
        return $this->execute(function () {
            $users = $this->queryController->selectInfiniteUser(request());
            $typeEvents = $this->queryController->selectTypeEventEnum(request());
            $thirds = $this->queryController->selectInfiniteThird(request());

            return [
                'code' => 200,
                ...$users,
                ...$typeEvents,
                ...$thirds,
            ];
        });
    }

    public function dataEditForm($id)
    {
        return $this->execute(function () use ($id) {

            $schedule = $this->scheduleRepository->find($id);

            $form = new ScheduleFormResource($schedule);

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

    public function store(ScheduleStoreRequest $request)
    {
        // return $request;
        return $this->runTransaction(function () use ($request) {

            $post = $request->all();

            $post['type_event'] = TypeEventEnum::TYPE_EVENT_001->value;
            $post['response_status'] = ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_001->value;

            $emails = $post['emails']; // Array de correos
            $emails = array_unique($emails); // Eliminar correos duplicados
            $post['emails'] = json_encode($emails); // Convertir el array a JSON

            $schedule = $this->scheduleRepository->store($post);

            foreach ($emails as $key => $user) {

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
                        'linkAccept' => env('SYSTEM_URL_FRONT') . 'ViewEventConciliationMessage/' . $schedule->id,
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

    public function update(ScheduleStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {

            $post = $request->all();

            $post['type_event'] = TypeEventEnum::TYPE_EVENT_001->value;

            $emails = $post['emails']; // Array de correos
            $emails = array_unique($emails); // Eliminar correos duplicados
            $post['emails'] = json_encode($emails); // Convertir el array a JSON

            $schedule = $this->scheduleRepository->store($post);

            foreach ($emails as $key => $user) {

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
                        'linkAccept' => env('SYSTEM_URL_FRONT') . 'ViewEventConciliationMessage/' . $schedule->id,
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

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $data = $this->scheduleRepository->find($id);
            if ($data) {
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

            $schedule['response_status'] = ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_002;
            $schedule['response_date'] = now();

            $schedule = $schedule->toArray();

            $schedule = $this->scheduleRepository->store($schedule);

            $event_data = new ScheduleAcceptFormResource($schedule);

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

            $schedule['response_status'] = ScheduleResponseStatusEnum::SCHEDULE_RESPONSE_STATUS_003->value;
            $schedule['response_date'] = now();

            $schedule = $schedule->toArray();

            $schedule = $this->scheduleRepository->store($schedule);

            $event_data = new ScheduleAcceptFormResource($schedule);

            return [
                'code' => 200,
                'event_data' => $event_data,
                'message' => 'Se ha rechazado el evento correctamente',
            ];
        });
    }
}
