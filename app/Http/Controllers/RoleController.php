<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\RoleStoreRequest;
use App\Http\Resources\Role\MenuCheckBoxResource;
use App\Http\Resources\Role\RoleFormResource;
use App\Http\Resources\Role\RoleListResource;
use App\Models\Role;
use App\Repositories\MenuRepository;
use App\Repositories\RoleRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected RoleRepository $roleRepository,
        protected MenuRepository $menuRepository,
        protected QueryController $queryController,
    ) {}

    public function index(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->roleRepository->list([
                ...['typeData' => 'all'],
                ...$request->all(),
            ]);

            $tableData = RoleListResource::collection($data);

            return [
                'code' => 200,
                'tableData' => $tableData,
            ];
        });
    }

    public function create()
    {
        return $this->execute(function () {
            $menus = $this->menuRepository->list([
                'father_null' => true,
                'withPermissions' => true,
            ], ['children']);

            $menus = MenuCheckBoxResource::collection($menus);

            $roleTypes = $this->queryController->selectRoleTypeEnum(request());

            return [
                'menus' => $menus,
                ...$roleTypes,
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {
            $role = $this->roleRepository->find($id);

            $menus = $this->menuRepository->list([
                'typeData' => 'all',
                'father_null' => true,
                'withPermissions' => true,
            ], ['children']);

            $menus = MenuCheckBoxResource::collection($menus);

            $roleTypes = $this->queryController->selectRoleTypeEnum(request());

            return [
                'code' => 200,
                'role' => new RoleFormResource($role),
                'menus' => $menus,
                ...$roleTypes,
            ];
        });
    }

    public function store(RoleStoreRequest $request)
    {
        $transaction = $this->runTransaction(function () use ($request) {

            $post = $request->except(['permissions', 'type']);

            $types = $request->input('type');

            $post['type'] = implode(',', $types);

            do {
                $nameRole = Str::random(10); // Genera un string aleatorio de 10 caracteres
            } while (Role::where('name', $nameRole)->exists()); // Verifica si ya existe en la base de datos

            $post['name'] = $nameRole;

            $data = $this->roleRepository->store($post);

            $permissions = [
                ...$request['permissions'],
                ...[1],
            ];

            $data->permissions()->sync($permissions);

            $msg = 'agregado';
            if (! empty($request['id'])) {
                $msg = 'modificado';
            }

            return [
                'code' => 200,
                'message' => 'Registro '.$msg.' correctamente',
                'data' => $data,
            ];
        });

        clearCacheLaravel();

        return $transaction;
    }

    public function destroy($id)
    {
        return $this->runTransaction(function () use ($id) {
            $data = $this->roleRepository->find($id);
            if ($data) {
                $data->delete();
                $msg = 'Registro eliminado correctamente';
            } else {
                $msg = 'El registro no existe';
            }

            return [
                'code' => 200,
                'message' => $msg,
            ];
        });
    }
}
