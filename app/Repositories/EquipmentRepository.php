<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use Illuminate\Support\Facades\DB;

class EquipmentRepository
{
    private const ENTITY_TYPE = 'Equipments';

    private const FALLBACK_DATE = '2014-01-01T00:00:00Z';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    private const SCHEME_CRIS_ID = 'https://w3id.org/cerif/vocab/IdentifierTypes#CRISID';

    private const VOCAB_OCDE_FORD = 'https://purl.org/pe-repo/ocde/ford';

    private const CTI_TERMS_SCHEME = 'https://purl.org/pe-repo/concytec/terminos';

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'gi.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Grupo_infraestructura gi WHERE gi.id IS NOT NULL';

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

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'gi.updated_at');
        $query = 'SELECT gi.id, gi.updated_at FROM Grupo_infraestructura gi WHERE gi.id IS NOT NULL';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY gi.id LIMIT ? OFFSET ?';
        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row->id),
                'datestamp' => CerifFormatter::toISO8601($row->updated_at) ?? self::FALLBACK_DATE,
                'setSpec' => 'equipments',
            ];
        }, $rows);
    }

    public function findAll(array $filters): array
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 100);
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'gi.updated_at');

        $query = "
            SELECT
                gi.id,
                gi.codigo,
                gi.nombre,
                gi.descripcion,
                gi.grupo_id,
                g.grupo_nombre,
                gi.categoria,
                gi.ubicacion,
                gi.valor_estimado,
                gi.area_mt2,
                gi.contacto,
                gp.ocde_value,
                gi.updated_at
            FROM Grupo_infraestructura gi
            LEFT JOIN Grupo g ON gi.grupo_id = g.id
            LEFT JOIN (
                SELECT
                    p.grupo_id,
                    MIN(COALESCE(NULLIF(TRIM(o.codigo), ''), NULLIF(TRIM(o.linea), ''))) as ocde_value
                FROM Proyecto p
                LEFT JOIN Ocde o ON p.ocde_id = o.id
                WHERE p.grupo_id IS NOT NULL
                  AND p.estado >= 1
                  AND (
                    (o.codigo IS NOT NULL AND TRIM(o.codigo) <> '')
                    OR (o.linea IS NOT NULL AND TRIM(o.linea) <> '')
                  )
                GROUP BY p.grupo_id
            ) gp ON gp.grupo_id = gi.grupo_id
            WHERE gi.id IS NOT NULL
        ";

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY gi.id LIMIT ? OFFSET ?';
        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return $this->buildRecord((array) $row);
        }, $rows);
    }

    public function findById(string|int $id): ?array
    {
        $rows = DB::select(
            "
                SELECT
                    gi.id,
                    gi.codigo,
                    gi.nombre,
                    gi.descripcion,
                    gi.grupo_id,
                    g.grupo_nombre,
                    gi.categoria,
                    gi.ubicacion,
                    gi.valor_estimado,
                    gi.area_mt2,
                    gi.contacto,
                    gp.ocde_value,
                    gi.updated_at
                FROM Grupo_infraestructura gi
                LEFT JOIN Grupo g ON gi.grupo_id = g.id
                LEFT JOIN (
                    SELECT
                        p.grupo_id,
                        MIN(COALESCE(NULLIF(TRIM(o.codigo), ''), NULLIF(TRIM(o.linea), ''))) as ocde_value
                    FROM Proyecto p
                    LEFT JOIN Ocde o ON p.ocde_id = o.id
                    WHERE p.grupo_id IS NOT NULL
                      AND p.estado >= 1
                      AND (
                        (o.codigo IS NOT NULL AND TRIM(o.codigo) <> '')
                        OR (o.linea IS NOT NULL AND TRIM(o.linea) <> '')
                      )
                    GROUP BY p.grupo_id
                ) gp ON gp.grupo_id = gi.grupo_id
                WHERE gi.id = ?
                LIMIT 1
            ",
            [$id]
        );

        if (count($rows) === 0) {
            return null;
        }

        return $this->buildRecord((array) $rows[0]);
    }

    private function mapToCerif(array $row): array
    {
        $equipmentId = CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']);

        $equipment = [
            'id' => $equipmentId,
            '@id' => $equipmentId,
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'identifiers' => CerifFormatter::filterEmpty([
                CerifFormatter::createIdentifier(self::SCHEME_CRIS_ID, $row['codigo'] ?: 'GI-'.$row['id']),
            ]),
            'type' => $this->getEquipmentType($row),
            'name' => CerifFormatter::filterEmpty([
                CerifFormatter::createTitle($row['nombre'] ?: 'Equipamiento '.$row['id'], 'es'),
            ]),
            'owner' => [
                'orgUnit' => [
                    'id' => ! empty($row['grupo_id'])
                        ? CerifFormatter::toCerifId('OrgUnits', 'G'.$row['grupo_id'])
                        : CerifFormatter::toCerifId('OrgUnits', '1'),
                    'name' => $row['grupo_nombre'] ?: 'Universidad Nacional Mayor de San Marcos',
                ],
            ],
            'lastModified' => CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE,
        ];

        if (! empty($row['descripcion'])) {
            $equipment['description'] = [[
                'lang' => 'es',
                'value' => $row['descripcion'],
            ]];
        }

        if (! empty($row['ubicacion'])) {
            $equipment['location'] = [
                'campus' => $row['ubicacion'],
            ];
        }

        if (! empty($row['valor_estimado']) && (float) $row['valor_estimado'] > 0) {
            $equipment['acquisitionAmount'] = [
                'value' => (int) round((float) $row['valor_estimado']),
                'currency' => 'PEN',
            ];
        }

        if (! empty($row['contacto'])) {
            $equipment['contact'] = [
                'value' => $row['contacto'],
            ];
        }

        if (! empty($row['area_mt2']) && (float) $row['area_mt2'] > 0) {
            $equipment['area'] = [
                'value' => (float) $row['area_mt2'],
                'unit' => 'm2',
            ];
        }

        if (! empty($row['ocde_value'])) {
            $equipment['subjects'] = [[
                'scheme' => self::VOCAB_OCDE_FORD,
                'value' => self::VOCAB_OCDE_FORD.'#'.$row['ocde_value'],
            ]];
        }

        return $equipment;
    }

    private function buildRecord(array $row): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row['id']),
                'datestamp' => CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE,
                'setSpec' => 'equipments',
            ],
            'metadata' => [
                'Equipment' => $this->mapToCerif($row),
            ],
        ];
    }

    private function getEquipmentType(array $row): string
    {
        $slug = $this->toSlug($row['categoria'] ?? null);

        if ($slug === '') {
            return self::CTI_TERMS_SCHEME.'#equipamiento-cientifico';
        }

        return self::CTI_TERMS_SCHEME.'#'.$slug;
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
}
