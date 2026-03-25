<?php

namespace App\Http\Controllers;

use App\Exceptions\OaiException;
use App\Services\CerifFormatter;
use App\Services\OaiDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OaiController extends Controller
{
    private OaiDispatcher $dispatcher;

    public function __construct(OaiDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function serve(Request $request): JsonResponse
    {
        try {
            $params = $request->query();
            $response = $this->dispatcher->dispatch($params);

            return response()->json($response, 200);
        } catch (OaiException $e) {
            return $this->buildErrorResponse(
                $request->query(),
                $e->getOaiCode(),
                $e->getMessage()
            );
        } catch (Throwable $e) {
            return $this->buildErrorResponse(
                $request->query(),
                'badArgument',
                config('app.debug') ? $e->getMessage() : 'Internal server error'
            );
        }
    }

    private function buildErrorResponse(array $params, string $code, string $message): JsonResponse
    {
        $baseUrl = config('oai.base_url', url('/oai'));

        $response = [
            'OAI-PMH' => [
                '@xmlns' => 'http://www.openarchives.org/OAI/2.0/',
                'responseDate' => now()->format('Y-m-d\TH:i:s\Z'),
                'request' => [
                    '@verb' => $params['verb'] ?? '',
                    ...CerifFormatter::buildOaiRequestAttributes($params),
                    '#text' => $baseUrl,
                ],
                'error' => [
                    '@code' => $code,
                    '#text' => $message,
                ],
            ],
        ];

        return response()->json($response, 200);
    }
}
