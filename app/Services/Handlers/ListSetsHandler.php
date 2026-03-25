<?php

namespace App\Services\Handlers;

use App\Services\CerifFormatter;

class ListSetsHandler
{
    public function handle(array $params): array
    {
        $baseUrl = config('oai.base_url', url('/oai'));

        $sets = config('oai.sets', [
            ['setSpec' => 'persons', 'setName' => 'Personas'],
            ['setSpec' => 'orgunits', 'setName' => 'Unidades organizativas'],
            ['setSpec' => 'publications', 'setName' => 'Publicaciones'],
            ['setSpec' => 'projects', 'setName' => 'Proyectos'],
            ['setSpec' => 'fundings', 'setName' => 'Financiamientos'],
            ['setSpec' => 'equipments', 'setName' => 'Equipamientos'],
            ['setSpec' => 'patents', 'setName' => 'Patentes'],
        ]);

        return [
            'OAI-PMH' => [
                '@xmlns' => 'http://www.openarchives.org/OAI/2.0/',
                'responseDate' => now()->format('Y-m-d\TH:i:s\Z'),
                'request' => [
                    '@verb' => 'ListSets',
                    ...CerifFormatter::buildOaiRequestAttributes($params),
                    '#text' => $baseUrl,
                ],
                'ListSets' => [
                    'set' => $sets,
                ],
            ],
        ];
    }
}
