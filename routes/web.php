<?php

use App\Http\Controllers\OaiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $metadataFormats = array_keys(config('oai.metadata_formats', []));

    return response()->json([
        'name' => 'RAIS-API',
        'description' => 'OAI-PMH 2.0 API con perfil CERIF PeruCRIS',
        'version' => config('oai.version', '1.0.0'),
        'protocol' => 'OAI-PMH 2.0',
        'metadataPrefix' => $metadataFormats[0] ?? 'perucris-cerif',
        'endpoints' => [
            'oai' => '/oai',
            'health' => '/health',
        ],
        'documentation' => 'https://purl.org/pe-repo/perucris/cerif',
        'sets' => array_map(fn ($set) => $set['setSpec'], config('oai.sets', [])),
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
    ]);
});

Route::get('/oai', [OaiController::class, 'serve'])->middleware('validate.oai');
