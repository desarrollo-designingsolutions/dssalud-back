<?php

namespace App\Helpers;

class Constants
{
    // Agrega más constantes según sea necesario

    public const COMPANY_UUID = '9e5aec58-a962-4670-8188-b41c6d0149a3';

    public const ROLE_SUPERADMIN_UUID = '21626ff9-4940-4143-879a-0f75b46eadb7';

    public const COUNTRY_ID = '48'; // Colombia

    public const ITEMS_PER_PAGE = '10'; // PARA LA PAGINACIONES

    public const ERROR_MESSAGE_VALIDATION_BACK = 'Se evidencia algunos errores.';

    public const ERROR_MESSAGE_TRYCATCH = 'Algo Ocurrio, Comunicate Con El Equipo De Desarrollo.';

    public const REDIS_TTL = '315360000'; // 10 años en segundos

    public const DISK_FILES = 'public'; // sistema de archivos

    public const CHUNKSIZE = 1;

    public const NUMBER_CASE_INITIAL = 100;

    // LLAVES PARA CONSTRUCCION Y VALIDACION DE RADICACIONES ANTIGUAS Y 2275
    public const KEY_NUMFACT = 'numFactura';

    public const KEY_NumDocumentoIdentificacion = 'numDocumentoIdentificacion';

    public const KEY_VrServicio = 'vrServicio';
}
