<?php

namespace App\Providers;

use App\Repositories\EquipmentRepository;
use App\Repositories\FundingRepository;
use App\Repositories\OrgUnitRepository;
use App\Repositories\PatenteRepository;
use App\Repositories\PersonaRepository;
use App\Repositories\ProyectoRepository;
use App\Repositories\PublicacionRepository;
use App\Services\Handlers\GetRecordHandler;
use App\Services\Handlers\IdentifyHandler;
use App\Services\Handlers\ListIdentifiersHandler;
use App\Services\Handlers\ListMetadataFormatsHandler;
use App\Services\Handlers\ListRecordsHandler;
use App\Services\Handlers\ListSetsHandler;
use App\Services\OaiDispatcher;
use App\Services\ResumptionTokenService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResumptionTokenService::class);

        $this->app->singleton(PublicacionRepository::class);
        $this->app->singleton(ProyectoRepository::class);
        $this->app->singleton(PatenteRepository::class);
        $this->app->singleton(PersonaRepository::class);
        $this->app->singleton(OrgUnitRepository::class);
        $this->app->singleton(FundingRepository::class);
        $this->app->singleton(EquipmentRepository::class);

        $this->app->singleton(IdentifyHandler::class);
        $this->app->singleton(ListSetsHandler::class);
        $this->app->singleton(ListMetadataFormatsHandler::class);
        $this->app->singleton(ListRecordsHandler::class);
        $this->app->singleton(ListIdentifiersHandler::class);
        $this->app->singleton(GetRecordHandler::class);

        $this->app->singleton(OaiDispatcher::class);
    }

    public function boot(): void
    {
        //
    }
}
