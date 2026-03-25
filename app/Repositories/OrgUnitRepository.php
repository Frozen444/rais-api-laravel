<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use Illuminate\Support\Facades\DB;

class OrgUnitRepository
{
    private const ENTITY_TYPE = 'OrgUnits';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    private const SCHEME_RUC = 'https://purl.org/pe-repo/concytec/terminos#ruc';
    private const SCHEME_ROR = 'https://ror.org';
    private const SCHEME_ISNI = 'https://isni.org';
    private const SCHEME_GRID = 'https://www.grid.ac';
    private const SCHEME_SCOPUS_AFFIL = 'http://purl.org/pe-repo/concytec/scopus/affiliationId';
    private const SCHEME_ORG_TYPE = 'https://purl.org/pe-repo/concytec/tipoOrganizacion';

    private const UNMSM_ROOT = [
        'id' => 1,
        'nombre' => 'Universidad Nacional Mayor de San Marcos',
        'acronym' => 'UNMSM',
        'ruc' => '20106897914',
        'ror' => 'https://ror.org/026zsd177',
        'isni' => '0000 0001 2107 4242',
        'grid' => 'grid.412881.4',
        'scopusAffiliationId' => '60012091',
        'countryCode' => 'PE',
        'ubigeo' => '150000',
        'ciiu' => '8530',
        'sectorOcde' => '09',
    ];

    public function findById(string $id): ?array
    {
        if ($id === '1' || $id === 1) {
            return $this->buildUnmsmRecord();
        }

        $prefix = substr($id, 0, 1);
        $numId = substr($id, 1);

        return match ($prefix) {
            'F' => $this->findFacultadById($numId),
            'I' => $this->findInstitutoById($numId),
            'G' => $this->findGrupoById($numId),
            default => null,
        };
    }

    public function getEarliestDatestamp(): string
    {
        $result = DB::select(
            'SELECT MIN(updated_at) as earliest FROM Grupo WHERE estado = 4'
        );
        return CerifFormatter::toISO8601($result[0]->earliest ?? 'now');
    }

    public function getDistinctSets(): array
    {
        return [
            ['setSpec' => 'orgunits', 'setName' => 'Unidades organizativas'],
        ];
    }

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $facultadesResult = DB::select('SELECT COUNT(*) as total FROM Facultad');
        $institutosResult = DB::select('SELECT COUNT(*) as total FROM Instituto WHERE estado = 1');

        $dateFilter = CerifFormatter::buildDateFilter($from, $until);
        $gruposQuery = 'SELECT COUNT(*) as total FROM Grupo WHERE estado = 4';
        if ($dateFilter['clause']) {
            $gruposQuery .= ' AND ' . $dateFilter['clause'];
        }
        $gruposResult = DB::select($gruposQuery, $dateFilter['params']);

