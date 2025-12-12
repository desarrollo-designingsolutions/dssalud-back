<?php

namespace App\Jobs\File;

use App\Events\FileUploadProgress;
use App\Events\ProgressCircular;
use App\Helpers\Constants;
use App\Repositories\FileRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class ProcessMassUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $tempPath;

    protected $fileName;

    protected $uploadId;

    protected $fileNumber;

    protected $totalFiles;

    protected $finalPath;

    protected $data;

    public function __construct($tempPath, $fileName, $uploadId, $fileNumber, $totalFiles, $finalPath, $data)
    {
        $this->tempPath = $tempPath;
        $this->fileName = $fileName;
        $this->uploadId = $uploadId;
        $this->fileNumber = $fileNumber;
        $this->totalFiles = $totalFiles;
        $this->finalPath = $finalPath;
        $this->data = $data;
    }

    public function handle(FileRepository $fileRepository)
    {
        // Usar la ruta relativa del disco, no la absoluta
        $disk = Constants::DISK_FILES;

        // Crear el directorio destino si no existe
        $directory = dirname($this->finalPath);
        if (! Storage::disk($disk)->exists($directory)) {
            Storage::disk($disk)->makeDirectory($directory);
        }

        // Mover el archivo usando rutas relativas
        $moved = Storage::disk($disk)->move($this->tempPath, $this->finalPath);

        if (! $moved) {
            logMessage("Error: No se pudo mover el archivo de {$this->tempPath} a {$this->finalPath}");
            throw new \Exception('No se pudo mover el archivo');
        }

        $fileRepository->store([
            'company_id' => $this->data['company_id'],
            'fileable_type' => $this->data['fileable_type'],
            'fileable_id' => $this->data['fileable_id'],
            'support_type_id' => $this->data['support_type_id'],
            'pathname' => $this->finalPath,
            'filename' => $this->fileName,
        ]);

        // Calcular progreso global basado en archivos procesados
        $progress = ($this->fileNumber / $this->totalFiles) * 100;

        FileUploadProgress::dispatch(
            $this->uploadId,
            $this->fileName,
            $this->fileNumber,
            $this->totalFiles,
            $progress,
            $this->finalPath
        );

        if (isset($this->data['channel'])) {
            ProgressCircular::dispatch($this->data['channel'], $progress);
        }
        // sleep(4);
    }
}
