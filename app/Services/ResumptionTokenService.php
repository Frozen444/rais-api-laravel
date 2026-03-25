<?php

namespace App\Services;

class ResumptionTokenService
{
    private int $pageSize;

    public function __construct()
    {
        $this->pageSize = (int) config('oai.page_size', (int) env('OAI_PAGE_SIZE', 100));
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function encodeToken(array $params): string
    {
        $json = json_encode($params);

        if (! is_string($json)) {
            return '';
        }

        return base64_encode($json);
    }

    public function decodeToken(string $token): ?array
    {
        try {
            $decoded = base64_decode($token, true);

            if ($decoded === false) {
                return null;
            }

            $data = json_decode($decoded, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function validateToken(string $token): array
    {
        if (trim($token) === '') {
            return [
                'valid' => false,
                'error' => 'Token is empty',
            ];
        }

        $data = $this->decodeToken($token);

        if ($data === null) {
            return [
                'valid' => false,
                'error' => 'Invalid token format',
            ];
        }

        if (! isset($data['cursor']) || ! is_numeric($data['cursor']) || (int) $data['cursor'] < 0) {
            return [
                'valid' => false,
                'error' => 'Invalid cursor in token',
            ];
        }

        if (empty($data['set'])) {
            return [
                'valid' => false,
                'error' => 'Missing set in token',
            ];
        }

        return [
            'valid' => true,
            'data' => [
                'set' => $data['set'],
                'metadataPrefix' => $data['metadataPrefix'] ?? null,
                'from' => $data['from'] ?? null,
                'until' => $data['until'] ?? null,
                'cursor' => (int) $data['cursor'],
                'completeListSize' => isset($data['completeListSize']) ? (int) $data['completeListSize'] : null,
            ],
        ];
    }

    public function createToken(int $cursor, int $completeListSize, array $params): array
    {
        $nextCursor = $cursor + $this->pageSize;

        if ($nextCursor >= $completeListSize) {
            return [
                '@cursor' => (string) $cursor,
                '@completeListSize' => (string) $completeListSize,
                '#text' => '',
            ];
        }

        $tokenData = [
            ...$params,
            'cursor' => $nextCursor,
            'completeListSize' => $completeListSize,
        ];

        return [
            '@cursor' => (string) $cursor,
            '@completeListSize' => (string) $completeListSize,
            '#text' => $this->encodeToken($tokenData),
        ];
    }

    public function buildToken(
        int $cursor,
        int $pageSize,
        int $total,
        ?string $set = null,
        ?string $from = null,
        ?string $until = null,
        ?string $metadataPrefix = null
    ): array {
        if ($pageSize > 0) {
            $this->pageSize = $pageSize;
        }

        return $this->createToken($cursor, $total, [
            'set' => $set,
            'from' => $from,
            'until' => $until,
            'metadataPrefix' => $metadataPrefix,
        ]);
    }

    public function extractPagination(array $params): array
    {
        if (! empty($params['resumptionToken'])) {
            $validation = $this->validateToken((string) $params['resumptionToken']);

            if (($validation['valid'] ?? false) === true) {
                return [
                    'cursor' => (int) ($validation['data']['cursor'] ?? 0),
                    'set' => $validation['data']['set'] ?? null,
                    'from' => $validation['data']['from'] ?? null,
                    'until' => $validation['data']['until'] ?? null,
                    'metadataPrefix' => $validation['data']['metadataPrefix'] ?? null,
                    'completeListSize' => $validation['data']['completeListSize'] ?? null,
                    'pageSize' => $this->pageSize,
                ];
            }
        }

        return [
            'cursor' => (int) ($params['cursor'] ?? 0),
            'set' => $params['set'] ?? null,
            'from' => $params['from'] ?? null,
            'until' => $params['until'] ?? null,
            'metadataPrefix' => $params['metadataPrefix'] ?? null,
            'completeListSize' => null,
            'pageSize' => $this->pageSize,
        ];
    }
}
