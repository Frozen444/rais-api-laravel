<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use DateTime;
use Illuminate\Support\Facades\DB;

class PatenteRepository
{
    private const ENTITY_TYPE = 'Patents';

    private const FALLBACK_DATE = '2014-01-01T00:00:00Z';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    public function findById(int $id): ?array
    {
        $rows = DB::select(
            '
                SELECT p.*
                FROM Patente p
                WHERE p.id = ?
                  AND p.estado = 1
                LIMIT 1
            ',
            [$id]
        );

        if (count($rows) === 0) {
            return null;
        }

        $patent = (array) $rows[0];
        $context = $this->getPatentContext((int) $patent['id']);

        return $this->buildRecord($patent, $context);
    }

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Patente p WHERE p.estado = 1';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $rows = DB::select($query, $dateFilter['params']);

        return (int) ($rows[0]->total ?? 0);
    }

    public function countDeleted(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Patente p WHERE p.estado = 0';

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
        $query = 'SELECT p.id, p.updated_at FROM Patente p WHERE p.estado = 1';

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
                'setSpec' => 'patents',
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
        $query = 'SELECT p.id, p.updated_at FROM Patente p WHERE p.estado = 0';

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
                'setSpec' => 'patents',
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
            SELECT p.*
            FROM Patente p
            WHERE p.estado = 1
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            $patent = (array) $row;
            $context = $this->getPatentContext((int) $patent['id']);

            return $this->buildRecord($patent, $context);
        }, $rows);
    }

    private function buildRecord(array $patent, array $context): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $patent['id']),
                'datestamp' => CerifFormatter::toISO8601($patent['updated_at'] ?? null) ?? self::FALLBACK_DATE,
                'setSpec' => 'patents',
            ],
            'metadata' => [
                'Patent' => $this->mapToCerif($patent, $context),
            ],
        ];
    }

    private function mapToCerif(array $row, array $context): array
    {
        $inventors = $context['inventors'] ?? [];
        $holders = $context['holders'] ?? [];
        $projectIds = $context['projectIds'] ?? [];

        $typeUri = CerifFormatter::PATENT_TYPE_MAP[$row['tipo'] ?? '']
            ?? CerifFormatter::PATENT_TYPE_MAP['default'];

        $ipcClassification = CerifFormatter::inferIPCClassification($row);
        $lastModified = CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE;
        $titleValue = $row['titulo'] ?? ('Patente '.$row['id']);

        $patent = [
            'id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'type' => $typeUri,
            'patentNumber' => $row['nro_registro'] ?: ('PAT-'.$row['id']),
            'title' => CerifFormatter::filterEmpty([
                CerifFormatter::createTitle($titleValue, 'es'),
            ]),
            'subjects' => [[
                'scheme' => $ipcClassification['scheme'],
                'value' => $ipcClassification['value'],
            ]],
            'countryCode' => 'PE',
            'language' => ['es'],
            'lastModified' => $lastModified,
        ];

        if (! empty($ipcClassification['note'])) {
            $patent['notes'] = [$ipcClassification['note']];
        }

        if (count($inventors) > 0) {
            $patent['inventors'] = array_map(function ($inventor) {
                $fullName = trim(implode(' ', array_filter([
                    $inventor['nombres'] ?? null,
                    $inventor['apellido1'] ?? null,
                    $inventor['apellido2'] ?? null,
                ])));

                $person = [
                    'name' => $fullName,
                ];

                if (! empty($inventor['investigador_id'])) {
                    $person['id'] = CerifFormatter::toCerifId('Persons', $inventor['investigador_id']);
                }

                $identifiers = [];
                if (! empty($inventor['doc_numero'])) {
                    $identifiers[] = [
                        'scheme' => 'http://purl.org/pe-repo/concytec/terminos#dni',
                        'value' => (string) $inventor['doc_numero'],
                    ];
                }
                if (! empty($inventor['codigo_orcid'])) {
                    $identifiers[] = [
                        'scheme' => 'https://orcid.org',
                        'value' => $this->normalizeOrcid((string) $inventor['codigo_orcid']),
                    ];
                }
                if (count($identifiers) > 0) {
                    $person['identifiers'] = $identifiers;
                }

                return [
                    'person' => $person,
                ];
            }, $inventors);
        }

        $holderItems = [];
        foreach ($holders as $holder) {
            if (! empty($holder['titular'])) {
                $holderItems[] = [
                    'orgUnit' => [
                        'name' => $holder['titular'],
                    ],
                ];
            }
        }

        if (! empty($row['titular1'])) {
            $holderItems[] = [
                'orgUnit' => [
                    'name' => $row['titular1'],
                ],
            ];
        }

        if (! empty($row['titular2'])) {
            $holderItems[] = [
                'orgUnit' => [
                    'name' => $row['titular2'],
                ],
            ];
        }

        if (count($holderItems) > 0) {
            $patent['holders'] = $holderItems;
        }

        if (count($projectIds) > 0) {
            $patent['originatesFrom'] = array_map(function ($projectId) {
                return [
                    'project' => [
                        'id' => CerifFormatter::toCerifId('Projects', $projectId),
                    ],
                ];
            }, $projectIds);
        }

        $patent['issuer'] = [
            'orgUnit' => [
                'id' => CerifFormatter::toCerifId('OrgUnits', 'INDECOPI'),
                'acronym' => 'INDECOPI',
                'name' => 'Instituto Nacional de Defensa de la Competencia y de la Protección de la Propiedad Intelectual',
            ],
        ];

        $registrationDate = $this->toDateOnly($row['fecha_presentacion'] ?? null);
        if ($registrationDate) {
            $patent['registrationDate'] = $registrationDate;
        }

        if (! empty($row['comentario'])) {
            $patent['abstract'] = [[
                'lang' => 'es',
                'value' => (string) $row['comentario'],
            ]];
        }

        if (! empty($row['enlace'])) {
            $patent['url'] = $row['enlace'];
        }

        if (! empty($row['nro_expediente'])) {
            $patent['identifiers'] = [[
                'type' => 'Expediente',
                'value' => $row['nro_expediente'],
            ]];
        }

        return $patent;
    }

    private function getPatentContext(int $patentId): array
    {
        $inventors = DB::select(
            '
                SELECT
                    pa.*,
                    ui.nombres as ui_nombres,
                    ui.apellido1 as ui_apellido1,
                    ui.apellido2 as ui_apellido2,
                    ui.doc_numero,
                    ui.codigo_orcid
                FROM Patente_autor pa
                LEFT JOIN Usuario_investigador ui ON pa.investigador_id = ui.id
                WHERE pa.patente_id = ?
                ORDER BY pa.id
            ',
            [$patentId]
        );

        $holders = DB::select(
            '
                SELECT titular
                FROM Patente_entidad
                WHERE patente_id = ?
                ORDER BY id
            ',
            [$patentId]
        );

        $mappedInventors = array_map(function ($inventor) {
            $row = (array) $inventor;

            return [
                ...$row,
                'nombres' => $row['nombres'] ?: ($row['ui_nombres'] ?? null),
                'apellido1' => $row['apellido1'] ?: ($row['ui_apellido1'] ?? null),
                'apellido2' => $row['apellido2'] ?: ($row['ui_apellido2'] ?? null),
            ];
        }, $inventors);

        return [
            'inventors' => $mappedInventors,
            'holders' => array_map(fn ($row) => (array) $row, $holders),
            'projectIds' => $this->getPatentProjectIds($patentId),
        ];
    }

    private function getPatentProjectIds(int $patentId): array
    {
        $projectIds = [];

        try {
            $directRows = DB::select(
                '
                    SELECT DISTINCT pp.proyecto_id
                    FROM Patente_proyecto pp
                    WHERE pp.patente_id = ?
                      AND pp.proyecto_id IS NOT NULL
                    ORDER BY pp.proyecto_id
                ',
                [$patentId]
            );

            foreach ($directRows as $row) {
                if (! empty($row->proyecto_id)) {
                    $projectIds[] = (int) $row->proyecto_id;
                }
            }
        } catch (\Throwable) {
        }

        if (count($projectIds) === 0) {
            $fallbackRows = DB::select(
                '
                    SELECT DISTINCT pi.proyecto_id
                    FROM Patente_autor pa
                    INNER JOIN Proyecto_integrante pi ON pa.investigador_id = pi.investigador_id
                    INNER JOIN Proyecto p ON p.id = pi.proyecto_id
                    WHERE pa.patente_id = ?
                      AND pa.investigador_id IS NOT NULL
                      AND IFNULL(pi.estado, 1) = 1
                      AND p.estado >= 1
                      AND pi.proyecto_id IS NOT NULL
                    ORDER BY pi.proyecto_id
                ',
                [$patentId]
            );

            foreach ($fallbackRows as $row) {
                if (! empty($row->proyecto_id)) {
                    $projectIds[] = (int) $row->proyecto_id;
                }
            }
        }

        return array_values(array_unique($projectIds));
    }

    private function normalizeOrcid(string $orcid): string
    {
        $value = trim($orcid);

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return 'https://orcid.org/'.$value;
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
