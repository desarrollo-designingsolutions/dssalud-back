<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeInvoiceAuditData implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $invoice_audit_id;

    public $data;

    public function __construct($invoice_audit_id, $data)
    {
        $this->invoice_audit_id = $invoice_audit_id;
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new channel("invoice_audit.{$this->invoice_audit_id}");
    }

    public function broadcastWith()
    {
        return [
            'invoice_audit_id' => $this->invoice_audit_id,
            'data' => $this->data,
        ];
    }
}
