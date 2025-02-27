<?php

namespace App\Events;

use App\Models\Rip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RipStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $rip;


    /**
     * Create a new event instance.
     */
    public function __construct($ripId)
    {
        $this->rip = Rip::find($ripId);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        // Define el canal que usará el evento para emitir
        return new Channel("rip.{$this->rip->id}");
    }

    public function broadcastAs()
    {
        return 'RipStatusUpdated'; // Nombre del evento que escuchará el frontend
    }

    public function broadcastWith()
    {
        // Aquí puedes incluir los datos que deseas enviar al frontend

        $path_json = $this->rip->path_json ?  env('SYSTEM_URL_BACK') . 'storage/' . $this->rip->path_json : null;
        $path_xls = $this->rip->path_xls ? env('SYSTEM_URL_BACK') . 'storage/' . $this->rip->path_xls : null;

        return [
            'id' => $this->rip->id,
            'numInvoices' => $this->rip->numInvoices,
            'sumVr' => $this->rip->sumVr,
            'send' => $this->rip->send,

            'status' => $this->rip->status,
            'status_description' => $this->rip->status->description(),
            'status_backgroundColor' => $this->rip->status->backgroundColor(),

            'created_at' => $this->rip->created_at->format('d-m-Y H:i'),
            'path_json' => $path_json,
            'path_xls' => $path_xls,
            'successfulInvoices' => $this->rip->successfulInvoices,
            'failedInvoices' => $this->rip->failedInvoices,

            'view_btn_error' => $this->rip->view_btn_error,
        ];
    }

}
