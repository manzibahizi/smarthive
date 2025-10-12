<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController
{
    private string $storageFile;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../../storage';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $this->storageFile = $dataDir . '/notifications.json';
        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode(new \stdClass()));
        }
    }

    public function getNotifications(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? ['id' => 'guest'];
        $userId = (string)($user['id'] ?? 'guest');
        $all = $this->readAll();
        $payload = [
            'status' => 'success',
            'data' => $all[$userId] ?? ['email' => '', 'phone' => '']
        ];
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function saveNotifications(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? ['id' => 'guest'];
        $userId = (string)($user['id'] ?? 'guest');
        $input = $request->getParsedBody() ?: [];
        $email = isset($input['email']) ? trim((string)$input['email']) : '';
        $phone = isset($input['phone']) ? trim((string)$input['phone']) : '';

        // Basic validation
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid email']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
        if ($phone !== '' && !preg_match('/^[+0-9][0-9\-\s]{6,20}$/', $phone)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid phone']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $all = $this->readAll();
        $all[$userId] = ['email' => $email, 'phone' => $phone];
        file_put_contents($this->storageFile, json_encode($all, JSON_PRETTY_PRINT));

        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function readAll(): array
    {
        $raw = @file_get_contents($this->storageFile);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}


