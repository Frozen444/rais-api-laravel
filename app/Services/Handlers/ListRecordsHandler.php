<?php

namespace App\Services\Handlers;

use App\Exceptions\OaiException;
use App\Repositories\EquipmentRepository;
use App\Repositories\FundingRepository;
use App\Repositories\OrgUnitRepository;
use App\Repositories\PatenteRepository;
use App\Repositories\PersonaRepository;
use App\Repositories\ProyectoRepository;
use App\Repositories\PublicacionRepository;
use App\Services\CerifFormatter;
use App\Services\ResumptionTokenService;

class ListRecordsHandler
{
    private PublicacionRepository $publicacionRepository;

    private ProyectoRepository $proyectoRepository;

    private PatenteRepository $patenteRepository;

    private PersonaRepository $personaRepository;

    private OrgUnitRepository $orgUnitRepository;

    private FundingRepository $fundingRepository;

    private EquipmentRepository $equipmentRepository;

    private ResumptionTokenService $tokenService;

    public function __construct(
        PublicacionRepository $publicacionRepository,
        ProyectoRepository $proyectoRepository,
        PatenteRepository $patenteRepository,
        PersonaRepository $personaRepository,
        OrgUnitRepository $orgUnitRepository,
        FundingRepository $fundingRepository,
        EquipmentRepository $equipmentRepository,
        ResumptionTokenService $tokenService
    ) {
        $this->publicacionRepository = $publicacionRepository;
        $this->proyectoRepository = $proyectoRepository;
        $this->patenteRepository = $patenteRepository;
        $this->personaRepository = $personaRepository;
        $this->orgUnitRepository = $orgUnitRepository;
        $this->fundingRepository = $fundingRepository;
        $this->equipmentRepository = $equipmentRepository;
        $this->tokenService = $tokenService;
    }

    public function handle(array $params): array
    {
        $set = null;
        $metadataPrefix = null;
        $from = null;
        $until = null;
        $cursor = 0;
        $completeListSize = null;

        if (! empty($params['resumptionToken'])) {
            $validation = $this->tokenService->validateToken((string) $params['resumptionToken']);

            if (($validation['valid'] ?? false) !== true) {
                throw OaiException::badResumptionToken((string) ($validation['error'] ?? 'Invalid resumption token'));
            }

            $tokenData = $validation['data'];
            $set = CerifFormatter::normalizeSetSpec($tokenData['set'] ?? null);
            $metadataPrefix = $tokenData['metadataPrefix'] ?? null;
            $from = $tokenData['from'] ?? null;
            $until = $tokenData['until'] ?? null;
            $cursor = (int) ($tokenData['cursor'] ?? 0);
            $completeListSize = isset($tokenData['completeListSize']) ? (int) $tokenData['completeListSize'] : null;
        } else {
            $set = CerifFormatter::normalizeSetSpec($params['set'] ?? null);
            $metadataPrefix = $params['metadataPrefix'] ?? null;
            $from = $params['from'] ?? null;
            $until = $params['until'] ?? null;
        }

        if (! $metadataPrefix) {
            throw OaiException::badArgument('metadataPrefix is required');
        }

        if ($metadataPrefix !== 'perucris-cerif') {
            throw OaiException::cannotDisseminateFormat("Metadata format '{$metadataPrefix}' is not supported");
        }

        if (! $set) {
            throw OaiException::badArgument('set argument is required');
        }

        $repositories = $this->getRepositories();
        $repository = $repositories[$set] ?? null;

        if ($repository === null) {
            throw OaiException::noSetHierarchy("Set '{$set}' not supported");
        }

        $activeCount = $repository['count']($from, $until);
        $deletedCount = $repository['deletedCount']($from, $until);

        if ($completeListSize === null) {
            $completeListSize = $activeCount + $deletedCount;
        }

        if ($completeListSize === 0) {
            throw OaiException::noRecordsMatch('No records match the request criteria');
        }

        $pageSize = $this->tokenService->getPageSize();

        if ($cursor < $activeCount) {
            $activeRecords = $repository['records']([
                'from' => $from,
                'until' => $until,
                'offset' => $cursor,
                'limit' => $pageSize,
            ]);

            $remaining = max(0, $pageSize - count($activeRecords));
            $deletedHeaders = $remaining > 0
                ? $repository['deletedIdentifiers']([
                    'from' => $from,
                    'until' => $until,
                    'offset' => 0,
                    'limit' => $remaining,
                ])
                : [];
        } else {
            $activeRecords = [];
            $deletedHeaders = $repository['deletedIdentifiers']([
                'from' => $from,
                'until' => $until,
                'offset' => $cursor - $activeCount,
                'limit' => $pageSize,
            ]);
        }

        $deletedRecords = array_map(function ($header) {
            return [
                'header' => $header,
            ];
        }, $deletedHeaders);

        $records = [...$activeRecords, ...$deletedRecords];

        $response = [
            'record' => $records,
        ];

        if (count($records) > 0) {
            $response['resumptionToken'] = $this->tokenService->createToken($cursor, $completeListSize, [
                'set' => $set,
                'metadataPrefix' => $metadataPrefix,
                'from' => $from,
                'until' => $until,
            ]);
        }

        $baseUrl = config('oai.base_url', url('/oai'));

        return [
            'OAI-PMH' => [
                '@xmlns' => 'http://www.openarchives.org/OAI/2.0/',
                'responseDate' => now()->format('Y-m-d\TH:i:s\Z'),
                'request' => [
                    '@verb' => 'ListRecords',
                    ...CerifFormatter::buildOaiRequestAttributes($params),
                    '#text' => $baseUrl,
                ],
                'ListRecords' => $response,
            ],
        ];
    }

