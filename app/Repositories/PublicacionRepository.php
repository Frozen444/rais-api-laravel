<?php

namespace App\Repositories;

use App\Services\CerifFormatter;
use DateTime;
use Illuminate\Support\Facades\DB;

class PublicacionRepository
{
    private const ENTITY_TYPE = 'Publications';

    private const FALLBACK_DATE = '2014-01-01T00:00:00Z';

    private const NAMESPACE_PERUCRIS_CERIF = 'https://purl.org/pe-repo/perucris/cerif';

    private const VOCAB_COAR_PUBLICATION_TYPES = 'https://www.openaire.eu/cerif-profile/vocab/COAR_Publication_Types';

    private const VOCAB_OCDE_FORD = 'https://purl.org/pe-repo/ocde/ford';

    public function findById(int $id): ?array
    {
        $rows = DB::select(
            '
                SELECT p.*
                FROM Publicacion p
                WHERE p.id = ?
                  AND p.estado = 1
                  AND p.validado = 1
                LIMIT 1
            ',
            [$id]
        );

        if (count($rows) === 0) {
            return null;
        }

        $publication = (array) $rows[0];
        $context = $this->getPublicationContext((int) $publication['id']);

        return $this->buildRecord($publication, $context);
    }

    public function countAll(?string $from = null, ?string $until = null): int
    {
        $dateFilter = CerifFormatter::buildDateFilter($from, $until, 'p.updated_at');

        $query = '
            SELECT COUNT(*) as total
            FROM Publicacion p
            WHERE p.estado = 1
              AND p.validado = 1
        ';

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
            FROM Publicacion p
            WHERE p.estado = 1
              AND p.validado = 1
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
                'setSpec' => 'publications',
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
            FROM Publicacion p
            WHERE p.estado = 1
              AND p.validado = 1
        ';

        if ($dateFilter['clause']) {
            $query .= ' AND '.$dateFilter['clause'];
        }

        $query .= ' ORDER BY p.id LIMIT ? OFFSET ?';

        $params = [...$dateFilter['params'], $limit, $offset];
        $rows = DB::select($query, $params);

        return array_map(function ($row) {
            $publication = (array) $row;
            $context = $this->getPublicationContext((int) $publication['id']);

            return $this->buildRecord($publication, $context);
        }, $rows);
    }

    private function getPublicationContext(int $publicationId): array
    {
        $authors = DB::select(
            '
                SELECT
                    pa.*,
                    ui.codigo_orcid,
                    ui.doc_numero,
                    f.id as facultad_id,
                    f.nombre as facultad_nombre
                FROM Publicacion_autor pa
                LEFT JOIN Usuario_investigador ui ON pa.investigador_id = ui.id
                LEFT JOIN Facultad f ON ui.facultad_id = f.id
                WHERE pa.publicacion_id = ?
                ORDER BY pa.orden ASC, pa.id ASC
            ',
            [$publicationId]
        );

        $keywords = DB::select(
            '
                SELECT clave as palabra_clave
                FROM Publicacion_palabra_clave
                WHERE publicacion_id = ?
            ',
            [$publicationId]
        );

        $originRows = DB::select(
            '
                SELECT DISTINCT
                    pp.proyecto_id,
                    o.codigo as ocde_codigo
                FROM Publicacion_proyecto pp
                LEFT JOIN Proyecto p ON pp.proyecto_id = p.id
                LEFT JOIN Ocde o ON p.ocde_id = o.id
                WHERE pp.publicacion_id = ?
                  AND pp.proyecto_id IS NOT NULL
                  AND IFNULL(pp.estado, 1) = 1
            ',
            [$publicationId]
        );

        $projectIds = [];
        $ocdeCodes = [];

        foreach ($originRows as $originRow) {
            if (! empty($originRow->proyecto_id)) {
                $projectIds[] = (int) $originRow->proyecto_id;
            }

            if (! empty($originRow->ocde_codigo)) {
                $ocdeCodes[] = (string) $originRow->ocde_codigo;
            }
        }

        return [
            'authors' => array_map(fn ($row) => (array) $row, $authors),
            'keywords' => array_map(fn ($row) => (array) $row, $keywords),
            'projectIds' => array_values(array_unique($projectIds)),
            'ocdeCodes' => array_values(array_unique($ocdeCodes)),
        ];
    }

