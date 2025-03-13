<?php

namespace App\Events;

use App\Models\FilingInvoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FilingInvoiceRowUpdatedNow implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $filingInvoice;

    /**
     * Create a new event instance.
     */
    public function __construct($filing_invoice_id)
    {
        $this->filingInvoice = FilingInvoice::find($filing_invoice_id);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        // Define el canal que usará el evento para emitir
        return new Channel("filing_invoice.{$this->filingInvoice->id}");
    }

    public function broadcastAs()
    {
        return 'FilingInvoiceRowUpdated'; // Nombre del evento que escuchará el frontend
    }

    public function broadcastWith()
    {
        // Aquí puedes incluir los datos que deseas enviar al frontend

        return [
            'id' => $this->filingInvoice->id,
            'files_count' => $this->filingInvoice->files_count,

            'status_xml' => $this->filingInvoice->status_xml,
            'status_xml_backgroundColor' => $this->filingInvoice->status_xml->BackgroundColor(),
            'status_xml_description' => $this->filingInvoice->status_xml->description(),

            'path_xml' => $this->filingInvoice->path_xml,
        ];
    }
}
