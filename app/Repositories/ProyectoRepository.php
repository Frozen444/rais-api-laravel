<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use DateTime;
use Illuminate\Support\Facades\DB;

class ProyectoRepository
{
    private const ENTITY_TYPE = 'Projects';

    private const FALLBACK_DATE = '2014-01-01T00:00:00Z';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    private const NS_OCDE_FORD = 'https://purl.org/pe-repo/ocde/ford';

    private const SCHEME_PROJECT_REFERENCE = 'https://w3id.org/cerif/vocab/IdentifierTypes#ProjectReference';

    private const OCDE_PROJECT_TYPE_SCHEME = 'https://purl.org/pe-repo/ocde/tipoProyecto';

    private const CTI_PROJECT_TYPE_SCHEME = 'https://purl.org/pe-repo/concytec/terminos';

    private const STATUS_MAP = [
        1 => 'http://purl.org/cerif/vocab/ProjectStatus#Ongoing',
        2 => 'http://purl.org/cerif/vocab/ProjectStatus#Completed',
        0 => 'http://purl.org/cerif/vocab/ProjectStatus#Cancelled',
    ];

    private const PROJECT_TYPE_OCDE_MAP = [
        'PCONFIGI' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PCONFIGI-INV' => 'https://purl.org/pe-repo/ocde/tipoProyecto#innovacionTecnologica',
        'PSINFINV' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionBasica',
        'PSINFIPU' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PICV' => 'https://purl.org/pe-repo/ocde/tipoProyecto#innovacionTecnologica',
        'PMULTI' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PINVPOS' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PFEX' => 'https://purl.org/pe-repo/ocde/tipoProyecto#desarrolloExperimental',
        'ECI' => 'https://purl.org/pe-repo/ocde/tipoProyecto#innovacionTecnologica',
        'PRO-CTIE' => 'https://purl.org/pe-repo/ocde/tipoProyecto#innovacionTecnologica',
        'PTPGRADO' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PTPMAEST' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PTPDOCTO' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
        'PTPBACHILLER' => 'https://purl.org/pe-repo/ocde/tipoProyecto#investigacionAplicada',
    ];

    public function findById(int $id): ?array
    {
        $rows = DB::select(
            '
                SELECT
                    p.*,
                    f.nombre as facultad_nombre,
                    g.grupo_nombre,
                    o.codigo as ocde_codigo,
                    o.linea as ocde_linea,
                    li.nombre as linea_nombre,
                    pd.detalle as proyecto_descripcion
                FROM Proyecto p
                LEFT JOIN Facultad f ON p.facultad_id = f.id
                LEFT JOIN Grupo g ON p.grupo_id = g.id
                LEFT JOIN Ocde o ON p.ocde_id = o.id
                LEFT JOIN Linea_investigacion li ON p.linea_investigacion_id = li.id
                LEFT JOIN Proyecto_descripcion pd ON p.id = pd.proyecto_id
                WHERE p.id = ?
                  AND p.estado >= 1
                LIMIT 1
            ',
            [$id]
        );

        if (count($rows) === 0) {
            return null;
        }

        $project = (array) $rows[0];
        $participants = $this->getProjectParticipants((int) $project['id']);
        $outputs = $this->getProjectOutputs((int) $project['id']);
        $equipments = $this->getProjectEquipments($project['grupo_id'] ?? null);

        return $this->buildRecord($project, $participants, $outputs, $equipments);
    }

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Proyecto p WHERE p.estado >= 1';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $rows = DB::select($query, $dateFilter['params']);

