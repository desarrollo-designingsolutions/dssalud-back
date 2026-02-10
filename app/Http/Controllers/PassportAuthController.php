<?php

namespace App\Http\Controllers;

use App\Helpers\Constants;
use App\Http\Requests\Authentication\PassportAuthLoginRequest;
use App\Http\Requests\Authentication\PassportAuthPasswordResetLinkRequest;
use App\Http\Requests\Authentication\PassportAuthSendResetLinkRequest;
use App\Jobs\BrevoProcessSendEmail;
use App\Models\Role;
use App\Models\User;
use App\Repositories\MenuRepository;
use App\Repositories\UserRepository;
use App\Services\MailService;
use App\Traits\HttpResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

class PassportAuthController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected UserRepository $userRepository,
        protected MenuRepository $menuRepository,
        protected MailService $mailService
    ) {}

    public function register(Request $request)
    {
        return $this->runTransaction(function () use ($request) {
            $user = $this->userRepository->store($request->all(), withCompany: false);

            $role = Role::find($user->role_id);
            if ($role) {
                $user->syncRoles($role);
            }

            $accessToken = $user->createToken('authToken')->accessToken;

            return [
                'code' => 200,
                'user' => $user,
                'access_token' => $accessToken,
            ];
        });
    }

    public function login(PassportAuthLoginRequest $request)
    {
        return $this->execute(function () use ($request) {

            $data = [
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ];

            Auth::attempt($data);

            if (! Auth::attempt($data)) {
                // Si las credenciales son incorrectas, lanzar una excepción
                throw new \Exception(json_encode([
                    'message' => 'Credenciales incorrectas',
                ]));
            }

            $user = Auth::user();
            if ($user->company) {
                if (! $user->company?->is_active) {
                    return response()->json([
                        'code' => '401',
                        'error' => 'Not authorized',
                        'message' => 'La empresa a la cual usted pertenece se encuentra inactiva',
                    ], 401);
                }
                if (! $user->is_active) {
                    return response()->json([
                        'code' => '401',
                        'error' => 'Not authorized',
                        'message' => 'El usuario se encuentra inactivo',
                    ], 401);
                }
                if (! empty($user->company->final_date)) {
                    $now = Carbon::now()->format('Y-m-d');
                    $compareDate = Carbon::parse($user->company->final_date)->format('Y-m-d');
                    if ($now >= $compareDate) {
                        return response()->json([
                            'code' => '401',
                            'error' => 'Not authorized',
                            'message' => 'La suscripción de la empresa a la cual usted pertenece, ha caducado',
                        ], 401);
                    }
                }
            }

            $obj['id'] = $user->id;
            $obj['full_name'] = $user->full_name;
            $obj['name'] = $user->name;
            $obj['surname'] = $user->surname;
            $obj['email'] = $user->email;
            $obj['rol_name'] = $user->role?->description;
            $obj['role_id'] = $user->role_id;
            $obj['company_id'] = $user->company_id;
            $obj['third'] = [
                'id' => $user->third?->id,
                'nit' => $user->third?->nit,
                'name' => $user->third?->name,
            ];
            $obj['photo'] = $user->photo;
            $obj['first_time'] = $user->first_time;

            $company = $user->company;

            $photo = null;
            if ($user->company?->logo && Storage::disk(Constants::DISK_FILES)->exists($user->company->logo)) {
                $photo = $user->company->logo;
            }

            $company['logo'] = $photo;
            $permisos = $user->getAllPermissions();
            if (count($permisos) > 0) {
                $menu = $this->menuRepository->list([
                    'typeData' => 'all',
                    'father_null' => 1,
                    'permissions' => $permisos->pluck('name'),
                ], [
                    'children' => function ($query) use ($permisos) {
                        $query->whereHas('permissions', function ($x) use ($permisos) {
                            $x->whereIn('name', $permisos->pluck('name'));
                        });
                    },
                    'children.children',
                ]);

                foreach ($menu as $key => $value) {
                    $arrayMenu[$key]['title'] = $value->title;
                    $arrayMenu[$key]['to']['name'] = $value->to;
                    $arrayMenu[$key]['icon']['icon'] = $value->icon ?? 'mdi-arrow-right-thin-circle-outline';

                    if (! empty($value['children'])) {
                        foreach ($value['children'] as $key2 => $value2) {
                            $arrayMenu[$key]['children'][$key2]['title'] = $value2->title;
                            $arrayMenu[$key]['children'][$key2]['to'] = $value2->to;
                            // $arrayMenu[$key]["children"][$key2]["icon"]["icon"] = $value2->icon ?? "mdi-arrow-right-thin-circle-outline";
                            if (! empty($value2['children'])) {
                                foreach ($value2['children'] as $key3 => $value3) {
                                    if (in_array($value3->requiredPermission, $permisos->pluck('name')->toArray())) {
                                        $arrayMenu[$key]['children'][$key2]['children'][$key3]['title'] = $value3->title;
                                        $arrayMenu[$key]['children'][$key2]['children'][$key3]['to'] = $value3->to;
                                        // $arrayMenu[$key]["children"][$key2]["icon"]["icon"] = $value2->icon ?? "mdi-arrow-right-thin-circle-outline";
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $access_token = $user->createToken('authToken');

            return [
                'code' => 200,
                'access_token' => $access_token->accessToken,
                'expires_at' => Carbon::parse($access_token->token->expires_at)->toDateTimeString(),
                'user' => $obj,
                'company' => $company,
                'permissions' => $permisos->pluck('name'),
                'menu' => $arrayMenu ?? [],
                'message' => 'Bienvenido',
            ];
        });
    }

    public function userInfo()
    {
        return $this->execute(function () {
            $user = Auth::user();

            return [
                'user' => $user,
            ];
        });
    }

    public function sendResetLink(PassportAuthSendResetLinkRequest $request)
    {
        return $this->execute(function () use ($request) {
            $user = $this->userRepository->findByEmail($request->input('email'));

            // Generar el enlace de restablecimiento
            $token = Password::getRepository()->create($user);

            $action_url = env('SYSTEM_URL_FRONT').'ResetPassword/'.$token.'?email='.urlencode($request->input('email'));

            // Enviar el correo usando el job de Brevo
            BrevoProcessSendEmail::dispatch(
                emailTo: [
                    [
                        'name' => $user->full_name,
                        'email' => $request->input('email'),
                    ],
                ],
                subject: 'Link Restablecer Contraseña',
                templateId: 3,  // El ID de la plantilla de Brevo que quieres usar
                params: [
                    'full_name' => $user->full_name,
                    'bussines_name' => $user->company?->name,
                    'action_url' => $action_url,

                ],  // Aquí pasas los parámetros para la plantilla, por ejemplo, el texto del mensaje
            );

            return [
                'code' => 200,
                'message' => 'Te hemos enviado por correo electrónico el enlace para restablecer tu contraseña.',
            ];
        });
    }

    public function passwordReset(PassportAuthPasswordResetLinkRequest $request)
    {
        return $this->execute(function () use ($request) {

            $response = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    // Actualizar la contraseña del usuario
                    $user->password = $password;
                    $user->save();
                }
            );

            if ($response == Password::PASSWORD_RESET) {
                return [
                    'code' => 200,
                    'message' => 'La contraseña ha sido cambiada correctamente.',
                ];
            }

            throw new \Exception(json_encode([
                'message' => 'El token de restablecimiento es inválido o ha expirado.',
            ]));
        });
    }
}
