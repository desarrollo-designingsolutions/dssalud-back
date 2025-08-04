<?php

namespace App\Http\Controllers;

use App\Http\Resources\Schedule\ScheduleListResource;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

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
}
