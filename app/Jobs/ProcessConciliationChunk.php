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

        // Procesar y guardar en archivo temporal
        $rows = [];
        foreach ($data as $item) {
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
        }

        // Guardar chunk en archivo temporal
        $filePath = 'temp/exports/' . $this->tempFileName;
        $existingContent = Storage::disk(Constants::DISK_FILES)->exists($filePath) ? Storage::disk(Constants::DISK_FILES)->get($filePath) : '';

        $stream = fopen('php://temp', 'w+');
        if (!empty($existingContent)) {
            fwrite($stream, $existingContent);
        }

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        Storage::disk(Constants::DISK_FILES)->put($filePath, stream_get_contents($stream));
        fclose($stream);
    }
}
