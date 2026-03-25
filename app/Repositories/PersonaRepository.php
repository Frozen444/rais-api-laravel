<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use Illuminate\Support\Facades\DB;

class PersonaRepository
{
    private const ENTITY_TYPE = 'Persons';

    private const FALLBACK_DATE = '2014-01-01T00:00:00Z';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    private const SCHEME_DNI = 'http://purl.org/pe-repo/concytec/terminos#dni';

    private const SCHEME_ORCID = 'https://orcid.org';

    private const SCHEME_SCOPUS = 'https://w3id.org/cerif/vocab/IdentifierTypes#ScopusAuthorID';

    private const SCHEME_RESEARCHER = 'https://w3id.org/cerif/vocab/IdentifierTypes#ResearcherID';

    private const GENDER_MAP = [
        'M' => 'm',
        'F' => 'f',
    ];

    public function findById(int $id): ?array
    {
        $rows = DB::select(
            '
                SELECT
                    ui.*,
                    f.id as facultad_id,
                    f.nombre as facultad_nombre,
                    i.id as instituto_id,
                    i.instituto as instituto_nombre
                FROM Usuario_investigador ui
                LEFT JOIN Facultad f ON ui.facultad_id = f.id
                LEFT JOIN Instituto i ON ui.instituto_id = i.id
                WHERE ui.id = ?
                  AND ui.estado = 1
                LIMIT 1
            ',
            [$id]
        );

        if (count($rows) === 0) {
            return null;
        }

        return $this->buildRecord((array) $rows[0]);
    }

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'ui.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Usuario_investigador ui WHERE ui.estado = 1';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $rows = DB::select($query, $dateFilter['params']);

        return (int) ($rows[0]->total ?? 0);
    }

    public function countDeleted(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'ui.updated_at');
        $query = 'SELECT COUNT(*) as total FROM Usuario_investigador ui WHERE ui.estado = 0';

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

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'ui.updated_at');

        $query = '
            SELECT ui.id, ui.updated_at
            FROM Usuario_investigador ui
            WHERE ui.estado = 1
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY ui.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row->id),
                'datestamp' => CerifFormatter::toISO8601($row->updated_at) ?? self::FALLBACK_DATE,
                'setSpec' => 'persons',
            ];
        }, $rows);
    }

    public function getDeletedIdentifiers(array $filters): array
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 100);
        $from = $filters['from'] ?? null;
        $until = $filters['until'] ?? null;

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'ui.updated_at');

        $query = '
            SELECT ui.id, ui.updated_at
            FROM Usuario_investigador ui
            WHERE ui.estado = 0
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY ui.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row->id),
                'datestamp' => CerifFormatter::toISO8601($row->updated_at) ?? self::FALLBACK_DATE,
                'setSpec' => 'persons',
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

        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'ui.updated_at');

        $query = '
            SELECT
                ui.*,
                f.id as facultad_id,
                f.nombre as facultad_nombre,
                i.id as instituto_id,
                i.instituto as instituto_nombre
            FROM Usuario_investigador ui
            LEFT JOIN Facultad f ON ui.facultad_id = f.id
            LEFT JOIN Instituto i ON ui.instituto_id = i.id
            WHERE ui.estado = 1
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY ui.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            return $this->buildRecord((array) $row);
        }, $rows);
    }

    private function buildRecord(array $row): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $row['id']),
                'datestamp' => CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE,
                'setSpec' => 'persons',
            ],
            'metadata' => [
                'Person' => $this->mapToCerif($row),
            ],
        ];
    }

    private function mapToCerif(array $row): array
    {
        $fullName = CerifFormatter::formatFullName(
            $row['nombres'] ?? null,
            $row['apellido1'] ?? null,
            $row['apellido2'] ?? null,
        ) ?: ('Investigador '.$row['id']);

        $identifiers = CerifFormatter::filterEmpty([
            (($row['doc_tipo'] ?? null) === 'DNI' && preg_match('/^\d{8}$/', (string) ($row['doc_numero'] ?? '')) === 1)
                ? CerifFormatter::createIdentifier(self::SCHEME_DNI, (string) $row['doc_numero'])
                : null,
            ! empty($row['codigo_orcid'])
                ? CerifFormatter::createIdentifier(self::SCHEME_ORCID, $this->normalizeOrcid((string) $row['codigo_orcid']))
                : null,
            (! empty($row['scopus_id']) && (string) $row['scopus_id'] !== '0')
                ? CerifFormatter::createIdentifier(self::SCHEME_SCOPUS, (string) $row['scopus_id'])
                : null,
            (! empty($row['researcher_id']) && (string) $row['researcher_id'] !== '0')
                ? CerifFormatter::createIdentifier(self::SCHEME_RESEARCHER, (string) $row['researcher_id'])
                : null,
        ]);

        $emails = CerifFormatter::filterEmpty([
            $this->normalizeContact($row['email1'] ?? null),
            $this->normalizeContact($row['email2'] ?? null),
            $this->normalizeContact($row['email3'] ?? null),
        ]);

        $affiliations = [];

        if (! empty($row['facultad_id']) && ! empty($row['facultad_nombre'])) {
            $affiliations[] = [
                'orgUnit' => [
                    'id' => CerifFormatter::toCerifId('OrgUnits', 'F'.$row['facultad_id']),
                    'name' => $row['facultad_nombre'],
                ],
                'role' => 'Investigador',
            ];

            if (! empty($row['instituto_id']) && ! empty($row['instituto_nombre'])) {
                $affiliations[] = [
                    'orgUnit' => [
                        'id' => CerifFormatter::toCerifId('OrgUnits', 'I'.$row['instituto_id']),
                        'name' => $row['instituto_nombre'],
                    ],
                    'role' => 'Investigador',
                ];
            }
        }

        $person = [
            'id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'personName' => [
                'familyNames' => CerifFormatter::formatFamilyNames($row['apellido1'] ?? null, $row['apellido2'] ?? null),
                'firstNames' => $row['nombres'] ?? $fullName,
                'fullName' => $fullName,
            ],
            'lastModified' => CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE,
        ];

        if (! empty($row['sexo']) && isset(self::GENDER_MAP[$row['sexo']])) {
            $person['gender'] = self::GENDER_MAP[$row['sexo']];
        }

        if (count($identifiers) > 0) {
            $person['identifiers'] = $identifiers;
        }

        if (count($emails) > 0) {
            $person['emails'] = $emails;
        }

        if (count($affiliations) > 0) {
            $person['affiliations'] = $affiliations;
        }

        if (! empty($row['palabras_clave'])) {
            $person['keywords'] = array_values(array_filter(array_map(function ($keyword) {
                $value = trim((string) $keyword);

                return $value !== '' ? ['value' => $value] : null;
            }, explode(',', (string) $row['palabras_clave']))));
        }

        return $person;
    }

    private function normalizeOrcid(string $orcid): ?string
    {
        $value = trim($orcid);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return 'https://orcid.org/'.$value;
    }

    private function normalizeContact(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'mailto:') || str_starts_with($trimmed, 'tel:') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, 'http://')) {
            return 'https://'.substr($trimmed, 7);
        }

        if (preg_match('/^\+?[0-9\s\-()]{6,}$/', $trimmed) === 1) {
            return 'tel:'.preg_replace('/\s+/', '', $trimmed);
        }

        if (str_contains($trimmed, '@')) {
            return 'mailto:'.strtolower($trimmed);
        }

        return 'https://'.$trimmed;
    }
}