        return 1 + (int) $facultadesResult[0]->total + (int) $institutosResult[0]->total + (int) $gruposResult[0]->total;
    }

    public function getIdentifiers(array $filters): array
    {
        $records = $this->findAll($filters);
        return array_map(fn($r) => $r['header'], $records);
    }

    public function findAll(array $filters): array
    {
        $offset = $filters['offset'] ?? 0;
        $limit = $filters['limit'] ?? 100;
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;

        $results = [];
        $currentOffset = $offset;
        $remaining = $limit;

        if ($currentOffset === 0 && $remaining > 0) {
            $results[] = $this->buildUnmsmRecord();
            $remaining--;
            $currentOffset = 0;
        } elseif ($currentOffset > 0) {
            $currentOffset--;
        }

        if ($remaining > 0) {
            $facultades = DB::select(
                'SELECT * FROM Facultad ORDER BY id LIMIT ? OFFSET ?',
                [$remaining, max(0, $currentOffset)]
            );

            foreach ($facultades as $f) {
                $results[] = $this->buildFacultadRecord((array) $f);
                $remaining--;
            }
            $currentOffset = max(0, $currentOffset - count($facultades));
        }

        if ($remaining > 0) {
            $institutos = DB::select("
                SELECT i.*, f.nombre as facultad_nombre
                FROM Instituto i
                LEFT JOIN Facultad f ON i.facultad_id = f.id
                WHERE i.estado = 1
                ORDER BY i.id
                LIMIT ? OFFSET ?
            ", [$remaining, max(0, $currentOffset)]);

            foreach ($institutos as $inst) {
                $results[] = $this->buildInstitutoRecord((array) $inst);
                $remaining--;
            }
            $currentOffset = max(0, $currentOffset - count($institutos));
        }

        if ($remaining > 0) {
            $dateFilter = CerifFormatter::buildDateFilter($from, $until);
            $gruposQuery = "
                SELECT g.*, f.nombre as facultad_nombre
                FROM Grupo g
                LEFT JOIN Facultad f ON g.facultad_id = f.id
                WHERE g.estado = 4
            ";
            if ($dateFilter['clause']) {
                $gruposQuery .= ' AND ' . $dateFilter['clause'];
            }
            $gruposQuery .= ' ORDER BY g.id LIMIT ? OFFSET ?';

            $grupos = DB::select($gruposQuery, [...$dateFilter['params'], $remaining, max(0, $currentOffset)]);

            foreach ($grupos as $g) {
                $results[] = $this->buildGrupoRecord((array) $g);
            }
        }

        return $results;
    }

    private function findFacultadById(string $id): ?array
    {
        $rows = DB::select('SELECT * FROM Facultad WHERE id = ?', [$id]);
        if (count($rows) === 0) {
            return null;
        }
        return $this->buildFacultadRecord((array) $rows[0]);
    }

    private function findInstitutoById(string $id): ?array
    {
        $rows = DB::select("
            SELECT i.*, f.nombre as facultad_nombre
            FROM Instituto i
            LEFT JOIN Facultad f ON i.facultad_id = f.id
            WHERE i.id = ? AND i.estado = 1
        ", [$id]);

        if (count($rows) === 0) {
            return null;
        }
        return $this->buildInstitutoRecord((array) $rows[0]);
    }

    private function findGrupoById(string $id): ?array
    {
        $rows = DB::select("
            SELECT g.*, f.nombre as facultad_nombre
            FROM Grupo g
            LEFT JOIN Facultad f ON g.facultad_id = f.id
            WHERE g.id = ? AND g.estado = 4
        ", [$id]);

        if (count($rows) === 0) {
            return null;
        }
        return $this->buildGrupoRecord((array) $rows[0]);
    }

    private function buildUnmsmRecord(): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, self::UNMSM_ROOT['id']),
                'datestamp' => CerifFormatter::nowISO8601(),
                'setSpec' => 'orgunits',
            ],
            'metadata' => [
                'OrgUnit' => [
                    '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, self::UNMSM_ROOT['id']),
                    '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
                    'name' => CerifFormatter::filterEmpty([CerifFormatter::createTitle(self::UNMSM_ROOT['nombre'])]),
                    'acronym' => self::UNMSM_ROOT['acronym'],
                    'type' => 'Universidad',
                    'identifiers' => CerifFormatter::filterEmpty([
                        CerifFormatter::createIdentifier(self::SCHEME_RUC, self::UNMSM_ROOT['ruc']),
                        CerifFormatter::createIdentifier(self::SCHEME_ROR, self::UNMSM_ROOT['ror']),
                        CerifFormatter::createIdentifier(self::SCHEME_ISNI, self::UNMSM_ROOT['isni']),
                        CerifFormatter::createIdentifier(self::SCHEME_GRID, self::UNMSM_ROOT['grid']),
                        CerifFormatter::createIdentifier(self::SCHEME_SCOPUS_AFFIL, self::UNMSM_ROOT['scopusAffiliationId']),
                    ]),
                    'countryCode' => self::UNMSM_ROOT['countryCode'],
                    'classifications' => [
                        ['scheme' => 'https://purl.org/pe-repo/inei/ubigeo', 'value' => self::UNMSM_ROOT['ubigeo']],
                        ['scheme' => 'https://purl.org/pe-repo/inei/ciiu', 'value' => self::UNMSM_ROOT['ciiu']],
                        ['scheme' => 'https://purl.org/pe-repo/ocde/sector', 'value' => self::UNMSM_ROOT['sectorOcde']],
                    ],
                ],
            ],
        ];
    }

    private function buildFacultadRecord(array $row): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, 'F' . $row['id']),
                'datestamp' => CerifFormatter::nowISO8601(),
                'setSpec' => 'orgunits',
            ],
            'metadata' => [
                'OrgUnit' => $this->mapFacultadToCerif($row),
            ],
        ];
    }

    private function buildInstitutoRecord(array $row): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, 'I' . $row['id']),
                'datestamp' => CerifFormatter::nowISO8601(),
                'setSpec' => 'orgunits',
            ],
            'metadata' => [
                'OrgUnit' => $this->mapInstitutoToCerif($row),
            ],
        ];
    }

    private function buildGrupoRecord(array $row): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, 'G' . $row['id']),
                'datestamp' => CerifFormatter::toISO8601($row['updated_at'] ?? 'now'),
                'setSpec' => 'orgunits',
            ],
            'metadata' => [
                'OrgUnit' => $this->mapGrupoToCerif($row),
            ],
        ];
    }

    private function mapFacultadToCerif(array $row): array
    {
        $orgUnit = [
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, 'F' . $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'name' => CerifFormatter::filterEmpty([CerifFormatter::createTitle($row['nombre'])]),
            'type' => 'Facultad',
            'partOf' => [
                'orgUnit' => [
                    '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, self::UNMSM_ROOT['id']),
                    'name' => self::UNMSM_ROOT['nombre'],
                ],
            ],
            'classifications' => [
                ['scheme' => 'https://purl.org/pe-repo/inei/ubigeo', 'value' => self::UNMSM_ROOT['ubigeo']],
                ['scheme' => 'https://purl.org/pe-repo/inei/ciiu', 'value' => self::UNMSM_ROOT['ciiu']],
                ['scheme' => 'https://purl.org/pe-repo/ocde/sector', 'value' => self::UNMSM_ROOT['sectorOcde']],
            ],
        ];

        return $orgUnit;
    }

    private function mapInstitutoToCerif(array $row): array
    {
        $orgUnit = [
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, 'I' . $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'name' => CerifFormatter::filterEmpty([CerifFormatter::createTitle($row['instituto'])]),
            'type' => 'Instituto',
            'classifications' => [
                ['scheme' => 'https://purl.org/pe-repo/inei/ubigeo', 'value' => self::UNMSM_ROOT['ubigeo']],
                ['scheme' => 'https://purl.org/pe-repo/inei/ciiu', 'value' => self::UNMSM_ROOT['ciiu']],
                ['scheme' => 'https://purl.org/pe-repo/ocde/sector', 'value' => self::UNMSM_ROOT['sectorOcde']],
            ],
        ];

        if ($row['facultad_id'] ?? null && $row['facultad_nombre'] ?? null) {
            $orgUnit['partOf'] = [
                'orgUnit' => [
                    '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, 'F' . $row['facultad_id']),
                    'name' => $row['facultad_nombre'],
                ],
            ];
        }

        return $orgUnit;
    }

    private function mapGrupoToCerif(array $row): array
    {
        $orgUnit = [
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, 'G' . $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'name' => CerifFormatter::filterEmpty([CerifFormatter::createTitle($row['grupo_nombre'])]),
            'type' => 'Grupo de investigacion',
        ];

        if ($row['grupo_nombre_corto'] ?? null) {
            $orgUnit['acronym'] = $row['grupo_nombre_corto'];
        }

        if ($row['facultad_id'] ?? null && $row['facultad_nombre'] ?? null) {
            $orgUnit['partOf'] = [
                'orgUnit' => [
                    '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, 'F' . $row['facultad_id']),
                    'name' => $row['facultad_nombre'],
                ],
            ];
        }

        if ($row['email'] ?? null) {
            $orgUnit['electronicAddress'] = [['type' => 'email', 'value' => $row['email']]];
        }

        if ($row['web'] ?? null) {
            $orgUnit['websites'] = [['type' => 'homepage', 'url' => $row['web']]];
        }

        if ($row['direccion'] ?? null) {
            $orgUnit['address'] = ['street' => $row['direccion']];
        }

        if ($row['presentacion'] ?? null) {
            $orgUnit['description'] = [['value' => $row['presentacion']]];
        }

        $classifications = [
            ['scheme' => 'https://purl.org/pe-repo/inei/ubigeo', 'value' => self::UNMSM_ROOT['ubigeo']],
            ['scheme' => 'https://purl.org/pe-repo/inei/ciiu', 'value' => self::UNMSM_ROOT['ciiu']],
            ['scheme' => 'https://purl.org/pe-repo/ocde/sector', 'value' => self::UNMSM_ROOT['sectorOcde']],
        ];

        if ($row['grupo_categoria'] ?? null) {
            $classifications[] = ['scheme' => self::SCHEME_ORG_TYPE, 'value' => $row['grupo_categoria']];
        }

        $orgUnit['classifications'] = array_values(array_filter($classifications, fn($c) => $c['value'] !== null));

        return $orgUnit;
    }
}
