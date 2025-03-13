<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileUploadProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets,SerializesModels;

    public $uploadId;

    public $fileName;

    public $fileNumber;

    public $totalFiles;

    public $progress;

    public $filePath;

    public function __construct($uploadId, $fileName, $fileNumber, $totalFiles, $progress, $filePath)
    {
        $this->uploadId = $uploadId;
        $this->fileName = $fileName;
        $this->fileNumber = $fileNumber;
        $this->totalFiles = $totalFiles;
        $this->progress = $progress;
        $this->filePath = $filePath;
    }

    public function broadcastOn()
    {
        return new Channel('upload-progress.'.$this->uploadId);
    }

    public function broadcastAs()
    {
        return 'file-progress';
    }
}
