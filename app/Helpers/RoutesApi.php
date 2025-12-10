<?php

namespace App\Helpers;

class RoutesApi
{
    // esto es para las apis que no requieran auth
    public const ROUTES_API = [
        'routes/api.php',
    ];

    // esto es para las apis que si requieran auth
    public const ROUTES_AUTH_API = [
        'routes/query.php',
        'routes/company.php',
        'routes/user.php',
        'routes/role.php',
        'routes/audit.php',
        'routes/file.php',
        'routes/notification.php',
        'routes/rip.php',
        'routes/filing.php',
        'routes/filingInvoice.php',
        'routes/fileViewer.php',
        'routes/invoiceAudit.php',
        'routes/assignmentBatche.php',
        'routes/assignment.php',
        'routes/glosa.php',
        'routes/schedule.php',
        'routes/reconciliationGroup.php',
        'routes/conciliation.php',
        'routes/processBatch.php',
        'routes/contract.php',
    ];
}
