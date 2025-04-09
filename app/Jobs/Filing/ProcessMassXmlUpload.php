<?php

namespace App\Jobs\Filing;

use App\Enums\Filing\StatusFilingInvoiceEnum;
use App\Events\FileUploadProgress;
use App\Events\FilingInvoiceRowUpdatedNow;
use App\Events\ProgressCircular;
use App\Helpers\Constants;
use App\Repositories\FilingInvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class ProcessMassXmlUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(FilingInvoiceRepository $filingInvoiceRepository)
    {
        // Construcción dinámica del finalPath con el nombre del archivo
        $separatedName = explode('_', $this->data['originalName']);
        [$nit, $numFac, $name] = $separatedName;

        $filing_invoice = $filingInvoiceRepository->searchOne([
            'invoice_number' => $numFac,
            'filing_id' => $this->data['filing_id'],
        ]);

        logMessage('juan33333');
        logMessage($filing_invoice);

        $jsonContents = openFileJson($filing_invoice->path_json);

        $data = [
            'numInvoice' => $filing_invoice->invoice_number,
            'file_name' => $this->data['originalName'],
            'jsonContents' => $jsonContents,
        ];

        // Validar datos del XML
        $infoValidation = validateDataFilesXml($this->data['tempPath'], $data);

        logMessage($infoValidation);

        // Determinar el estado y la ruta del archivo XML
        $finalName = "{$this->data['originalName']}";
        $finalPath = "companies/company_{$this->data['company_id']}/filings/{$filing_invoice->filing->type->value}/filing_{$filing_invoice->filing->id}/invoices/{$filing_invoice->invoice_number}/xml/{$finalName}";
        if ($infoValidation['totalErrorMessages'] == 0) {
            logMessage('1');
            // Mover el archivo
            // Storage::disk(Constants::DISK_FILES)->move($this->data['tempPath'], $finalPath);

            $filing_invoice->path_xml = $finalPath;
            $filing_invoice->status_xml = StatusFilingInvoiceEnum::FILINGINVOICE_EST_003;
            $filing_invoice->validationXml = null;

        } else {
            logMessage('2');
            $filing_invoice->status_xml = StatusFilingInvoiceEnum::FILINGINVOICE_EST_005;
            $filing_invoice->validationXml = json_encode($infoValidation['errorMessages']);
        }

        logMessage('3');
        $filing_invoice->save();

        logMessage('juan');
        logMessage($filing_invoice);

        FilingInvoiceRowUpdatedNow::dispatch($filing_invoice->id);

        // Calcular progreso global basado en archivos procesados
        $progress = ($this->data['fileNumber'] / $this->data['totalFiles']) * 100;

        FileUploadProgress::dispatch(
            $this->data['uploadId'],
            $finalName,
            $this->data['fileNumber'],
            $this->data['totalFiles'],
            $progress,
            $finalPath
        );

        if (isset($this->data['channel'])) {
            ProgressCircular::dispatch($this->data['channel'], $progress);
        }
        // sleep(3);
    }
}
