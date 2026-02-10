<?php

namespace App\Http\Controllers;

use App\Repositories\FileViewerRepository;
use App\Traits\HttpResponseTrait;
use Illuminate\Http\Request;

class FileViewerController extends Controller
{
    use HttpResponseTrait;

    public function __construct(
        protected FileViewerRepository $fileViewerRepository,
    ) {}

    public function listfolders(Request $request)
    {
        return $this->execute(function () use ($request) {
            $folderPath = $request->input('path');
            $searchTerm = request()->query('search');

            if (! $folderPath) {
                return response()->json(['code' => 400, 'message' => 'Debe proporcionar una ruta'], 400);
            }

            $fullPath = public_path($folderPath);

            if (! is_dir($fullPath)) {
                return response()->json(['code' => 400, 'message' => 'Directorio no válido'], 400);
            }

            function scanDirectory($path, $relativePath, $searchTerm = null)
            {
                $result = [
                    'files' => [],
                    'folders' => [],
                    'matches' => [], // Array para guardar coincidencias
                ];
                $items = scandir($path);

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $itemPath = $path.DIRECTORY_SEPARATOR.$item;
                    $itemRelative = $relativePath.'/'.$item;

                    // Si hay término de búsqueda, verificar coincidencia
                    $matchesSearch = ! $searchTerm || stripos($item, $searchTerm) !== false;

                    if (is_dir($itemPath)) {
                        $folderData = [
                            'name' => $item,
                            'path' => $itemRelative,
                            'is_empty' => count(array_diff(scandir($itemPath), ['.', '..'])) === 0,
                        ];

                        // Recursión para subdirectorios
                        $subItems = scanDirectory($itemPath, $itemRelative, $searchTerm);
                        $folderData['contents'] = $subItems;

                        // Agregar coincidencias de subdirectorios
                        $result['matches'] = array_merge($result['matches'], $subItems['matches']);

                        // Si el nombre de la carpeta coincide o contiene coincidencias
                        if ($matchesSearch || ! empty($subItems['matches'])) {
                            $result['folders'][] = $folderData;
                        }
                    } else {
                        $fileData = [
                            'name' => $item,
                            'path' => $itemRelative,
                            'extension' => pathinfo($item, PATHINFO_EXTENSION),
                            'size' => filesize($itemPath),
                            'url' => asset($itemRelative),
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
                    'search_term' => $searchTerm,
                ],
            ];
        });
    }
}
