<?php

namespace App\Services;

class CerifFormatter
{
    public const ORGUNIT_TYPE_FACULTAD = 'https://purl.org/pe-repo/concytec/terminos#facultad';

    public const ORGUNIT_TYPE_INSTITUTO = 'https://purl.org/pe-repo/concytec/terminos#instituto';

    public const ORGUNIT_TYPE_GRUPO_INVESTIGACION = 'https://purl.org/pe-repo/concytec/terminos#grupoInvestigacion';

    public const PUBLICATION_TYPE_MAP = [
        'articulo' => 'http://purl.org/coar/resource_type/c_6501',
        'libro' => 'http://purl.org/coar/resource_type/c_2f33',
        'capitulo' => 'http://purl.org/coar/resource_type/c_3248',
        'tesis' => 'http://purl.org/coar/resource_type/c_db06',
        'tesis-asesoria' => 'http://purl.org/coar/resource_type/c_db06',
        'tesis-bachiller' => 'http://purl.org/coar/resource_type/c_7a1f',
        'tesis-maestria' => 'http://purl.org/coar/resource_type/c_bdcc',
        'tesis-doctorado' => 'http://purl.org/coar/resource_type/c_db06',
        'evento' => 'http://purl.org/coar/resource_type/c_c94f',
        'ensayo' => 'http://purl.org/coar/resource_type/c_dcae04bc',
        'default' => 'http://purl.org/coar/resource_type/c_1843',
    ];

    public const PATENT_TYPE_MAP = [
        'Patente de invención' => 'http://purl.org/coar/resource_type/9DKX-KSAF',
        'Modelo de utilidad' => 'http://purl.org/coar/resource_type/9DKX-KSAF',
        'default' => 'http://purl.org/coar/resource_type/9DKX-KSAF',
    ];

    public const LEGACY_SET_ALIASES = [
        'funding' => 'fundings',
        'equipment' => 'equipments',
    ];

    public const PATENT_IPC_BY_KEYWORDS = [
        ['motor eléctrico|generador eléctrico|máquina eléctrica', 'H02K'],
        ['transformador|convertidor|alimentación|rectificador', 'H02M'],
        ['circuito eléctrico|electrónica|semiconductor|diodo|transistor', 'H01L'],
        ['construcción|edificio|cemento|concreto|mampostería|estructura', 'E04B'],
        ['techo|cubierta|tejado|losa', 'E04D'],
        ['engranaje|transmisión|acople', 'F16H'],
        ['cojinete|rodamiento|eje|chumacera', 'F16C'],
        ['válvula|grifo|compuerta|llave', 'F16K'],
        ['tubería|conducto|conexión|manguera', 'F16L'],
        ['motor|máquina|mecanismo', 'F16H'],
        ['bicicleta|motocicleta|mototaxi', 'B62K'],
        ['vehículo|auto|carrocería|chasis|bastidor', 'B62D'],
        ['rueda|llanta|freno', 'B62K'],
        ['aleación|metal|tratamiento térmico', 'C22C'],
        ['plástico|polímero|resina|elastómero', 'C08L'],
        ['composición|compuesto|material|sustancia', 'C01B'],
        ['medicamento|fármaco|composición farmacéutica|fórmula', 'A61K'],
        ['dispositivo médico|prótesis|implante|catéter', 'A61F'],
        ['diagnóstico|tratamiento|terapia|método quirúrgico', 'A61B'],
        ['maquinaria agrícola|tractor|cosechadora|arado', 'A01B'],
        ['cultivo|planta|semilla|fertilizante|abono', 'A01G'],
        ['computadora|software|algoritmo|procesamiento|código', 'G06F'],
        ['sistema de información|base de datos|aplicación', 'G06F'],
        ['medición|sensor|detector|instrumento|calibración', 'G01N'],
        ['control|regulación|monitoreo|automatización', 'G05B'],
    ];

    public static function toISO8601($date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            $d = $date instanceof \DateTimeInterface ? $date : new \DateTime((string) $date);
        } catch (\Throwable) {
            return null;
        }

        if ($d->format('Y') === '-0001' || $d->format('Y') === '0000') {
            return null;
        }

