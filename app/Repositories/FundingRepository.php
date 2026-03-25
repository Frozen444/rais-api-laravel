<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use DateTime;
use Illuminate\Support\Facades\DB;

class FundingRepository
{
    private const ENTITY_TYPE = 'Fundings';

    private const FALLBACK_DATE = '2014-01-01T00:00:00Z';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    private const OPENAIRE_FUNDING_TYPES = 'https://www.openaire.eu/cerif-profile/vocab/OpenAIRE_Funding_Types';

    private const SCHEME_AWARD_NUMBER = 'https://w3id.org/cerif/vocab/IdentifierTypes#AwardNumber';

    private const FUNDING_ELIGIBILITY = "
        (
            (p.codigo_proyecto IS NOT NULL AND p.codigo_proyecto <> '')
            OR (COALESCE(p.aporte_unmsm, 0) + COALESCE(p.aporte_no_unmsm, 0) + COALESCE(p.financiamiento_fuente_externa, 0) + COALESCE(p.entidad_asociada, 0)) > 0
            OR p.convocatoria IS NOT NULL
        )
    ";

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');

        $query = '
            SELECT COUNT(*) as total
            FROM Proyecto p
            WHERE p.estado >= 1
              AND '.self::FUNDING_ELIGIBILITY;

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
              AND '.self::FUNDING_ELIGIBILITY;

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, 'P'.$row->id),
                'datestamp' => CerifFormatter::toISO8601($row->updated_at) ?? self::FALLBACK_DATE,
                'setSpec' => 'fundings',
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

        $query = $this->getBaseFundingSelect();

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return $this->buildRecord((array) $row);
        }, $rows);
    }

    public function findById(string|int $id): ?array
    {
        $projectId = $this->parseFundingProjectId($id);

        if ($projectId === null) {
            return null;
        }

        $query = $this->getBaseFundingSelect().' AND p.id = ? LIMIT 1';
        $rows = DB::select($query, [$projectId]);

        if (count($rows) === 0) {
            return null;
        }

        return $this->buildRecord((array) $rows[0]);
    }

    private function parseFundingProjectId(string|int $id): ?int
    {
        $value = trim((string) $id);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'P')) {
            $value = substr($value, 1);
        }

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private function getBaseFundingSelect(): string
    {
        return '
            SELECT
                p.id,
                p.codigo_proyecto,
                p.titulo,
                p.tipo_proyecto,
                p.fecha_inicio,
                p.fecha_fin,
                p.periodo,
                p.convocatoria,
                p.aporte_unmsm,
                p.aporte_no_unmsm,
                p.financiamiento_fuente_externa,
                p.entidad_asociada,
                p.updated_at,
                f.nombre as facultad_nombre,
                sv.monto_subvencion
            FROM Proyecto p
            LEFT JOIN Facultad f ON f.id = p.facultad_id
            LEFT JOIN (
                SELECT
                    proyecto_id,
                    SUM(monto_subvencion) as monto_subvencion
                FROM view_subvencion_investigadores
                GROUP BY proyecto_id
            ) sv ON sv.proyecto_id = p.id
            WHERE p.estado >= 1
              AND '.self::FUNDING_ELIGIBILITY;
    }

    private function buildFundingType(array $row): array
    {
        $externalTotal =
            (float) ($row['aporte_no_unmsm'] ?? 0)
            + (float) ($row['financiamiento_fuente_externa'] ?? 0)
            + (float) ($row['entidad_asociada'] ?? 0);

        $value = $externalTotal > 0
            ? self::OPENAIRE_FUNDING_TYPES.'#Grant'
            : self::OPENAIRE_FUNDING_TYPES.'#InternalFunding';

        return [
            'scheme' => self::OPENAIRE_FUNDING_TYPES,
            'value' => $value,
        ];
    }

    private function buildFundingAmount(array $row): ?array
    {
        $total =
            (float) ($row['aporte_unmsm'] ?? 0)
            + (float) ($row['aporte_no_unmsm'] ?? 0)
            + (float) ($row['financiamiento_fuente_externa'] ?? 0)
            + (float) ($row['entidad_asociada'] ?? 0);

        if ($total <= 0) {
            return null;
        }

        return [
            'value' => (int) round($total),
            'currency' => 'PEN',
        ];
    }

    private function mapToCerif(array $row): array
    {
        $fundingId = 'P'.$row['id'];
        $lastModified = CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE;

        $funding = [
            'id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $fundingId),
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $fundingId),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'type' => $this->buildFundingType($row),
            'title' => CerifFormatter::filterEmpty([
                CerifFormatter::createTitle('Financiamiento de proyecto '.($row['codigo_proyecto'] ?: $row['id']), 'es'),
                ! empty($row['titulo']) ? CerifFormatter::createTitle($row['titulo'], 'es') : null,
            ]),
            'identifiers' => CerifFormatter::filterEmpty([
                CerifFormatter::createIdentifier(self::SCHEME_AWARD_NUMBER, $row['codigo_proyecto'] ?: 'P-'.$row['id']),
            ]),
            'fundedBy' => [
                'orgUnit' => [
                    'id' => CerifFormatter::toCerifId('OrgUnits', '1'),
                    'acronym' => 'UNMSM',
                    'name' => 'Universidad Nacional Mayor de San Marcos',
                ],
            ],
            'relatedProjects' => [CerifFormatter::toCerifId('Projects', $row['id'])],
            'lastModified' => $lastModified,
        ];

        if (! empty($row['convocatoria'])) {
            $funding['partOf'] = [
                'id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, 'C'.$row['convocatoria']),
            ];
        }

        $startDate = $this->toDateOnly($row['fecha_inicio'] ?? null);
        if (! $startDate && ! empty($row['periodo'])) {
            $startDate = (string) $row['periodo'];
        }
        if ($startDate) {
            $funding['startDate'] = $startDate;
        }

        $endDate = $this->toDateOnly($row['fecha_fin'] ?? null);
        if ($endDate) {
            $funding['endDate'] = $endDate;
        }

        $amount = $this->buildFundingAmount($row);
        if ($amount !== null) {
            $funding['amount'] = $amount;
        }

        if (! empty($row['monto_subvencion']) && (float) $row['monto_subvencion'] > 0) {
            $funding['executedAmount'] = [
                'value' => (int) round((float) $row['monto_subvencion']),
                'currency' => 'PEN',
            ];
        }

        $descriptionParts = [];
        if (! empty($row['tipo_proyecto'])) {
            $descriptionParts[] = 'Tipo de proyecto: '.$row['tipo_proyecto'];
        }
        if (! empty($row['facultad_nombre'])) {
            $descriptionParts[] = 'Facultad: '.$row['facultad_nombre'];
        }
        if (count($descriptionParts) > 0) {
            $funding['description'] = [[
                'lang' => 'es',
                'value' => implode('. ', $descriptionParts),
            ]];
        }

        if (! empty($row['tipo_proyecto'])) {
            $funding['keywords'] = [
                ['value' => $row['tipo_proyecto']],
            ];
        }

        return $funding;
    }

    private function buildRecord(array $row): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, 'P'.$row['id']),
                'datestamp' => CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE,
                'setSpec' => 'fundings',
            ],
            'metadata' => [
                'Funding' => $this->mapToCerif($row),
            ],
        ];
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
