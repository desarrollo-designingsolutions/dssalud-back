<?php

// Calcula si un rip manual tiene la data competa o no
// count_full  incrementa si:
// -un rip manual no tiene almenos una factura
// -un rip manual tiene facturas pero almenos una de ellas no tiene almenos un  usuario
// -un rip manual tiene facturas con usuarios pero al enos un usuario no tiene ningun   usuario
function evaluateCompleteManualRips($path, $type = 'all')
{
    $flag = [];
    $count_full = 0;
    $count_notFull = 0;

    $jsonContents = openFileJson($path);

    if (is_array($jsonContents) && count($jsonContents) > 0) {
        foreach ($jsonContents as $item) {
            if (! empty($item['usuarios'])) {
                $flag[] = true;
                foreach ($item['usuarios'] as $user) {
                    if (! empty($user['servicios'])) {
                        $flag[] = true;

                        $servicios = $user['servicios'];
                        if (
                            count($servicios['consultas']) > 0 ||
                            count($servicios['procedimientos']) > 0 ||
                            count($servicios['urgencias']) > 0 ||
                            count($servicios['hospitalizacion']) > 0 ||
                            count($servicios['recienNacidos']) > 0 ||
                            count($servicios['medicamentos']) > 0 ||
                            count($servicios['otrosServicios']) > 0
                        ) {
                            $flag[] = true;
                        } else {
                            $flag[] = false;
                        }
                    } else {
                        $flag[] = false;
                    }
                }
            } else {
                $flag[] = false;
            }

            if (in_array(false, $flag)) {
                $count_notFull++;
            } else {
                $count_full++;
            }
        }
    }

    return [
        'count_full' => $count_full,
        'count_notFull' => $count_notFull,
    ];
}