        return $d->format('Y-m-d\TH:i:s\Z');
    }

    public static function nowISO8601(): string
    {
        return (new \DateTime)->format('Y-m-d\TH:i:s\Z');
    }

    public static function toOAIIdentifier(string $entityType, int|string $id): string
    {
        $domain = config('oai.domain', 'perucris.concytec.gob.pe');

        return "oai:{$domain}:{$entityType}/{$id}";
    }

    public static function parseOAIIdentifier(string $oaiId): ?array
    {
        if (! $oaiId || ! is_string($oaiId)) {
            return null;
        }

        $regex = '/^oai:([^:]+):([^\\/]+)\\/(.+)$/';
        if (! preg_match($regex, $oaiId, $matches)) {
            return null;
        }

        return [
            'domain' => $matches[1],
            'entityType' => $matches[2],
            'id' => $matches[3],
        ];
    }

    public static function normalizeSetSpec(?string $setSpec): ?string
    {
        if (! $setSpec) {
            return $setSpec;
        }

        return self::LEGACY_SET_ALIASES[$setSpec] ?? $setSpec;
    }

    public static function buildOaiRequestAttributes(array $params): array
    {
        $attrs = [];

        if (! empty($params['metadataPrefix'])) {
            $attrs['@metadataPrefix'] = $params['metadataPrefix'];
        }

        if (! empty($params['set'])) {
            $attrs['@set'] = $params['set'];
        }

        if (! empty($params['from'])) {
            $attrs['@from'] = $params['from'];
        }

        if (! empty($params['until'])) {
            $attrs['@until'] = $params['until'];
        }

        if (! empty($params['identifier'])) {
            $attrs['@identifier'] = $params['identifier'];
        }

        if (! empty($params['resumptionToken'])) {
            $attrs['@resumptionToken'] = $params['resumptionToken'];
        }

        return $attrs;
    }

    public static function toCerifId(string $entityType, int|string $id): string
    {
        return "{$entityType}/{$id}";
    }

    public static function formatFullName(?string $nombres, ?string $apellido1, ?string $apellido2): string
    {
        $parts = array_filter([$nombres, $apellido1, $apellido2]);

        return implode(' ', $parts);
    }

    public static function formatFamilyNames(?string $apellido1, ?string $apellido2): string
    {
        $parts = array_filter([$apellido1, $apellido2]);

        return implode(' ', $parts);
    }

    public static function filterEmpty(array $arr): array
    {
        return array_values(array_filter($arr, fn ($item) => $item !== null && $item !== ''));
    }

    public static function ensureArray($value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    public static function createTitle(?string $value, ?string $lang = null): ?array
    {
        if (! $value) {
            return null;
        }

        $obj = ['value' => trim($value)];
        if ($lang) {
            $obj['lang'] = $lang;
        }

        return $obj;
    }

    public static function createIdentifier(?string $scheme, ?string $value): ?array
    {
        if (! $value || $value === '0' || $value === '') {
            return null;
        }

        return ['scheme' => $scheme, 'value' => trim($value)];
    }

    public static function createTypedIdentifier(?string $type, ?string $value): ?array
    {
        if (! $value || $value === '') {
            return null;
        }

        return ['type' => $type, 'value' => trim($value)];
    }

    public static function getEntityConfig(?string $setSpec): ?array
    {
        $configs = [
            'persons' => ['entityType' => 'Persons', 'table' => 'Usuario_investigador'],
            'orgunits' => ['entityType' => 'OrgUnits', 'table' => 'Facultad'],
            'publications' => ['entityType' => 'Publications', 'table' => 'Publicacion'],
            'projects' => ['entityType' => 'Projects', 'table' => 'Proyecto'],
            'patents' => ['entityType' => 'Patents', 'table' => 'Patente'],
            'fundings' => ['entityType' => 'Fundings', 'table' => 'Proyecto'],
            'equipments' => ['entityType' => 'Equipments', 'table' => 'Grupo_infraestructura'],
        ];

        $normalizedSet = self::normalizeSetSpec($setSpec);

        return $configs[$normalizedSet] ?? null;
    }

    public static function parseFilterDate(?string $dateStr): ?\DateTime
    {
        if (! $dateStr) {
            return null;
        }

        try {
            $date = new \DateTime($dateStr);
        } catch (\Throwable) {
            return null;
        }

        if ($date->format('Y') === '-0001' || $date->format('Y') === '0000') {
            return null;
        }

        return $date;
    }

    public static function buildDateFilter(?string $fromDate, ?string $untilDate, string $dateField = 'updated_at'): array
    {
        $conditions = [];
        $params = [];

        if ($fromDate) {
            $from = self::parseFilterDate($fromDate);
            if ($from) {
                $conditions[] = "{$dateField} >= ?";
                $params[] = $from->format('Y-m-d H:i:s');
            }
        }

        if ($untilDate) {
            $until = self::parseFilterDate($untilDate);
            if ($until) {
                $conditions[] = "{$dateField} <= ?";
                $params[] = $until->format('Y-m-d H:i:s');
            }
        }

        return [
            'clause' => implode(' AND ', $conditions),
            'params' => $params,
        ];
    }

    public static function inferAccessRights(array $row): array
    {
        if ($row['doi'] ?? $row['url'] ?? $row['uri'] ?? null) {
            return [
                'uri' => 'http://purl.org/coar/access_right/c_abf2',
                'label' => 'open access',
            ];
        }

        if (($row['tipo_publicacion'] ?? '') === 'tesis-asesoria' && ! ($row['uri'] ?? null)) {
            return [
                'uri' => 'http://purl.org/coar/access_right/c_14cb',
                'label' => 'metadata only access',
            ];
        }

        return [
            'uri' => 'http://purl.org/coar/access_right/c_14cb',
            'label' => 'metadata only access',
        ];
    }

    public static function inferIPCClassification(array $row): array
    {
        $title = strtolower($row['titulo'] ?? '');
        $type = strtolower($row['tipo'] ?? '');

        foreach (self::PATENT_IPC_BY_KEYWORDS as [$keywords, $ipcCode]) {
            if (preg_match("/{$keywords}/i", $title)) {
                return [
                    'scheme' => 'http://data.epo.org/linked-data/def/ipc/',
                    'value' => 'http://data.epo.org/linked-data/def/ipc/'.$ipcCode,
                ];
            }
        }

        if (str_contains($type, 'modelo de utilidad')) {
            return [
                'scheme' => 'http://data.epo.org/linked-data/def/ipc/',
                'value' => 'http://data.epo.org/linked-data/def/ipc/F16H',
                'note' => 'Clasificación inferida por tipo - requiere curación manual',
            ];
        }

        return [
            'scheme' => 'http://data.epo.org/linked-data/def/ipc/',
            'value' => 'http://data.epo.org/linked-data/def/ipc/Y10S',
            'note' => 'Clasificación genérica - requiere curación manual',
        ];
    }
}
