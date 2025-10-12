<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HiveController
{
    private string $hivesFile;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../../storage';
        if (!is_dir($dataDir)) { @mkdir($dataDir, 0777, true); }
        $this->hivesFile = $dataDir . '/hives.json';
        if (!file_exists($this->hivesFile)) {
            file_put_contents($this->hivesFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public function list(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = (string)($user['id'] ?? '');
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');

        $hives = $this->readHives();
        if (!$isAdmin) {
            $hives = array_values(array_filter($hives, function ($h) use ($userId) {
                return (string)($h['owner_id'] ?? '') === $userId;
            }));
        } else {
            $hives = array_values($hives);
            // Enrich with owner contact info for admins
            $notifPath = __DIR__ . '/../../storage/notifications.json';
            $contacts = [];
            if (file_exists($notifPath)) {
                $raw = @file_get_contents($notifPath);
                $arr = $raw ? json_decode($raw, true) : [];
                if (is_array($arr)) { $contacts = $arr; }
            }
            foreach ($hives as &$hive) {
                $oid = (string)($hive['owner_id'] ?? '');
                $c = $contacts[$oid] ?? [];
                $hive['owner_email'] = $c['email'] ?? '';
                $hive['owner_phone'] = $c['phone'] ?? '';
            }
            unset($hive);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'hives' => $hives]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?: [];
        $user = $_SESSION['user'] ?? ['id' => 'guest', 'name' => 'Guest'];
        $name = trim((string)($data['name'] ?? ''));
        $deviceId = trim((string)($data['device_id'] ?? ''));
        $location = trim((string)($data['location'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));

        if ($name === '' || $deviceId === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Name and device_id are required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $hives = $this->readHives();
        foreach ($hives as $h) {
            if (strcasecmp((string)($h['device_id'] ?? ''), $deviceId) === 0) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Device ID already registered']));
                return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
            }
        }

        $nextId = empty($hives) ? 1 : (max(array_map(fn($x)=>(int)$x['id'], $hives)) + 1);
        $new = [
            'id' => $nextId,
            'name' => $name,
            'device_id' => $deviceId,
            'location' => $location,
            'description' => $description,
            'owner_id' => (string)($user['id'] ?? ''),
            'owner_name' => (string)($user['name'] ?? ''),
            'status' => 'pending',
            'created_at' => date('c')
        ];
        $hives[] = $new;
        file_put_contents($this->hivesFile, json_encode($hives, JSON_PRETTY_PRINT));

        $response->getBody()->write(json_encode(['status' => 'success', 'hive' => $new]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = (string)($user['id'] ?? '');
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $hiveId = (int)$args['id'];
        $hives = $this->readHives();
        $found = null;
        foreach ($hives as $h) { if ((int)$h['id'] === $hiveId) { $found = $h; break; } }
        if (!$found) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        if (!$isAdmin && (string)($found['owner_id'] ?? '') !== $userId) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        // Simulated live metrics until device integration
        $found['temperature'] = 24;
        $found['humidity'] = 65;
        $found['weight'] = 45;
        $found['gas_ppm'] = 180;
        $found['last_updated'] = date('c');
        $found['history'] = [
            ['time' => '06:00', 'temperature' => 22, 'gas_ppm' => 170],
            ['time' => '09:00', 'temperature' => 24, 'gas_ppm' => 182],
            ['time' => '12:00', 'temperature' => 26, 'gas_ppm' => 190],
            ['time' => '15:00', 'temperature' => 25, 'gas_ppm' => 188],
            ['time' => '18:00', 'temperature' => 23, 'gas_ppm' => 176]
        ];
        $response->getBody()->write(json_encode(['status' => 'success', 'hive' => $found]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = (string)($user['id'] ?? '');
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $hiveId = (int)$args['id'];
        $data = $request->getParsedBody() ?: [];
        $hives = $this->readHives();
        $updated = false;
        foreach ($hives as &$h) {
            if ((int)$h['id'] === $hiveId) {
                if (!$isAdmin && (string)($h['owner_id'] ?? '') !== $userId) {
                    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
                if (isset($data['name'])) { $h['name'] = trim((string)$data['name']); }
                if (isset($data['location'])) { $h['location'] = trim((string)$data['location']); }
                if (isset($data['description'])) { $h['description'] = trim((string)$data['description']); }
                if (isset($data['status'])) { $h['status'] = trim((string)$data['status']); }
                $updated = true; break;
            }
        }
        unset($h);
        if (!$updated) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        file_put_contents($this->hivesFile, json_encode($hives, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function readHives(): array
    {
        $raw = @file_get_contents($this->hivesFile);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = (string)($user['id'] ?? '');
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $hiveId = (int)$args['id'];

        $hives = $this->readHives();
        $found = null;
        foreach ($hives as $h) { if ((int)$h['id'] === $hiveId) { $found = $h; break; } }
        if (!$found) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        if (!$isAdmin && (string)($found['owner_id'] ?? '') !== $userId) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $hives = array_values(array_filter($hives, fn($h) => (int)$h['id'] !== $hiveId));
        file_put_contents($this->hivesFile, json_encode($hives, JSON_PRETTY_PRINT));
        // Record admin-visible alert about deletion
        $alertsPath = __DIR__ . '/../../storage/alerts.json';
        $existing = is_file($alertsPath) ? json_decode(@file_get_contents($alertsPath), true) : [];
        if (!is_array($existing)) { $existing = []; }
        $existing[] = [
            'type' => 'hive-deleted',
            'level' => 'warning',
            'message' => 'Hive deleted',
            'hive_id' => (int)$found['id'],
            'hive_name' => (string)($found['name'] ?? ''),
            'deleted_by' => (string)($user['id'] ?? ''),
            'created_at' => date('c')
        ];
        @file_put_contents($alertsPath, json_encode($existing, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        if (!$isAdmin) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        $hiveId = (int)$args['id'];
        $hives = $this->readHives();
        $updated = false;
        foreach ($hives as &$h) {
            if ((int)$h['id'] === $hiveId) {
                $h['status'] = 'active';
                $h['approved_at'] = date('c');
                $h['approved_by'] = (string)($user['id'] ?? '');
                $updated = true; break;
            }
        }
        unset($h);
        if (!$updated) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        file_put_contents($this->hivesFile, json_encode($hives, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }
} 