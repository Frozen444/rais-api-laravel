<?php

namespace App\Services\Handlers;

class IdentifyHandler
{
    public function handle(array $params): array
    {
        $baseUrl = config('oai.base_url', url('/oai'));

        return [
            'OAI-PMH' => [
                '@xmlns' => 'http://www.openarchives.org/OAI/2.0/',
                'responseDate' => now()->format('Y-m-d\TH:i:s\Z'),
                'request' => [
                    '@verb' => 'Identify',
                    '#text' => $baseUrl,
                ],
                'Identify' => [
                    'repositoryName' => config('oai.repository_name', 'RAIS - Repositorio Académico de Investigación UNMSM'),
                    'baseURL' => $baseUrl,
                    'protocolVersion' => '2.0',
                    'adminEmail' => config('oai.admin_email', 'admin@example.com'),
                    'earliestDatestamp' => config('oai.earliest_datestamp', '2014-01-01T00:00:00Z'),
                    'deletedRecord' => config('oai.deleted_record', 'persistent'),
                    'granularity' => 'YYYY-MM-DDThh:mm:ssZ',
                ],
            ],
        ];
    }
}