    private function buildRecord(array $publication, array $context): array
    {
        return [
            'header' => [
                'identifier' => CerifFormatter::toOAIIdentifier(self::ENTITY_TYPE, $publication['id']),
                'datestamp' => CerifFormatter::toISO8601($publication['updated_at'] ?? null) ?? self::FALLBACK_DATE,
                'setSpec' => 'publications',
            ],
            'metadata' => [
                'Publication' => $this->mapToCerif($publication, $context),
            ],
        ];
    }

    private function mapToCerif(array $row, array $context): array
    {
        $authors = $context['authors'] ?? [];
        $keywords = $context['keywords'] ?? [];
        $projectIds = $context['projectIds'] ?? [];
        $ocdeCodes = $context['ocdeCodes'] ?? [];

        $typeUri = CerifFormatter::PUBLICATION_TYPE_MAP[$row['tipo_publicacion'] ?? '']
            ?? CerifFormatter::PUBLICATION_TYPE_MAP['default'];

        $lastModified = CerifFormatter::toISO8601($row['updated_at'] ?? null) ?? self::FALLBACK_DATE;
        $titleValue = $row['titulo'] ?? $row['publicacion_nombre'] ?? ('Publicación '.$row['id']);

        $publication = [
            'id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@id' => CerifFormatter::toCerifId(self::ENTITY_TYPE, $row['id']),
            '@xmlns' => self::NAMESPACE_PERUCRIS_CERIF,
            'type' => [
                'scheme' => self::VOCAB_COAR_PUBLICATION_TYPES,
                'value' => $typeUri,
            ],
            'title' => CerifFormatter::filterEmpty([
                CerifFormatter::createTitle($titleValue, 'es'),
            ]),
            'access' => CerifFormatter::inferAccessRights($row)['uri'],
            'lastModified' => $lastModified,
        ];

        $identifiers = CerifFormatter::filterEmpty([
            CerifFormatter::createTypedIdentifier('DOI', $row['doi'] ?? null),
            CerifFormatter::createTypedIdentifier('ISBN', $row['isbn'] ?? null),
            CerifFormatter::createTypedIdentifier('ISSN', $row['issn'] ?? null),
            CerifFormatter::createTypedIdentifier('ISSN', $row['issn_e'] ?? null),
            CerifFormatter::createTypedIdentifier('Handle', $row['uri'] ?? null),
            CerifFormatter::createTypedIdentifier('URL', $row['url'] ?? null),
        ]);

        if (count($identifiers) > 0) {
            $publication['identifiers'] = $identifiers;
        }

        if (count($authors) > 0) {
            $publication['authors'] = array_map(function ($author, $index) {
                $fullName = trim((string) ($author['autor'] ?? ''));

                if ($fullName === '') {
                    $fullName = trim(implode(' ', array_filter([
                        $author['nombres'] ?? null,
                        $author['apellido1'] ?? null,
                        $author['apellido2'] ?? null,
                    ])));
                }

                $person = [
                    'personName' => [
                        'fullName' => $fullName,
                        'familyNames' => trim(implode(' ', array_filter([
                            $author['apellido1'] ?? null,
                            $author['apellido2'] ?? null,
                        ]))),
                        'firstNames' => $author['nombres'] ?? '',
                    ],
                ];

                if (! empty($author['investigador_id'])) {
                    $person['id'] = CerifFormatter::toCerifId('Persons', $author['investigador_id']);
                }

                $personIdentifiers = [];
                if (! empty($author['doc_numero'])) {
                    $personIdentifiers[] = [
                        'scheme' => 'http://purl.org/pe-repo/concytec/terminos#dni',
                        'value' => (string) $author['doc_numero'],
                    ];
                }
                if (! empty($author['codigo_orcid'])) {
                    $personIdentifiers[] = [
                        'scheme' => 'https://orcid.org',
                        'value' => $this->normalizeOrcid((string) $author['codigo_orcid']),
                    ];
                }
                if (count($personIdentifiers) > 0) {
                    $person['identifiers'] = $personIdentifiers;
                }

                $authorEntry = [
                    'person' => $person,
                    'order' => (int) ($author['orden'] ?? ($index + 1)),
                ];

                if (! empty($author['facultad_id']) && ! empty($author['facultad_nombre'])) {
                    $authorEntry['affiliations'] = [
                        [
                            'orgUnit' => [
                                'id' => CerifFormatter::toCerifId('OrgUnits', 'F'.$author['facultad_id']),
                                'name' => $author['facultad_nombre'],
                            ],
                        ],
                    ];
                }

                return $authorEntry;
            }, $authors, array_keys($authors));
        }

        if (! empty($row['publicacion_nombre'])) {
            $publication['publishedIn'] = [
                'publication' => [
                    'id' => CerifFormatter::toCerifId('Publications', 'SRC-'.$row['id']),
                    'title' => [
                        ['value' => $row['publicacion_nombre']],
                    ],
                ],
            ];
        }

        if (! empty($row['editorial'])) {
            $publication['publishers'] = [
                [
                    'orgUnit' => [
                        'name' => [
                            ['value' => $row['editorial']],
                        ],
                    ],
                ],
            ];
        }

        $publicationDate = $this->toDateOnly($row['fecha_publicacion'] ?? null);
        if ($publicationDate) {
            $publication['publicationDate'] = $publicationDate;
        }

        if (! empty($row['volumen'])) {
            $publication['volume'] = (string) $row['volumen'];
        }
        if (! empty($row['edicion'])) {
            $publication['edition'] = (string) $row['edicion'];
        }
        if (! empty($row['pagina_inicial'])) {
            $publication['startPage'] = (string) $row['pagina_inicial'];
        }
        if (! empty($row['pagina_final'])) {
            $publication['endPage'] = (string) $row['pagina_final'];
        }

        if (! empty($row['idioma'])) {
            $publication['language'] = [strtolower((string) $row['idioma'])];
        }

        if (! empty($row['resumen'])) {
            $summary = trim((string) $row['resumen']);
            if ($summary !== '') {
                $publication['abstract'] = [[
                    'lang' => 'es',
                    'value' => $summary,
                ]];
            }
        }

        if (count($keywords) > 0) {
            $keywordItems = [];
            foreach ($keywords as $keyword) {
                $value = trim((string) ($keyword['palabra_clave'] ?? ''));
                if ($value !== '') {
                    $keywordItems[] = [
                        'lang' => 'es',
                        'value' => $value,
                    ];
                }
            }

            if (count($keywordItems) > 0) {
                $publication['keywords'] = $keywordItems;
            }
        }

        if (count($ocdeCodes) > 0) {
            $publication['subjects'] = array_map(function ($code) {
                return [
                    'scheme' => self::VOCAB_OCDE_FORD,
                    'value' => self::VOCAB_OCDE_FORD.'#'.$code,
                ];
            }, $ocdeCodes);
        }

        if (count($projectIds) > 0) {
            $publication['originatesFrom'] = [];

            foreach ($projectIds as $projectId) {
                $publication['originatesFrom'][] = [
                    'project' => [
                        'id' => CerifFormatter::toCerifId('Projects', $projectId),
                    ],
                ];

                $publication['originatesFrom'][] = [
                    'funding' => [
                        'id' => CerifFormatter::toCerifId('Fundings', 'P'.$projectId),
                    ],
                ];
            }
        }

        return $publication;
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
