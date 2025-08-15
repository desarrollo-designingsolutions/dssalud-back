<?php
// app/Jobs/ProcessConciliationChunk.php
namespace App\Jobs;

use App\Helpers\Constants;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Repositories\ReconciliationGroupInvoiceRepository;
use Illuminate\Support\Facades\Log;

class ProcessConciliationChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;
    protected $offset;
    protected $limit;
    protected $tempFileName;

    public function __construct($request, $offset, $limit, $tempFileName)
    {
        $this->request = $request;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->tempFileName = $tempFileName;
    }

    public function handle(ReconciliationGroupInvoiceRepository $repository)
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        // Obtener los datos del chunk actual
        $request = $this->request;
        $request['offset'] = $this->offset;
        $request['limit'] = $this->limit;

        // Log::info("request",[$request]);


        $data = $repository->getConciliationInvoicesChunk($request);

        // Log::info("data",[$data]);

                // Log::info("data buscada");
        // Procesar y guardar en archivo temporal
        $rows = [];
                    foreach ($data as $key=> $item) {
            $rows[] = [
                $item->invoiceAudit?->invoice_number,
                formatNumber($item->invoiceAudit?->total_value),
                $item->invoiceAudit?->origin,
                $item->invoiceAudit?->modality,
                $item->invoiceAudit?->contract_number,
                "hola",
                formatNumber($item->sum_accepted_value_ips),
                formatNumber($item->sum_accepted_value_eps),
                formatNumber($item->sum_eps_ratified_value),
            ];
                    // Log::info("cada chunk procesado {$key} de {$this->limit} registros");
        }

        // Guardar chunk en archivo temporal
        $filePath = 'temp/exports/' . $this->tempFileName;
        $existingContent = Storage::disk(Constants::DISK_FILES)->exists($filePath) ? Storage::disk(Constants::DISK_FILES)->get($filePath) : '';

                    // Log::info("Contenido existente en el archivo temporal: " . strlen($existingContent) . " bytes");


        try {
        $stream = fopen('php://temp', 'w+');

        if (Storage::disk(Constants::DISK_FILES)->exists($filePath)) {
            fwrite($stream, Storage::disk(Constants::DISK_FILES)->get($filePath));
        }

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        Storage::disk(Constants::DISK_FILES)->put($filePath, stream_get_contents($stream));
    } catch (\Exception $e) {
        // Log::error("Error al guardar el chunk en el archivo temporal: " . $e->getMessage());
        if (is_resource($stream)) {
            fclose($stream); // Asegura el cierre del recurso
        }
    }finally {
        if (is_resource($stream)) {
            fclose($stream); // Asegura el cierre del recurso
        }
    }

    // Liberar memoria explícitamente
    unset($rows, $data, $stream);
        Storage::disk(Constants::DISK_FILES)->put($filePath, stream_get_contents($stream));
        fclose($stream);
    }
}
