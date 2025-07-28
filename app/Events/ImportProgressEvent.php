<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

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
        // ✅ ESTRUCTURA COMPLETA CON TODOS LOS DATOS NECESARIOS
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
            // ✅ NUEVOS DATOS PARA METADATA COMPLETA
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
            // ✅ CAMPOS CALCULADOS PARA TIEMPO ESTIMADO
            'processing_speed' => 0,
            'estimated_time_remaining' => 0,
        ], $metadata);

        // Guardar en cache y Redis
        $this->storeProgressData();
        
        // // Log::debug("🔌 [WEBSOCKET] Event created for batch {$this->batchId} with progress {$this->metadata['general_progress']}% | ETA: {$this->metadata['estimated_time_remaining']}s");
    }

    protected function storeProgressData()
    {
        $progressData = [
            'batch_id' => $this->batchId,
            'progress' => $this->progress,
            'current_student' => $this->currentStudent,
            'current_action' => $this->currentAction,
            'metadata' => $this->metadata,
            'timestamp' => now()->toDateTimeString()
        ];

        // Guardar en cache (fallback)
        Cache::put("batch_progress_{$this->batchId}", $progressData, now()->addHours(2));

        // Guardar en Redis (principal)
        try {
            Redis::setex(
                "websocket_progress_{$this->batchId}",
                7200, // 2 horas
                json_encode($progressData)
            );
            // Log::debug("📦 [REDIS] Progress stored for batch {$this->batchId}");
        } catch (\Exception $e) {
            // Log::warning("⚠️ [REDIS] Failed to store progress: " . $e->getMessage());
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

    public function broadcastWith()
    {
        // 🔥 LOG SÚPER VISIBLE EN PHP
        // Log::info("🔥🔥🔥 [PHP-WEBSOCKET] EMITIENDO EVENTO PARA BATCH: {$this->batchId}");
        // Log::info("📊 [PHP-WEBSOCKET] PORCENTAJE: {$this->metadata['general_progress']}%");
        // Log::info("👤 [PHP-WEBSOCKET] ESTUDIANTE: {$this->currentStudent}");
        // Log::info("⚡ [PHP-WEBSOCKET] ACCIÓN: {$this->currentAction}");
        // Log::info("⏱️ [PHP-WEBSOCKET] TIEMPO ESTIMADO: {$this->metadata['estimated_time_remaining']}s");
        // Log::info("🚀 [PHP-WEBSOCKET] VELOCIDAD: {$this->metadata['processing_speed']} reg/s");

        $broadcastData = [
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
                // ✅ NUEVOS DATOS PARA EL FRONTEND
                'current_sheet' => $this->metadata['current_sheet'],
                'total_sheets' => $this->metadata['total_sheets'],
                'errors_count' => $this->metadata['errors_count'],
                'warnings_count' => $this->metadata['warnings_count'],
                'file_size' => $this->metadata['file_size'],
                'processing_start_time' => $this->metadata['processing_start_time'],
                'last_activity' => $this->metadata['last_activity'],
                'memory_usage' => $this->metadata['memory_usage'],
                'cpu_usage' => $this->metadata['cpu_usage'],
                'connection_status' => $this->metadata['connection_status'],
                // ✅ CAMPOS CALCULADOS PARA TIEMPO ESTIMADO
                'processing_speed' => $this->metadata['processing_speed'],
                'estimated_time_remaining' => $this->metadata['estimated_time_remaining'],
            ],
            'timestamp' => now()->toDateTimeString()
        ];

        // Log::info("📡 [PHP-WEBSOCKET] DATOS COMPLETOS A ENVIAR:", $broadcastData);
        return $broadcastData;
    }

    /**
     * Obtener datos desde Redis
     */
    public static function getProgressFromRedis(string $batchId): ?array
    {
        try {
            $data = Redis::get("websocket_progress_{$batchId}");
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            // Log::warning("⚠️ [REDIS] Failed to get progress: " . $e->getMessage());
            return null;
        }
    }
}