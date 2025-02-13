<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserStoreRequest;
use App\Http\Resources\User\UserFormResource;
use App\Http\Resources\User\UserListResource;
use App\Repositories\CompanyRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected QueryController $queryController,
        protected UserRepository $userRepository,
        protected RoleRepository $roleRepository,
        protected CompanyRepository $companyRepository,
    ) {
    }

    public function list(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->userRepository->list($request->all());
            $tableData = UserListResource::collection($data);

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

    public function create()
    {
        return $this->execute(function () {
            $roles = $this->roleRepository->selectList(request());
            $companies = $this->companyRepository->selectList();

            return [
                'code' => 200,
                'roles' => $roles,
                'companies' => $companies,
            ];
        });
    }

    public function store(UserStoreRequest $request)
    {
        return $this->runTransaction(function () use ($request) {
            $post = $request->except(['confirmedPassword']);

            $data = $this->userRepository->store($post, withCompany: false);

            $data->syncRoles($request->input("role_id"));

            return [
                'code' => 200,
                'message' => 'Usuario agregado correctamente'
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {
            $roles = $this->roleRepository->selectList(request());
            $companies = $this->companyRepository->selectList();

            $user = $this->userRepository->find($id);
            $form = new UserFormResource($user);

            return [
                'code' => 200,
                'form' => $form,
                'roles' => $roles,
                'companies' => $companies,
            ];
        });
    }

    public function update(UserStoreRequest $request, $id)
    {
        return $this->runTransaction(function () use ($request, $id) {
            $post = $request->except(['confirmedPassword']);

            $data = $this->userRepository->store($post, $id, withCompany: false);

            $data->syncRoles($request->input("role_id"));

            clearCacheLaravel();

            return [
                'code' => 200,
                'message' => 'Usuario modificado correctamente'
            ];
        });
    }

    public function delete($id)
    {
        return $this->runTransaction(function () use ($id) {
            $user = $this->userRepository->find($id);
            if ($user) {
                $user->delete();
                $msg = 'Registro eliminado correctamente';
            } else {
                $msg = 'El registro no existe';
            }

            return [
                'code' => 200,
                'message' => $msg
            ];
        });
    }

    public function changeStatus(Request $request)
    {
        return $this->runTransaction(function () use ($request) {
            $model = $this->userRepository->changeState($request->input('id'), strval($request->input('value')), $request->input('field'));

            ($model->is_active == 1) ? $msg = 'habilitada' : $msg = 'inhabilitada';

            return [
                'code' => 200,
                'message' => 'User ' . $msg . ' con éxito'
            ];
        });
    }

    public function changePassword(Request $request)
    {
        return $this->execute(function () use ($request) {
            // Obtener el usuario autenticado
            $user = $this->userRepository->find($request->input("id"));

            // Cambiar la contraseña
            $user->password = $request->input("new_password");
            $user->first_time = false;
            $user->save();

            return [
                "code" => 200,
                'message' => 'Contraseña modificada con éxito.'
            ];
        });
    }

    public function changePhoto(Request $request)
    {
        return $this->runTransaction(function () use ($request) {
            $user = $this->userRepository->find($request->input('user_id'));

            // Cambiar la photo
            if ($request->file('photo')) {
                $file = $request->file('photo');
                $ruta = 'companies/company_' . $user->company_id . '/' . $user->id . $request->input('photo');
                $photo = $file->store($ruta, 'public');
                $user->photo = $photo;
                $user->save();
            }

            return [
                'code' => 200,
                'message' => 'Foto modificada con éxito.',
                'photo' => $user->photo
            ];
        });
    }
}
