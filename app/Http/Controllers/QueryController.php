<?php

namespace App\Http\Controllers;

use App\Http\Resources\Country\CountrySelectResource;
use App\Repositories\CityRepository;
use App\Repositories\CountryRepository;
use App\Repositories\StateRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class QueryController extends Controller
{
    public function __construct(
        protected CountryRepository $countryRepository,
        protected StateRepository $stateRepository,
        protected CityRepository $cityRepository,
        protected UserRepository $userRepository,
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

    // public function selectInifiniteInsurance(Request $request)
    // {
    //     $request['status'] = 1;
    //     $insurance = $this->insuranceRepository->list($request->all());
    //     $dataInsurance = SelectInsuranceResource::collection($insurance);

    //     return [
    //         'code' => 200,
    //         'insurance_arrayInfo' => $dataInsurance,
    //         'insurance_countLinks' => $insurance->lastPage(),
    //     ];
    // }

}
