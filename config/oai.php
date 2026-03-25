<?php

return [
    'version' => env('OAI_VERSION', '1.0.0'),
    'domain' => env('OAI_DOMAIN', 'perucris.concytec.gob.pe'),
    'base_url' => env('OAI_BASE_URL', env('APP_URL', 'http://localhost').'/oai'),
    'repository_name' => env('OAI_REPOSITORY_NAME', 'RAIS - Repositorio Académico de Investigación UNMSM'),
    'admin_email' => env('OAI_ADMIN_EMAIL', 'admin@example.com'),
    'earliest_datestamp' => env('OAI_EARLIEST_DATESTAMP', '2014-01-01T00:00:00Z'),
    'deleted_record' => env('OAI_DELETED_RECORD', 'persistent'),
    'page_size' => (int) env('OAI_PAGE_SIZE', 100),

    'sets' => [
        ['setSpec' => 'persons', 'setName' => 'Personas'],
        ['setSpec' => 'orgunits', 'setName' => 'Unidades organizativas'],
        ['setSpec' => 'publications', 'setName' => 'Publicaciones'],
        ['setSpec' => 'projects', 'setName' => 'Proyectos'],
        ['setSpec' => 'fundings', 'setName' => 'Financiamientos'],
        ['setSpec' => 'equipments', 'setName' => 'Equipamientos'],
        ['setSpec' => 'patents', 'setName' => 'Patentes'],
    ],

    'legacy_set_aliases' => [
        'funding' => 'fundings',
        'equipment' => 'equipments',
    ],

    'institution' => [
        'name' => 'Universidad Nacional Mayor de San Marcos',
        'acronym' => 'UNMSM',
        'ror' => 'https://ror.org/026zsd177',
        'ruc' => '20106897914',
        'country' => 'PE',
        'country_name' => 'Perú',
        'ubigeo' => '150000',
        'ciiu' => '8530',
        'sector_ocde' => '09',
    ],

    'namespaces' => [
        'oai_pmh' => 'http://www.openarchives.org/OAI/2.0/',
        'perucris_cerif' => 'https://purl.org/pe-repo/perucris/cerif',
        'cerif_model' => 'https://w3id.org/cerif/model',
    ],

    'metadata_formats' => [
        'perucris-cerif' => [
            'schema' => 'https://purl.org/pe-repo/perucris/cerif.xsd',
            'metadata_namespace' => 'https://purl.org/pe-repo/perucris/cerif',
        ],
    ],
];
