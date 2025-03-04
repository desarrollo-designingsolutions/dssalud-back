<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\RoleStoreRequest;
use App\Http\Resources\Role\MenuCheckBoxResource;
use App\Models\Role;
use App\Repositories\FileViewerRepository;
use App\Traits\HttpTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileViewerController extends Controller
{
    use HttpTrait;

    public function __construct(
        protected FileViewerRepository $fileViewerRepository,
    ) {}

    public function list(Request $request)
    {
        return $this->execute(function () use ($request) {
            $data = $this->fileViewerRepository->list($request->all());
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
            $menus = $this->menuRepository->list([
                'father_null' => true,
                'withPermissions' => true,
            ], ['children']);

            $menus = MenuCheckBoxResource::collection($menus);

            return [
                'menus' => $menus,
            ];
        });
    }

    public function edit($id)
    {
        return $this->execute(function () use ($id) {
            $role = $this->roleRepository->find($id);

            $menus = $this->menuRepository->list([
                'typeData' => "all",
                'father_null' => true,
                'withPermissions' => true,
            ], ['children']);

            $menus = MenuCheckBoxResource::collection($menus);

            return [
                'code' => 200,
                'role' => new RoleFormResource($role),
                'menus' => $menus,
            ];
        });
    }

    public function store(RoleStoreRequest $request)
    {
        $transaction = $this->runTransaction(function () use ($request) {

            $post = $request->except(['permissions']);

            do {
                $nameRole = Str::random(10); // Genera un string aleatorio de 10 caracteres
            } while (Role::where('name', $nameRole)->exists()); // Verifica si ya existe en la base de datos

            $post["name"] = $nameRole;

            $data = $this->roleRepository->store($post);

            $permissions = [
                ...$request['permissions'],
                ...[1],
            ];

            $data->permissions()->sync($permissions);

            $msg = 'agregado';
            if (!empty($request['id'])) {
                $msg = 'modificado';
            }

            return [
                'code' => 200,
                'message' => 'Registro ' . $msg . ' correctamente',
                'data' => $data
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
                'message' => $msg
            ];
        });
    }

    public function listfolders(Request $request)
    {
        return $this->execute(function () use ($request) {
            $folderPath = $request->input('path');
            $searchTerm = request()->query('search'); 

            if (!$folderPath) {
                return response()->json(['code' => 400, 'message' => 'Debe proporcionar una ruta'], 400);
            }

            $fullPath = public_path($folderPath);

            if (!is_dir($fullPath)) {
                return response()->json(['code' => 400, 'message' => 'Directorio no válido'], 400);
            }

            function scanDirectory($path, $relativePath, $searchTerm = null)
            {
                $result = [
                    'files' => [],
                    'folders' => [],
                    'matches' => [] // Array para guardar coincidencias
                ];
                $items = scandir($path);

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;

                    $itemPath = $path . DIRECTORY_SEPARATOR . $item;
                    $itemRelative = $relativePath . '/' . $item;

                    // Si hay término de búsqueda, verificar coincidencia
                    $matchesSearch = !$searchTerm || stripos($item, $searchTerm) !== false;

                    if (is_dir($itemPath)) {
                        $folderData = [
                            'name' => $item,
                            'path' => $itemRelative,
                            'is_empty' => count(array_diff(scandir($itemPath), ['.', '..'])) === 0
                        ];

                        // Recursión para subdirectorios
                        $subItems = scanDirectory($itemPath, $itemRelative, $searchTerm);
                        $folderData['contents'] = $subItems;

                        // Agregar coincidencias de subdirectorios
                        $result['matches'] = array_merge($result['matches'], $subItems['matches']);

                        // Si el nombre de la carpeta coincide o contiene coincidencias
                        if ($matchesSearch || !empty($subItems['matches'])) {
                            $result['folders'][] = $folderData;
                        }
                    } else {
                        $fileData = [
                            'name' => $item,
                            'path' => $itemRelative,
                            'extension' => pathinfo($item, PATHINFO_EXTENSION),
                            'size' => filesize($itemPath),
                            'url' => asset($itemRelative)
                        ];

                        // Si hay término de búsqueda y coincide, agregar a matches
                        if ($matchesSearch) {
                            $result['matches'][] = $fileData;
                        }
                        $result['files'][] = $fileData;
                    }
                }
                return $result;
            }

            $data = scanDirectory($fullPath, $folderPath, $searchTerm);

            return [
                'code' => 200,
                'data' => [
                    'path' => $folderPath,
                    'parent' => dirname($folderPath) === '.' ? '' : dirname($folderPath),
                    'contents' => $data,
                    'search_results' => $data['matches'], // Resultados de búsqueda específicos
                    'search_term' => $searchTerm
                ]
            ];
        });
    }
}
