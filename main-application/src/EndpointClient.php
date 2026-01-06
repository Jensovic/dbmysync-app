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

