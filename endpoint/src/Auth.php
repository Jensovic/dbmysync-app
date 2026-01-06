<?php

namespace Jensovic\DbMySyncAddin;

class Auth
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Verify the request has valid authentication
     */
    public function verify(): bool
    {
        $providedSecret = $this->getSecretFromRequest();
        
        if (empty($providedSecret)) {
            return false;
        }

        return hash_equals($this->secret, $providedSecret);
    }

    /**
     * Get secret from request headers
     */
    private function getSecretFromRequest(): ?string
    {
        // Check X-DbSync-Secret header
        if (isset($_SERVER['HTTP_X_DBSYNC_SECRET'])) {
            return $_SERVER['HTTP_X_DBSYNC_SECRET'];
        }

        // Fallback: Check Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Send unauthorized response and exit
     */
    public function sendUnauthorized(): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized - Invalid or missing secret'
        ]);
        exit;
    }
}

