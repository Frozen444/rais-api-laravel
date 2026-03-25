<?php

namespace App\Services;

use App\Exceptions\OaiException;
use App\Services\Handlers\GetRecordHandler;
use App\Services\Handlers\IdentifyHandler;
use App\Services\Handlers\ListIdentifiersHandler;
use App\Services\Handlers\ListMetadataFormatsHandler;
use App\Services\Handlers\ListRecordsHandler;
use App\Services\Handlers\ListSetsHandler;

class OaiDispatcher
{
    private const VALID_VERBS = [
        'Identify',
        'ListMetadataFormats',
        'ListSets',
        'ListIdentifiers',
        'ListRecords',
        'GetRecord',
    ];

    private IdentifyHandler $identifyHandler;
    private ListSetsHandler $listSetsHandler;
    private ListMetadataFormatsHandler $listMetadataFormatsHandler;
    private ListRecordsHandler $listRecordsHandler;
    private ListIdentifiersHandler $listIdentifiersHandler;
    private GetRecordHandler $getRecordHandler;

    public function __construct(
        IdentifyHandler $identifyHandler,
        ListSetsHandler $listSetsHandler,
        ListMetadataFormatsHandler $listMetadataFormatsHandler,
        ListRecordsHandler $listRecordsHandler,
        ListIdentifiersHandler $listIdentifiersHandler,
        GetRecordHandler $getRecordHandler
    ) {
        $this->identifyHandler = $identifyHandler;
        $this->listSetsHandler = $listSetsHandler;
        $this->listMetadataFormatsHandler = $listMetadataFormatsHandler;
        $this->listRecordsHandler = $listRecordsHandler;
        $this->listIdentifiersHandler = $listIdentifiersHandler;
        $this->getRecordHandler = $getRecordHandler;
    }

    public function dispatch(array $params): array
    {
        $verb = $params['verb'] ?? null;

        if (!$verb) {
            throw OaiException::badArgument('Missing verb parameter');
        }

        if (!in_array($verb, self::VALID_VERBS, true)) {
            throw OaiException::badVerb("Invalid verb: {$verb}");
        }

        return match ($verb) {
            'Identify' => $this->identifyHandler->handle($params),
            'ListMetadataFormats' => $this->listMetadataFormatsHandler->handle($params),
            'ListSets' => $this->listSetsHandler->handle($params),
            'ListIdentifiers' => $this->listIdentifiersHandler->handle($params),
            'ListRecords' => $this->listRecordsHandler->handle($params),
            'GetRecord' => $this->getRecordHandler->handle($params),
            default => throw OaiException::badVerb("Unsupported verb: {$verb}"),
        };
    }

    public static function getValidVerbs(): array
    {
        return self::VALID_VERBS;
    }
}
