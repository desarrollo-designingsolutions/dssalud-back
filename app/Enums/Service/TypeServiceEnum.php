<?php

namespace App\Enums\Service;

use App\Attributes\Color;
use App\Attributes\Description;
use App\Attributes\ElementJson;
use App\Attributes\Icon;
use App\Attributes\Model;
use App\Traits\AttributableEnum;

enum TypeServiceEnum: string
{
    use AttributableEnum;

    #[Description('Consultas')]
    #[Model('App\\Models\\MedicalConsultation')]
    #[ElementJson('consultas')]
    #[Icon('tabler-report-medical')]
    #[Color('#6C757D')] // Gris Azulado
    case SERVICE_TYPE_001 = 'SERVICE_TYPE_001';

    #[Description('Procedimientos')]
    #[Model('App\\Models\\Procedure')]
    #[ElementJson('procedimientos')]
    #[Icon('tabler-checkup-list')]
    #[Color('#28A745')] // Verde Salud
    case SERVICE_TYPE_002 = 'SERVICE_TYPE_002';

    #[Description('Urgencias')]
    #[Model('App\\Models\\Urgency')]
    #[ElementJson('urgencias')]
    #[Icon('tabler-urgent')]
    #[Color('#DC3545')] // Rojo Urgencia
    case SERVICE_TYPE_003 = 'SERVICE_TYPE_003';

    #[Description('Hospitalización')]
    #[Model('App\\Models\\Hospitalization')]
    #[ElementJson('hospitalizacion')]
    #[Icon('tabler-building-hospital')]
    #[Color('#007BFF')] // Azul Profesional
    case SERVICE_TYPE_004 = 'SERVICE_TYPE_004';

    #[Description('Recien nacidos')]
    #[Model('App\\Models\\NewlyBorn')]
    #[ElementJson('recienNacidos')]
    #[Icon('tabler-baby-carriage')]
    #[Color('#FD7E14')] // Naranja Suave
    case SERVICE_TYPE_005 = 'SERVICE_TYPE_005';

    #[Description('Medicamentos')]
    #[Model('App\\Models\\Medicine')]
    #[ElementJson('medicamentos')]
    #[Icon('tabler-pill')]
    #[Color('#6F42C1')] // Púrpura Farmacéutico
    case SERVICE_TYPE_006 = 'SERVICE_TYPE_006';

    #[Description('Otros servicios')]
    #[Model('App\\Models\\OtherService')]
    #[ElementJson('otrosServicios')]
    #[Icon('tabler-heart-cog')]
    #[Color('#17A2B8')] // Cian Médico
    case SERVICE_TYPE_007 = 'SERVICE_TYPE_007';





    /**
     * Busca y devuelve el caso del enum que tiene el atributo ElementJson igual al $key proporcionado.
     *
     * @param string $key El valor del ElementJson a buscar (ej: 'consultas', 'medicamentos')
     * @return self
     * @throws ValueError Si no se encuentra ningún caso con ese ElementJson
     */
    public static function fromElementJson(string $key): self
    {
        return match ($key) {
            'consultas'        => self::SERVICE_TYPE_001,
            'procedimientos'   => self::SERVICE_TYPE_002,
            'urgencias'        => self::SERVICE_TYPE_003,
            'hospitalizacion'  => self::SERVICE_TYPE_004,
            'recienNacidos'    => self::SERVICE_TYPE_005,
            'medicamentos'     => self::SERVICE_TYPE_006,
            'otrosServicios'   => self::SERVICE_TYPE_007,
            default            => throw new \ValueError("No TypeServiceEnum found for ElementJson value: '{$key}'"),
        };
    }
}
