<?php

namespace App\Jobs\File;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use App\Events\FileUploadProgress;
use App\Events\ProgressCircular;
use App\Repositories\FileRepository;

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
        // Mover el archivo
        Storage::disk('public')->move($this->tempPath, $this->finalPath);


        $fileRepository->store([
            "company_id" => $this->data["company_id"],
            "fileable_type" => $this->data["fileable_type"],
            "fileable_id" => $this->data["fileable_id"],
            "support_type_id" => $this->data["support_type_id"],
            "pathname" => $this->finalPath,
            "filename" => $this->fileName,
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

        if(isset($this->data["channel"])){
            ProgressCircular::dispatch($this->data["channel"], $progress);
        }
        sleep(4);
    }
}
