<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ImportProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $batchId;

    public float $progress;

    public string $currentElement;

    public string $currentAction;

    public string $status;

    public array $metadata;

    public function __construct(
        string $batchId,
        string $processedRecords,
        string $currentAction,
        string $errorCount,
        string $backendStatus,
        string $currentElement
    ) {
        $this->batchId = $batchId;
        $this->currentAction = $currentAction;
        $this->currentElement = (string) $currentElement;

        $staticMetadata = Redis::connection('redis_6380')->hgetall("batch:{$this->batchId}:metadata");

        $totalRecords = (int) ($staticMetadata['total_rows'] ?? 0);
        $started_at = $staticMetadata['started_at'] ?? 'N/A';

        $this->progress = $totalRecords > 0 ? round(((int) $processedRecords / $totalRecords) * 100, 2) : 0;
        $this->status = $this->mapStatus($backendStatus);

        $this->metadata = [
            'total_records' => $totalRecords,
            'processed_records' => (int) $processedRecords,
            'errors_count' => (int) $errorCount,
            'last_activity' => now()->toDateTimeString(),
            'started_at' => $started_at,
            'completed_at' => $staticMetadata['completed_at'] ?? 'N/A',
            'file_name' => $staticMetadata['file_name'] ?? 'N/A',
            'file_size' => (int) ($staticMetadata['file_size'] ?? 0),
            'current_sheet' => (int) ($staticMetadata['current_sheet'] ?? 1),
            'total_sheets' => (int) ($staticMetadata['total_sheets'] ?? 1),
            'connection_status' => 'connected',
        ];

        // CORRECCIÓN: Solo intentar parsear si started_at no es 'N/A'
        if ($started_at !== 'N/A') {
            try {
                $startTime = Carbon::parse($started_at);
                $elapsedSeconds = max(1, abs(Carbon::now()->diffInSeconds($startTime, false)));

                if ((int) $processedRecords > 0) {
                    $processingSpeed = round((int) $processedRecords / $elapsedSeconds, 2);
                    $this->metadata['processing_speed'] = $processingSpeed;
                    $remainingRecords = $totalRecords - (int) $processedRecords;
                    if ($processingSpeed > 0 && $remainingRecords > 0) {
                        $estimatedTimeRemaining = round($remainingRecords / $processingSpeed);
                        $this->metadata['estimated_time_remaining'] = $estimatedTimeRemaining;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error calculando métricas de progreso: '.$e->getMessage());
            }
        }
    }

    protected function mapStatus(string $backendStatus): string
    {
        return match ($backendStatus) {
            'active', 'finalizing' => 'active',
            'queued' => 'queued',
            'completed', 'completed_with_errors' => 'completed',
            'failed' => 'error',
            default => 'active',
        };
    }

    public function broadcastOn(): Channel
    {
        return new Channel('import.progress.'.$this->batchId);
    }

    public function broadcastAs(): string
    {
        return 'progress.update';
    }

    public function broadcastWith(): array
    {
        return [
            'batch_id' => $this->batchId,
            'progress' => $this->progress,
            'current_element' => $this->currentElement,
            'current_action' => $this->currentAction,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
