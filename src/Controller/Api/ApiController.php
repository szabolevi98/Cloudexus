<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Core\Currency;
use Cloudexus\Core\Paginator;
use Cloudexus\Model\Account\ApiUserModel;

/**
 * Base for all /api/* endpoints: bearer-token auth, JSON in/out, and a
 * shared {data, meta} pagination envelope. Does NOT use sessions/CSRF/Twig.
 */
abstract class ApiController
{
    protected const DEFAULT_PER_PAGE = 50;
    protected const MAX_PER_PAGE = 200;

    protected ?array $apiUser = null;

    /** Rejects the request with 401 unless a valid, active token is present. */
    protected function authenticate(): void
    {
        $this->apiUser = (new ApiUserModel())->findActiveByToken($this->bearerToken());
        if (!$this->apiUser) {
            $this->error('Invalid or missing API token.', 401);
        }
    }

    protected function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if ($header !== '' && preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        // Fallback for environments where the Authorization header is stripped.
        return trim($_SERVER['HTTP_X_API_KEY'] ?? '');
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function error(string $message, int $status = 400): never
    {
        $this->json(['error' => ['status' => $status, 'message' => $message]], $status);
    }

    /** Decoded JSON request body as an array. */
    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->error('Invalid JSON request body.', 422);
        }
        return $data;
    }

    protected function perPage(): int
    {
        $pp = (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE);
        if ($pp < 1) {
            $pp = self::DEFAULT_PER_PAGE;
        }
        return min($pp, self::MAX_PER_PAGE);
    }

    protected function paginator(): Paginator
    {
        return new Paginator($this->perPage());
    }

    /** Outputs a page of rows plus pagination metadata. */
    protected function collection(array $rows, Paginator $pager): never
    {
        $this->json([
            'data' => array_values($rows),
            'meta' => [
                'page' => $pager->page,
                'per_page' => $pager->perPage,
                'total' => $pager->total,
                'total_pages' => $pager->pages(),
                'currency' => $this->currencyMeta(),
            ],
        ]);
    }

    /** Outputs a single resource plus the currency metadata. */
    protected function resource(array $data, int $status = 200): never
    {
        $this->json([
            'data' => $data,
            'meta' => ['currency' => $this->currencyMeta()],
        ], $status);
    }

    /**
     * The primary currency every monetary amount in the response is expressed
     * in. Amounts are never converted; see GET /api/currencies for the rates.
     *
     * @return array{code: string, symbol: string, title: string}
     */
    protected function currencyMeta(): array
    {
        $primary = Currency::primary();

        return [
            'code' => (string) $primary['code'],
            'symbol' => Currency::symbol(),
            'title' => (string) ($primary['title'] ?? ''),
        ];
    }

    /** Common list filter values (q + updated_since) read from the query string. */
    protected function baseFilters(): array
    {
        return [
            'q' => trim($_GET['q'] ?? ''),
            'updated_since' => trim($_GET['updated_since'] ?? ''),
        ];
    }
}
