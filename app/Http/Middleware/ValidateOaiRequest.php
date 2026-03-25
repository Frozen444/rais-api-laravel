<?php

namespace App\Http\Middleware;

use App\Services\CerifFormatter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateOaiRequest
{
    private const VALID_VERBS = [
        'Identify',
        'ListMetadataFormats',
        'ListSets',
        'ListIdentifiers',
        'ListRecords',
        'GetRecord',
    ];

    private const DATE_REGEX = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}Z)?$/';

    public function handle(Request $request, Closure $next): Response
    {
        $params = $request->query();
        $verb = $params['verb'] ?? null;

        if (! $verb) {
            return $this->buildErrorResponse($params, 'badVerb', 'The verb argument is missing');
        }

        if (! in_array($verb, self::VALID_VERBS, true)) {
            return $this->buildErrorResponse($params, 'badVerb', "The value '{$verb}' is not a legal OAI-PMH verb");
        }

        if (! empty($params['resumptionToken'])) {
            $invalidWithToken = array_values(array_filter(['from', 'until', 'set', 'metadataPrefix'], function ($key) use ($params) {
                return array_key_exists($key, $params) && $params[$key] !== null && $params[$key] !== '';
            }));

            if (count($invalidWithToken) > 0) {
                return $this->buildErrorResponse(
                    $params,
                    'badArgument',
                    'Arguments '.implode(', ', $invalidWithToken).' are not allowed when resumptionToken is provided'
                );
            }
        }

        if (in_array($verb, ['ListIdentifiers', 'ListRecords'], true)) {
            $set = $params['set'] ?? null;
            $metadataPrefix = $params['metadataPrefix'] ?? null;
            $resumptionToken = $params['resumptionToken'] ?? null;

            if ($set !== null && $set !== '' && ! $this->isValidSet($set)) {
                return $this->buildErrorResponse($params, 'noSetHierarchy', 'The repository does not support the specified set');
            }

            if (! $resumptionToken && ! $metadataPrefix) {
                return $this->buildErrorResponse(
                    $params,
                    'badArgument',
                    'metadataPrefix required when resumptionToken not provided'
                );
            }

            if ($metadataPrefix && $metadataPrefix !== 'perucris-cerif') {
                return $this->buildErrorResponse(
                    $params,
                    'cannotDisseminateFormat',
                    'The metadata format is not supported by this repository'
                );
            }

            if (! empty($params['from']) && ! $this->isValidDate((string) $params['from'])) {
                return $this->buildErrorResponse(
                    $params,
                    'badArgument',
                    'from must be in YYYY-MM-DD or YYYY-MM-DDThh:mm:ssZ format'
                );
            }

            if (! empty($params['until']) && ! $this->isValidDate((string) $params['until'])) {
                return $this->buildErrorResponse(
                    $params,
                    'badArgument',
                    'until must be in YYYY-MM-DD or YYYY-MM-DDThh:mm:ssZ format'
                );
            }
        }

        if ($verb === 'GetRecord') {
            $identifier = $params['identifier'] ?? null;
            $metadataPrefix = $params['metadataPrefix'] ?? null;

            if (! $identifier) {
                return $this->buildErrorResponse($params, 'badArgument', 'identifier is required');
            }

            if (! $metadataPrefix) {
                return $this->buildErrorResponse($params, 'badArgument', 'metadataPrefix is required');
            }

            if ($metadataPrefix !== 'perucris-cerif') {
                return $this->buildErrorResponse(
                    $params,
                    'cannotDisseminateFormat',
                    'The metadata format is not supported by this repository'
                );
            }
        }

        return $next($request);
    }

    private function isValidSet(string $set): bool
    {
        $availableSets = array_map(fn ($item) => $item['setSpec'], config('oai.sets', []));
        $legacyAliases = array_keys(config('oai.legacy_set_aliases', CerifFormatter::LEGACY_SET_ALIASES));

        return in_array($set, [...$availableSets, ...$legacyAliases], true);
    }

    private function isValidDate(string $value): bool
    {
        return preg_match(self::DATE_REGEX, $value) === 1;
    }

    private function buildErrorResponse(array $params, string $code, string $message): Response
    {
        $baseUrl = config('oai.base_url', url('/oai'));

        return response()->json([
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
        ], 200);
    }
}
