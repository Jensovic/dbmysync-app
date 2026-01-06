<?php

namespace Jensovic\DbMySyncAddin;

class Response
{
    /**
     * Send JSON success response
     */
    public static function success($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send JSON error response
     */
    public static function error(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

