<?php

use Saloon\XmlWrangler\XmlReader;

function validateDataFilesXml($archivo, $data)
{
    $errorMessages = [];
    $arrayExito = [];

    $arrayExito[] = validationFileXML($archivo, $data, $errorMessages);
    if ($arrayExito[0]['result'] == false) {
        return [
            'errorMessages' => $errorMessages,
            'totalErrorMessages' => count($errorMessages),
        ];
    }
    $dataXml = $arrayExito[0]['xmlData'];

    $attachedDocument = $dataXml['AttachedDocument'];
    $validation = $attachedDocument['cac:ParentDocumentLineReference']['cac:DocumentReference']['cac:ResultOfVerification'];

    $arrayExito[] = validationResultCode($dataXml, $validation['cbc:ValidationResultCode'], $errorMessages);

    $numFac = $attachedDocument['cbc:ID'];
    $arrayExito[] = RVC004($data['jsonContents'], $numFac, $errorMessages);

    $nit = $attachedDocument['cac:SenderParty']['cac:PartyTaxScheme']['cbc:CompanyID'];
    $arrayExito[] = RVC001($data['jsonContents'], $nit, $errorMessages);

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

function validationFileXML($archiveXml, $data, &$errorMessages)
{
    $xmlData = [];
    $validation = true;

    try {
        $contenidoXml = file_get_contents($archiveXml);
        $reader = XmlReader::fromString($contenidoXml);
        $xmlData = $reader->values(); // Array of values.
    } catch (\Throwable $th) {
        $errorMessages[] = [
            'validacion' => 'validationFileXML',
            'validacion_type_Y' => 'R',
            'num_invoice' => $data['numInvoice'],
            'file' => $data['file_name'] ?? null,
            'row' => null,
            'column' => 'validationFileXML',
            'data' => null,
            'error' => 'No se pudo leer el archivo XML, ' . $th->getMessage(),
        ];

        $validation = false;
    }

    return [
        'valdiacion_type_Y' => 'R',
        'result' => $validation,
        'xmlData' => $xmlData,
    ];
}
