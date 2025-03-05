<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFilingEnum;
use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Enums\Role\RoleTypeEnum;
use App\Http\Resources\Contract\ContractSelectInfiniteResource;
use App\Http\Resources\Country\CountrySelectResource;
use App\Http\Resources\SupportType\SupportTypeSelectInfiniteResource;
use App\Http\Resources\Third\ThirdSelectInfiniteResource;
use App\Repositories\CityRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CountryRepository;
use App\Repositories\StateRepository;
use App\Repositories\SupportTypeRepository;
use App\Repositories\ThirdRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class QueryController extends Controller
{
    public function __construct(
        protected CountryRepository $countryRepository,
        protected StateRepository $stateRepository,
        protected CityRepository $cityRepository,
        protected UserRepository $userRepository,
        protected ContractRepository $contractRepository,
        protected SupportTypeRepository $supportTypeRepository,
        protected ThirdRepository $thirdRepository,
    ) {}

    public function selectInfiniteCountries(Request $request)
    {
        $countries = $this->countryRepository->list($request->all());

        $dataCountries = CountrySelectResource::collection($countries);

        return [
            'code' => 200,
            'countries_arrayInfo' => $dataCountries,
            'countries_countLinks' => $countries->lastPage(),
        ];
    }

    public function selectStates($country_id)
    {
        $states = $this->stateRepository->selectList($country_id);

        return [
            'code' => 200,
            'states' => $states,
        ];
    }

    public function selectCities($state_id)
    {
        $cities = $this->cityRepository->selectList($state_id);

        return [
            'code' => 200,
            'cities' => $cities,
        ];
    }

    public function selectCitiesCountry($country_id)
    {
        $country = $this->countryRepository->find($country_id, ['cities']);

        return response()->json([
            'code' => 200,
            'message' => 'Datos Encontrados',
            'cities' => $country['cities']->map(function ($item) {
                return [
                    'value' => $item->id,
                    'title' => $item->name,
                ];
            }),
        ]);
    }

    public function selectStatusFilingInvoiceEnum(Request $request)
    {
        $status = StatusFilingInvoiceEnum::cases();

        $status = collect($status)->map(function ($item) {
            return [
                "value" => $item,
                "title" => $item->description(),
            ];
        });

        return [
            'code' => 200,
            'statusFilingInvoiceEnum_arrayInfo' => $status->values(),
            'statusFilingInvoiceEnum_countLinks' => 1,
        ];
    }
    public function selectStatusXmlFilingInvoiceEnum(Request $request)
    {
        $status = StatusFilingInvoiceEnum::cases();


        $status = array_filter($status, function ($case)  {
            return in_array($case->value, ["FILINGINVOICE_EST_003", "FILINGINVOICE_EST_004"]);
        });



        $status = collect($status)->map(function ($item) {
            return [
                "value" => $item,
                "title" => $item->description(),
            ];
        });

        return [
            'code' => 200,
            'statusXmlFilingInvoiceEnum_arrayInfo' => $status->values(),
            'statusXmlFilingInvoiceEnum_countLinks' => 1,
        ];
    }


    public function selectInfiniteContract(Request $request)
    {
        $request['status'] = 1;
        $contract = $this->contractRepository->list($request->all());
        $dataContract = ContractSelectInfiniteResource::collection($contract);

        return [
            'code' => 200,
            'contract_arrayInfo' => $dataContract,
            'contract_countLinks' => $contract->lastPage(),
        ];
    }

    public function selectInfiniteSupportType(Request $request)
    {
        $request['status'] = 1;
        $supportType = $this->supportTypeRepository->list($request->all());
        $dataSupportType = SupportTypeSelectInfiniteResource::collection($supportType);

        return [
            'code' => 200,
            'supportType_arrayInfo' => $dataSupportType,
            'supportType_countLinks' => $supportType->lastPage(),
        ];
    }

    public function selectStatusFilingEnumOpenAndClosed(Request $request)
    {
        $status = StatusFilingEnum::cases();

        $status = array_filter($status, function ($case)  {
            return in_array($case->value, ["FILING_EST_008", "FILING_EST_009"]);
        });

        $status = collect($status)->map(function ($item) {
            return [
                "value" => $item,
                "title" => $item->description(),
            ];
        });

        return [
            'code' => 200,
            'statusFilingEnumOpenAndClosed_arrayInfo' => $status->values(),
            'statusFilingEnumOpenAndClosed_countLinks' => 1,
        ];
    }

    public function selectRoleTypeEnum(Request $request)
    {
        $types = RoleTypeEnum::cases();

        $types = collect($types)->map(function ($item) {
            return [
                "value" => $item,
                "title" => $item->description(),
            ];
        });

        return [
            'roleTypeEnum_arrayInfo' => $types->values(),
            'roleTypeEnum_countLinks' => 1,
        ];
    }

    public function selectInfiniteThird(Request $request)
    {
        $request['status'] = 1;
        $third = $this->thirdRepository->list($request->all());
        $dataThird = ThirdSelectInfiniteResource::collection($third);

        return [
            'code' => 200,
            'third_arrayInfo' => $dataThird,
            'third_countLinks' => $third->lastPage(),
        ];
    }
}
