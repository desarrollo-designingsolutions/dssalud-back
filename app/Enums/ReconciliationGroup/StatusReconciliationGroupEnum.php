<?php

namespace App\Enums\ReconciliationGroup;

use App\Attributes\Description;
use App\Traits\AttributableEnum;

enum StatusReconciliationGroupEnum: string
{
    use AttributableEnum;

    #[Description('Reprogramación')]
    case RECONCILIATION_GROUP_EST_001 = 'RECONCILIATION_GROUP_EST_001';

    #[Description('Acta en Firma')]
    case RECONCILIATION_GROUP_EST_002 = 'RECONCILIATION_GROUP_EST_002';

    #[Description('Inasistencia')]
    case RECONCILIATION_GROUP_EST_003 = 'RECONCILIATION_GROUP_EST_003';

    #[Description('Acta en elaboración')]
    case RECONCILIATION_GROUP_EST_004 = 'RECONCILIATION_GROUP_EST_004';

    #[Description('Acta Firmada')]
    case RECONCILIATION_GROUP_EST_005 = 'RECONCILIATION_GROUP_EST_005';

    #[Description('Mesa de Trabajo')]
    case RECONCILIATION_GROUP_EST_006 = 'RECONCILIATION_GROUP_EST_006';

    #[Description('Glosa notificada')]
    case RECONCILIATION_GROUP_EST_007 = 'RECONCILIATION_GROUP_EST_007';

    #[Description('Pendiente notificar falta correo')]
    case RECONCILIATION_GROUP_EST_008 = 'RECONCILIATION_GROUP_EST_008';

}
