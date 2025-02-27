<?php

function validateDataFilesXml($dataXml, $jsonContents)
{
    $errorMessages = [];
    $arrayExito = [];

    $attachedDocument = $dataXml['AttachedDocument'];
    $validation = $attachedDocument['cac:ParentDocumentLineReference']['cac:DocumentReference']['cac:ResultOfVerification'];

    $arrayExito[] = validationResultCode($dataXml, $validation['cbc:ValidationResultCode'], $errorMessages);

    $numFac = $attachedDocument['cbc:ID'];
    $arrayExito[] = RVC004($jsonContents, $numFac, $errorMessages);

    $nit = $attachedDocument['cac:SenderParty']['cac:PartyTaxScheme']['cbc:CompanyID'];
    $arrayExito[] = RVC001($jsonContents, $nit, $errorMessages);

    return [
        'errorMessages' => $errorMessages,
        'totalErrorMessages' => count($errorMessages),
    ];
}

function validationResultCode($dataXml, $value2, &$errorMessages)
{

    $validation = true;

    if ($value2 != '02') {
        $errorMessages[] = [
            'validacion' => 'validationResultCode',
            'validacion_type_Y' => 'R',
            'num_invoice' => $dataXml['numFactura'],
            'file' => $dataXml['file_name'] ?? null,
            'row' => $dataXml['row'] ?? null,
            'column' => 'ValidationResultCode',
            'data' => $value2,
            'error' => 'el ValidationResultCode debe ser el numero 2.',
        ];

        $validation = false;
    }

    return [
        'validacion_type_Y' => 'R',
        'result' => $validation,
    ];
}
