<?php

namespace Jensovic\DbMySync;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class EndpointClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // For local development, enable in production
        ]);
    }

    /**
     * Fetch schema from endpoint
     */
    public function fetchSchema(string $url, string $secret): array
    {
        try {
            $response = $this->client->get($url, [
                'query' => ['action' => 'schema'],
                'headers' => [
                    'X-DbSync-Secret' => $secret,
                    'Accept' => 'application/json'
                ]
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!isset($data['success']) || !$data['success']) {
                throw new \RuntimeException($data['error'] ?? 'Unknown error from endpoint');
            }

            return $data['data'];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to fetch schema: " . $e->getMessage());
        }
    }

    /**
     * Test endpoint connection (health check)
     */
    public function testConnection(string $url, string $secret): bool
    {
        try {
            $response = $this->client->get($url, [
                'query' => ['action' => 'health'],
                'headers' => [
                    'X-DbSync-Secret' => $secret,
                    'Accept' => 'application/json'
                ]
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            return isset($data['success']) && $data['success'];
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Test endpoint connection with detailed response
     *
     * @return array ['status' => 'success'|'auth_failed'|'not_found'|'unreachable', 'message' => string, 'error' => string|null]
     */
    public function testConnectionDetailed(string $url, string $secret): array
    {
        try {
            $response = $this->client->get($url, [
                'query' => ['action' => 'health'],
                'headers' => [
                    'X-DbSync-Secret' => $secret,
                    'Accept' => 'application/json'
                ],
                'http_errors' => false // Don't throw exception on 4xx/5xx
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            // Check if response is valid JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'status' => 'not_found',
                    'message' => 'Kein Endpoint gefunden',
                    'error' => 'Invalid JSON response'
                ];
            }

            // Success
            if ($statusCode === 200 && isset($data['success']) && $data['success']) {
                return [
                    'status' => 'success',
                    'message' => 'Endpoint gefunden',
                    'error' => null
                ];
            }

            // Authentication failed (401)
            if ($statusCode === 401 || (isset($data['error']) && strpos($data['error'], 'Unauthorized') !== false)) {
                return [
                    'status' => 'auth_failed',
                    'message' => 'Endpoint gefunden, aber Secret ist falsch',
                    'error' => $data['error'] ?? 'Unauthorized'
                ];
            }

            // Other error from endpoint
            return [
                'status' => 'not_found',
                'message' => 'Kein Endpoint gefunden',
                'error' => $data['error'] ?? "HTTP {$statusCode}"
            ];

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Connection failed (host not reachable, DNS error, etc.)
            return [
                'status' => 'unreachable',
                'message' => 'Endpoint nicht erreichbar',
                'error' => $e->getMessage()
            ];
        } catch (GuzzleException $e) {
            // Other Guzzle errors
            return [
                'status' => 'unreachable',
                'message' => 'Endpoint nicht erreichbar',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch schema for a specific table
     */
    public function fetchTableSchema(string $url, string $secret, string $table): array
    {
        try {
            $response = $this->client->get($url, [
                'query' => [
                    'action' => 'schema',
                    'table' => $table
                ],
                'headers' => [
                    'X-DbSync-Secret' => $secret,
                    'Accept' => 'application/json'
                ]
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!isset($data['success']) || !$data['success']) {
                throw new \RuntimeException($data['error'] ?? 'Unknown error from endpoint');
            }

            return $data['data'];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to fetch table schema: " . $e->getMessage());
        }
    }
}

