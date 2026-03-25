<?php

namespace App\Services\Handlers;

use App\Services\CerifFormatter;

class ListMetadataFormatsHandler
{
    public function handle(array $params): array
    {
        $baseUrl = config('oai.base_url', url('/oai'));
        $formats = config('oai.metadata_formats', []);

        $metadataFormats = [];

        foreach ($formats as $prefix => $format) {
            $metadataFormats[] = [
                'metadataPrefix' => $prefix,
                'schema' => $format['schema'] ?? '',
                'metadataNamespace' => $format['metadata_namespace'] ?? '',
            ];
        }

        return [
            'OAI-PMH' => [
                '@xmlns' => 'http://www.openarchives.org/OAI/2.0/',
                'responseDate' => now()->format('Y-m-d\TH:i:s\Z'),
                'request' => [
                    '@verb' => 'ListMetadataFormats',
                    ...CerifFormatter::buildOaiRequestAttributes($params),
                    '#text' => $baseUrl,
                ],
                'ListMetadataFormats' => [
                    'metadataFormat' => $metadataFormats,
                ],
            ],
        ];
    }
}
