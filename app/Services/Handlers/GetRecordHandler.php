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

class GetRecordHandler
{
    private PublicacionRepository $publicacionRepository;

    private ProyectoRepository $proyectoRepository;

    private PatenteRepository $patenteRepository;

    private PersonaRepository $personaRepository;

    private OrgUnitRepository $orgUnitRepository;

    private FundingRepository $fundingRepository;

    private EquipmentRepository $equipmentRepository;

    public function __construct(
        PublicacionRepository $publicacionRepository,
        ProyectoRepository $proyectoRepository,
        PatenteRepository $patenteRepository,
        PersonaRepository $personaRepository,
        OrgUnitRepository $orgUnitRepository,
        FundingRepository $fundingRepository,
        EquipmentRepository $equipmentRepository
    ) {
        $this->publicacionRepository = $publicacionRepository;
        $this->proyectoRepository = $proyectoRepository;
        $this->patenteRepository = $patenteRepository;
        $this->personaRepository = $personaRepository;
        $this->orgUnitRepository = $orgUnitRepository;
        $this->fundingRepository = $fundingRepository;
        $this->equipmentRepository = $equipmentRepository;
    }

    public function handle(array $params): array
    {
        $identifier = $params['identifier'] ?? null;
        $metadataPrefix = $params['metadataPrefix'] ?? null;

        if (! $identifier) {
            throw OaiException::badArgument('identifier is required');
        }

        if (! $metadataPrefix) {
            throw OaiException::badArgument('metadataPrefix is required');
        }

        if ($metadataPrefix !== 'perucris-cerif') {
            throw OaiException::cannotDisseminateFormat("Metadata format '{$metadataPrefix}' is not supported");
        }

        $parsed = CerifFormatter::parseOAIIdentifier($identifier);
        if (! $parsed) {
            throw OaiException::idDoesNotExist("Invalid identifier format: {$identifier}");
        }

        $entityType = $parsed['entityType'];
        $id = $parsed['id'];

        $record = $this->getRecordByEntity($entityType, $id);

        if (! $record) {
            throw OaiException::idDoesNotExist("The identifier '{$identifier}' does not exist in this repository");
        }

        $baseUrl = config('oai.base_url', url('/oai'));

        return [
            'OAI-PMH' => [
                '@xmlns' => 'http://www.openarchives.org/OAI/2.0/',
                'responseDate' => now()->format('Y-m-d\TH:i:s\Z'),
                'request' => [
                    '@verb' => 'GetRecord',
                    ...CerifFormatter::buildOaiRequestAttributes($params),
                    '#text' => $baseUrl,
                ],
                'GetRecord' => [
                    'record' => $record,
                ],
            ],
        ];
    }

    private function getRecordByEntity(string $entityType, string $id): ?array
    {
        return match (strtolower($entityType)) {
            'publications' => $this->publicacionRepository->findById((int) $id),
            'projects' => $this->proyectoRepository->findById((int) $id),
            'patents' => $this->patenteRepository->findById((int) $id),
            'persons' => $this->personaRepository->findById((int) $id),
            'orgunits' => $this->orgUnitRepository->findById($id),
            'fundings', 'funding' => $this->fundingRepository->findById($id),
            'equipments', 'equipment' => $this->equipmentRepository->findById($id),
            default => null,
        };
    }
}
