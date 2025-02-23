<?php

namespace App\Http\Controllers;

use App\Enums\Filing\StatusFillingInvoiceEnum;
use App\Http\Resources\Contract\ContractSelectInfiniteResource;
use App\Http\Resources\Country\CountrySelectResource;
use App\Http\Resources\SupportType\SupportTypeSelectInfiniteResource;
use App\Repositories\CityRepository;
use App\Repositories\ContractRepository;
use App\Repositories\CountryRepository;
use App\Repositories\StateRepository;
use App\Repositories\SupportTypeRepository;
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

    public function selectStatusFillingInvoiceEnum(Request $request)
    {
        $status = StatusFillingInvoiceEnum::cases();

        $status = collect($status)->map(function ($item) {
            return [
                "value" => $item,
                "title" => $item->description(),
            ];
        });

        return [
            'code' => 200,
            'statusFillingInvoiceEnum_arrayInfo' => $status->values(),
            'statusFillingInvoiceEnum_countLinks' => 1,
        ];
    }
    public function selectStatusXmlFillingInvoiceEnum(Request $request)
    {
        $status = StatusFillingInvoiceEnum::cases();


        $status = array_filter($status, function ($case)  {
            return in_array($case->value, ["VALIDATED", "NOT_VALIDATED"]);
        });



        $status = collect($status)->map(function ($item) {
            return [
                "value" => $item,
                "title" => $item->description(),
            ];
        });

        return [
            'code' => 200,
            'statusXmlFillingInvoiceEnum_arrayInfo' => $status->values(),
            'statusXmlFillingInvoiceEnum_countLinks' => 1,
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
}