    private function getRepositories(): array
    {
        return [
            'persons' => [
                'count' => fn (?string $from, ?string $until): int => $this->personaRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->personaRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => $this->personaRepository->countDeleted($from, $until),
                'deletedIdentifiers' => fn (array $filters): array => $this->personaRepository->getDeletedIdentifiers($filters),
            ],
            'orgunits' => [
                'count' => fn (?string $from, ?string $until): int => $this->orgUnitRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->orgUnitRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => $this->orgUnitRepository->countDeleted($from, $until),
                'deletedIdentifiers' => fn (array $filters): array => $this->orgUnitRepository->getDeletedIdentifiers($filters),
            ],
            'publications' => [
                'count' => fn (?string $from, ?string $until): int => $this->publicacionRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->publicacionRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => $this->publicacionRepository->countDeleted($from, $until),
                'deletedIdentifiers' => fn (array $filters): array => $this->publicacionRepository->getDeletedIdentifiers($filters),
            ],
            'projects' => [
                'count' => fn (?string $from, ?string $until): int => $this->proyectoRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->proyectoRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => $this->proyectoRepository->countDeleted($from, $until),
                'deletedIdentifiers' => fn (array $filters): array => $this->proyectoRepository->getDeletedIdentifiers($filters),
            ],
            'patents' => [
                'count' => fn (?string $from, ?string $until): int => $this->patenteRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->patenteRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => $this->patenteRepository->countDeleted($from, $until),
                'deletedIdentifiers' => fn (array $filters): array => $this->patenteRepository->getDeletedIdentifiers($filters),
            ],
            'fundings' => [
                'count' => fn (?string $from, ?string $until): int => $this->fundingRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->fundingRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => 0,
                'deletedIdentifiers' => fn (array $filters): array => [],
            ],
            'equipments' => [
                'count' => fn (?string $from, ?string $until): int => $this->equipmentRepository->countAll($from, $until),
                'records' => fn (array $filters): array => $this->equipmentRepository->findAll($filters),
                'deletedCount' => fn (?string $from, ?string $until): int => 0,
                'deletedIdentifiers' => fn (array $filters): array => [],
            ],
        ];
    }
}