        return (int) ($rows[0]->total ?? 0);
    }

    public function countDeleted(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Proyecto p WHERE p.estado = 0';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $rows = DB::select($query, $dateFilter['params']);

        return (int) ($rows[0]->total ?? 0);
    }

    public function getIdentifiers(array $filters): array
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 100);
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');

        $query = '
            SELECT p.id, p.updated_at
            FROM Proyecto p
            WHERE p.estado >= 1
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row->id),
                'datestamp' => CerifFormatter::toISO8601($row->updated_at) ?? self::FALLBACK_DATE,
                'setSpec' => 'projects',
            ];
        }, $rows);
    }

    public function getDeletedIdentifiers(array $filters): array
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 100);
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');

        $query = '
            SELECT p.id, p.updated_at
            FROM Proyecto p
            WHERE p.estado = 0
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row->id),
                'datestamp' => CerifFormatter::toISO8601($row->updated_at) ?? self::FALLBACK_DATE,
                'setSpec' => 'projects',
                'status' => 'deleted',
            ];
        }, $rows);
    }

    public function findAll(array $filters): array
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 100);
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');

        $query = '
            SELECT
                p.*,
                f.nombre as facultad_nombre,
                g.grupo_nombre,
                o.codigo as ocde_codigo,
                o.linea as ocde_linea,
                li.nombre as linea_nombre,
                pd.detalle as proyecto_descripcion
            FROM Proyecto p
            LEFT JOIN Facultad f ON p.facultad_id = f.id
            LEFT JOIN Grupo g ON p.grupo_id = g.id
            LEFT JOIN Ocde o ON p.ocde_id = o.id
            LEFT JOIN Linea_investigacion li ON p.linea_investigacion_id = li.id
            LEFT JOIN Proyecto_descripcion pd ON p.id = pd.proyecto_id
            WHERE p.estado >= 1
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            $project = (array) $row;
            $participants = $this->getProjectParticipants((int) $project['id']);
            $outputs = $this->getProjectOutputs((int) $project['id']);
            $equipments = $this->getProjectEquipments($project['grupo_id'] ?? null);

            return $this->buildRecord($project, $participants, $outputs, $equipments);
        }, $rows);
    }

    private function buildRecord(array $project, array $participants, array $outputs, array $equipments): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $project['id']),
                'datestamp' => CerifFormatter::toISO8601($project['updated_at'] ?? null) ?? self::FALLBACK_DATE,
                'setSpec' => 'projects',
            ],
            'metadata' => [
                'Project' => $this->mapToCerif($project, $participants, $outputs, $equipments),
            ],
        ];
    }

    private function mapToCerif(array $row, array $participants, array $outputs, array $equipments): array
    {
        $titleValue = $row['titulo'] ?? $row['codigo_proyecto'] ?? ('Proyecto '.$row['id']);

        $project = [
            'id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'title' => CerifFormatter::filterEmpty([
                CerifFormatter::createTitle($titleValue, 'es'),
            ]),
            'lastModified' => CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE,
        ];

        $projectReference = trim((string) ($row['codigo_proyecto'] ?? ''));
        if ($projectReference === '') {
            $projectReference = 'P-'.$row['id'];
        }

        $project['identifiers'] = [[
            'scheme' => self::SCHEME_PROJECT_REFERENCE,
            'value' => $projectReference,
        ]];

        $types = $this->getProjectTypes($row);
        if (count($types) > 0) {
            $project['type'] = $types;
        }

        $startDate = $this->toDateOnly($row['fecha_inicio'] ?? null);
        if ($startDate) {
            $project['startDate'] = $startDate;
        }

        $endDate = $this->toDateOnly($row['fecha_fin'] ?? null);
        if ($endDate) {
            $project['endDate'] = $endDate;
        }

        if (isset($row['estado']) && isset(self::STATUS_MAP[(int) $row['estado']])) {
            $project['status'] = self::STATUS_MAP[(int) $row['estado']];
        }

        if (! empty($row['palabras_clave'])) {
            $keywords = array_values(array_filter(array_map(function ($keyword) {
                $value = trim((string) $keyword);

                return $value !== '' ? ['lang' => 'es', 'value' => $value] : null;
            }, explode(',', (string) $row['palabras_clave']))));

            if (count($keywords) > 0) {
                $project['keywords'] = $keywords;
            }
        }

        if (! empty($row['proyecto_descripcion'])) {
            $project['abstract'] = [[
                'lang' => 'es',
                'value' => trim((string) $row['proyecto_descripcion']),
            ]];
        }

        if (! empty($row['ocde_codigo'])) {
            $project['subjects'] = [[
                'scheme' => self::NS_OCDE_FORD,
                'value' => self::NS_OCDE_FORD.'#'.$row['ocde_codigo'],
            ]];
        }

        if (! empty($row['linea_nombre'])) {
            $project['researchLine'] = [$row['linea_nombre']];
        }

        $participantItems = $participants;

        if (! empty($row['facultad_id']) && ! empty($row['facultad_nombre'])) {
            $facultadId = CerifFormatter::toCerifId('OrgUnits', 'F'.$row['facultad_id']);
            $exists = false;

            foreach ($participantItems as $item) {
                if (($item['orgUnit']['id'] ?? null) === $facultadId) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $participantItems[] = [
                    'orgUnit' => [
                        'id' => $facultadId,
                        'name' => $row['facultad_nombre'],
                    ],
                    'role' => 'Institución ejecutora',
                ];
            }
        }

        if (! empty($row['grupo_id']) && ! empty($row['grupo_nombre'])) {
            $grupoId = CerifFormatter::toCerifId('OrgUnits', 'G'.$row['grupo_id']);
            $exists = false;

            foreach ($participantItems as $item) {
                if (($item['orgUnit']['id'] ?? null) === $grupoId) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $participantItems[] = [
                    'orgUnit' => [
                        'id' => $grupoId,
                        'name' => $row['grupo_nombre'],
                    ],
                    'role' => 'Grupo de investigación',
                ];
            }
        }

        $hasPersonParticipant = false;
        foreach ($participantItems as $item) {
            if (! empty($item['person'])) {
                $hasPersonParticipant = true;
                break;
            }
        }

        if (! $hasPersonParticipant) {
            $responsable = $this->getResponsibleParticipant((int) $row['id']);

            if ($responsable !== null) {
                $participantItems[] = $responsable;
            } else {
                $participantItems[] = [
                    'person' => [
                        'name' => 'Investigador responsable no especificado',
                    ],
                    'role' => 'Responsable',
                ];
            }
        }

        $project['participants'] = $participantItems;

        if ($this->hasFundingData($row)) {
            $project['fundings'] = [[
                'id' => CerifFormatter::toCerifId('Fundings', 'P'.$row['id']),
            ]];
        }

        $project['outputs'] = [
            'publications' => $outputs['publications'] ?? [],
            'patents' => $outputs['patents'] ?? [],
            'products' => $outputs['products'] ?? [],
        ];

        if (! empty($row['localizacion'])) {
            $project['geoLocations'] = [[
                'geoLocationPlace' => $row['localizacion'],
            ]];
        }

        if (count($equipments) > 0) {
            $project['uses'] = $equipments;
        }

        return $project;
    }

    private function getProjectParticipants(int $projectId): array
    {
        $rows = DB::select(
            '
                SELECT
                    pi.investigador_id,
                    pi.condicion,
                    ui.nombres,
                    ui.apellido1,
                    ui.apellido2,
                    pit.nombre as tipo_nombre,
                    p.facultad_id,
                    f.nombre as facultad_nombre,
                    p.grupo_id,
                    g.grupo_nombre
                FROM Proyecto_integrante pi
                LEFT JOIN Usuario_investigador ui ON pi.investigador_id = ui.id
                LEFT JOIN Proyecto_integrante_tipo pit ON pi.proyecto_integrante_tipo_id = pit.id
                LEFT JOIN Proyecto p ON p.id = pi.proyecto_id
                LEFT JOIN Facultad f ON p.facultad_id = f.id
                LEFT JOIN Grupo g ON p.grupo_id = g.id
                WHERE pi.proyecto_id = ?
                  AND IFNULL(pi.estado, 1) = 1
                ORDER BY pi.id
            ',
            [$projectId]
        );

        return $this->buildParticipants(array_map(fn ($row) => (array) $row, $rows));
    }

    private function getProjectOutputs(int $projectId): array
    {
        $rows = DB::select(
            '
                SELECT DISTINCT pp.publicacion_id
                FROM Publicacion_proyecto pp
                WHERE pp.proyecto_id = ?
                  AND pp.publicacion_id IS NOT NULL
                  AND IFNULL(pp.estado, 1) = 1
                ORDER BY pp.publicacion_id
            ',
            [$projectId]
        );

        return [
            'publications' => array_map(function ($row) {
                return CerifFormatter::toCerifId('Publications', $row->publicacion_id);
            }, $rows),
            'patents' => [],
            'products' => [],
        ];
    }

    private function getProjectEquipments(mixed $groupId): array
    {
        if (empty($groupId)) {
            return [];
        }

        $rows = DB::select(
            '
                SELECT gi.id
                FROM Grupo_infraestructura gi
                WHERE gi.grupo_id = ?
                ORDER BY gi.id
                LIMIT 100
            ',
            [$groupId]
        );

        return array_map(function ($row) {
            return CerifFormatter::toCerifId('Equipments', $row->id);
        }, $rows);
    }

    private function buildParticipants(array $integrantes): array
    {
        $participants = [];

        $facultadId = null;
        $facultadNombre = null;
        $grupoId = null;
        $grupoNombre = null;

        foreach ($integrantes as $integrante) {
            $fullName = trim(implode(' ', array_filter([
                $integrante['nombres'] ?? null,
                $integrante['apellido1'] ?? null,
                $integrante['apellido2'] ?? null,
            ])));

            if (empty($integrante['investigador_id']) && $fullName === '') {
                continue;
            }

            $participant = [
                'role' => $this->buildParticipantRole($integrante),
            ];

            if (! empty($integrante['investigador_id'])) {
                $participant['person'] = [
                    'id' => CerifFormatter::toCerifId('Persons', $integrante['investigador_id']),
                    'name' => $fullName !== '' ? $fullName : ('Investigador '.$integrante['investigador_id']),
                ];
            } elseif ($fullName !== '') {
                $participant['person'] = [
                    'name' => $fullName,
                ];
            }

            $participants[] = $participant;

            $facultadId = $integrante['facultad_id'] ?? $facultadId;
            $facultadNombre = $integrante['facultad_nombre'] ?? $facultadNombre;
            $grupoId = $integrante['grupo_id'] ?? $grupoId;
            $grupoNombre = $integrante['grupo_nombre'] ?? $grupoNombre;
        }

        if (! empty($facultadId) && ! empty($facultadNombre)) {
            $participants[] = [
                'orgUnit' => [
                    'id' => CerifFormatter::toCerifId('OrgUnits', 'F'.$facultadId),
                    'name' => $facultadNombre,
                ],
                'role' => 'Institución ejecutora',
            ];
        }

        if (! empty($grupoId) && ! empty($grupoNombre)) {
            $participants[] = [
                'orgUnit' => [
                    'id' => CerifFormatter::toCerifId('OrgUnits', 'G'.$grupoId),
                    'name' => $grupoNombre,
                ],
                'role' => 'Grupo de investigación',
            ];
        }

        return $participants;
    }

    private function buildParticipantRole(array $integrante): string
    {
        $rawRole = trim((string) ($integrante['tipo_nombre'] ?? $integrante['condicion'] ?? ''));

        return $rawRole !== '' ? $rawRole : 'Participante';
    }

    private function getResponsibleParticipant(int $projectId): ?array
    {
        $rows = DB::select(
            '
                SELECT
                    pi.investigador_id,
                    ui.nombres,
                    ui.apellido1,
                    ui.apellido2,
                    pit.nombre as tipo_nombre,
                    pi.condicion
                FROM Proyecto_integrante pi
                LEFT JOIN Usuario_investigador ui ON pi.investigador_id = ui.id
                LEFT JOIN Proyecto_integrante_tipo pit ON pi.proyecto_integrante_tipo_id = pit.id
                WHERE pi.proyecto_id = ?
                  AND IFNULL(pi.estado, 1) = 1
                ORDER BY
                    CASE
                        WHEN LOWER(IFNULL(pit.nombre, \'\')) LIKE \'%principal%\' THEN 0
                        WHEN LOWER(IFNULL(pi.condicion, \'\')) LIKE \'%principal%\' THEN 0
                        WHEN LOWER(IFNULL(pit.nombre, \'\')) LIKE \'%responsable%\' THEN 1
                        WHEN LOWER(IFNULL(pi.condicion, \'\')) LIKE \'%responsable%\' THEN 1
                        ELSE 2
                    END,
                    pi.id
                LIMIT 1
            ',
            [$projectId]
        );

        if (count($rows) === 0) {
            try {
                $fallbackRows = DB::select(
                    '
                        SELECT
                            p.investigador_id,
                            ui.nombres,
                            ui.apellido1,
                            ui.apellido2
                        FROM Proyecto p
                        LEFT JOIN Usuario_investigador ui ON p.investigador_id = ui.id
                        WHERE p.id = ?
                          AND p.investigador_id IS NOT NULL
                        LIMIT 1
                    ',
                    [$projectId]
                );

                if (count($fallbackRows) === 0) {
                    return null;
                }

                $row = (array) $fallbackRows[0];
            } catch (\Throwable) {
                return null;
            }
        } else {
            $row = (array) $rows[0];
        }

        $fullName = trim(implode(' ', array_filter([
            $row['nombres'] ?? null,
            $row['apellido1'] ?? null,
            $row['apellido2'] ?? null,
        ])));

        if ($fullName === '') {
            $fullName = ! empty($row['investigador_id'])
                ? 'Investigador '.$row['investigador_id']
                : 'Investigador responsable';
        }

        $participant = [
            'person' => [
                'name' => $fullName,
            ],
            'role' => 'Responsable',
        ];

        if (! empty($row['investigador_id'])) {
            $participant['person']['id'] = CerifFormatter::toCerifId('Persons', $row['investigador_id']);
        }

        return $participant;
    }

    private function getProjectTypes(array $row): array
    {
        $types = [];
        $projectType = trim((string) ($row['tipo_proyecto'] ?? ''));

        if ($projectType === '') {
            return $types;
        }

        $ocdeType = self::PROJECT_TYPE_OCDE_MAP[$projectType] ?? null;
        if ($ocdeType) {
            $types[] = [
                'scheme' => self::OCDE_PROJECT_TYPE_SCHEME,
                'value' => $ocdeType,
            ];
        }

        $ctiSlug = $this->toSlug($projectType);
        if ($ctiSlug !== '') {
            $types[] = [
                'scheme' => self::CTI_PROJECT_TYPE_SCHEME,
                'value' => self::CTI_PROJECT_TYPE_SCHEME.'#'.$ctiSlug,
            ];
        }

        return $types;
    }

    private function hasFundingData(array $row): bool
    {
        $total =
            (float) ($row['aporte_unmsm'] ?? 0)
            + (float) ($row['aporte_no_unmsm'] ?? 0)
            + (float) ($row['financiamiento_fuente_externa'] ?? 0)
            + (float) ($row['entidad_asociada'] ?? 0);

        return $total > 0 || (! empty($row['codigo_proyecto']) && trim((string) $row['codigo_proyecto']) !== '');
    }

    private function toSlug(mixed $value): string
    {
        $string = strtolower(trim((string) $value));

        if ($string === '') {
            return '';
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $string) ?? '';

        return trim($slug, '-');
    }

    private function toDateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return (new DateTime((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            $trimmed = trim((string) $value);

            return $trimmed !== '' ? $trimmed : null;
        }
    }
}
