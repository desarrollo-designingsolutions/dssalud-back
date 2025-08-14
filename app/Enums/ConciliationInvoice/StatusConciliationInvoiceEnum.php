<?php

namespace App\Enums\ConciliationInvoice;

use App\Attributes\BackgroundColor;
use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusConciliationInvoiceEnum: string
{
    use AttributableEnum;

    #[Description('Pendiente')]
    #[BackgroundColor('warning')]
    case CONCILIATION_INVOICE_EST_001 = 'CONCILIATION_INVOICE_EST_001';

    #[Description('Finalizado')]
    #[BackgroundColor('success')]
    case CONCILIATION_INVOICE_EST_002 = 'CONCILIATION_INVOICE_EST_002';
}
