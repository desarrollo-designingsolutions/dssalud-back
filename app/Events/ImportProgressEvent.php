<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ImportProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $batchId,
        public int $progress,
        public string $currentStudent,
        public string $currentAction,
        public array $metadata = []
    ) {
        $this->metadata = array_merge([
            'sheet' => 0,
            'chunk' => 0,
            'current_row' => 0,
            'total_rows' => 0,
            'subjects' => [],
            'total_records' => 0,
            'processed_records' => 0,
            'general_progress' => 0,
            'cancelled' => false,
            'connection_type' => 'websocket',
            'server_time' => now()->toDateTimeString(),
            'current_sheet' => 1,
            'total_sheets' => 1,
            'errors_count' => 0,
            'warnings_count' => 0,
            'file_size' => 0,
            'processing_start_time' => null,
            'last_activity' => now()->toDateTimeString(),
            'memory_usage' => 0,
            'cpu_usage' => 0,
            'connection_status' => 'connected',
            'processing_speed' => 0,
            'estimated_time_remaining' => 0,
            'total_errors' => 0, // Nuevo campo unificado
            'records_with_errors' => 0, // Nuevo campo unificado
        ], $metadata);

        $this->storeProgressData();
    }

    protected function storeProgressData(): void
    {
        $progressData = [
            'batch_id' => $this->batchId,
            'progress' => $this->progress,
            'current_student' => $this->currentStudent,
            'current_action' => $this->currentAction,
            'metadata' => $this->metadata,
            'timestamp' => now()->toDateTimeString()
        ];

        try {
            Cache::put(
                "import_progress_{$this->batchId}",
                $progressData,
                now()->addHours(2) // TTL de 2 horas
            );

        } catch (\Exception $e) {
            Log::error("Failed to store progress data: " . $e->getMessage());
        }
    }

    public function broadcastOn(): Channel
    {
        return new Channel('import.progress.' . $this->batchId);
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
            'current_student' => $this->currentStudent,
            'current_action' => $this->currentAction,
            'metadata' => [
                'sheet' => $this->metadata['sheet'],
                'chunk' => $this->metadata['chunk'],
                'processed_rows' => $this->metadata['current_row'],
                'total_rows' => $this->metadata['total_rows'],
                'subjects_processed' => count($this->metadata['subjects']),
                'total_records' => $this->metadata['total_records'],
                'processed_records' => $this->metadata['processed_records'],
                'general_progress' => $this->metadata['general_progress'],
                'cancelled' => $this->metadata['cancelled'] ?? false,
                'connection_type' => 'websocket',
                'server_time' => $this->metadata['server_time'],
                'current_sheet' => $this->metadata['current_sheet'],
                'total_sheets' => $this->metadata['total_sheets'],
                'errors_count' => $this->metadata['errors_count'],
                'warnings_count' => $this->metadata['warnings_count'],
                'total_errors' => $this->metadata['total_errors'] ?? 0,
                'records_with_errors' => $this->metadata['records_with_errors'] ?? 0,
                'file_size' => $this->metadata['file_size'],
                'processing_start_time' => $this->metadata['processing_start_time'],
                'last_activity' => $this->metadata['last_activity'],
                'memory_usage' => $this->metadata['memory_usage'],
                'cpu_usage' => $this->metadata['cpu_usage'],
                'connection_status' => $this->metadata['connection_status'],
                'processing_speed' => $this->metadata['processing_speed'],
                'estimated_time_remaining' => $this->metadata['estimated_time_remaining'],
            ],
            'timestamp' => now()->toDateTimeString()
        ];
    }

    public static function getProgressData(string $batchId): ?array
    {
        try {
            return Cache::get("import_progress_{$batchId}");
        } catch (\Exception $e) {
            Log::warning("Failed to get progress data: " . $e->getMessage());
            return null;
        }
    }
}
