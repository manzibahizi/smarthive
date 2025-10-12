<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TrainingController
{
    private string $resourcesFile;
    private string $applicationsFile;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../../storage';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $this->resourcesFile = $dataDir . '/training.json';
        $this->applicationsFile = $dataDir . '/training_applications.json';
        if (!file_exists($this->resourcesFile)) {
            // Seed with a couple of examples (un/published)
            $seed = [
                ['id' => 1, 'title' => 'Basic Beekeeping', 'description' => 'Intro to equipment, hive setup, and safety.', 'published' => true, 'date' => date('Y-m-d', strtotime('+7 days'))],
                ['id' => 2, 'title' => 'Hive Management', 'description' => 'Inspections, pest control, seasonal tasks.', 'published' => false, 'date' => date('Y-m-d', strtotime('+14 days'))]
            ];
            file_put_contents($this->resourcesFile, json_encode($seed, JSON_PRETTY_PRINT));
        }
        if (!file_exists($this->applicationsFile)) {
            file_put_contents($this->applicationsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public function list(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $all = $this->readResources();
        $visible = $isAdmin ? $all : array_values(array_filter($all, fn($r) => !empty($r['published'])));

        // Attach applicant counts for admins
        if ($isAdmin) {
            $counts = $this->applicationCounts();
            foreach ($visible as &$r) {
                $rid = (string)$r['id'];
                $r['applicants'] = $counts[$rid] ?? 0;
            }
            unset($r);
        }

        $response->getBody()->write(json_encode(['status' => 'success', 'resources' => $visible]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $all = $this->readResources();
        $found = null;
        foreach ($all as $r) {
            if ((int)$r['id'] === $id) { $found = $r; break; }
        }
        if (!$found) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        if (!$isAdmin && empty($found['published'])) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(json_encode(['status' => 'success', 'resource' => $found]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apply(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? ['id' => 'guest', 'name' => 'Guest'];
        $userId = (string)($user['id'] ?? 'guest');
        $id = (string)$args['id'];

        // Ensure resource exists and is published
        $all = $this->readResources();
        $resource = null;
        foreach ($all as $r) { if ((string)$r['id'] === $id) { $resource = $r; break; } }
        if (!$resource || empty($resource['published'])) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not available']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $body = $request->getParsedBody() ?: [];
        $name = isset($body['name']) ? trim((string)$body['name']) : '';
        $phone = isset($body['phone']) ? trim((string)$body['phone']) : '';
        $email = isset($body['email']) ? trim((string)$body['email']) : '';
        $location = isset($body['location']) ? trim((string)$body['location']) : '';
        $experience = isset($body['experience']) ? trim((string)$body['experience']) : '';
        $note = isset($body['note']) ? trim((string)$body['note']) : '';

        if ($name === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Name is required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
        // Rwanda phone: +2507[2389]XXXXXXX
        if ($phone === '' || !preg_match('/^\+2507[2389]\d{7}$/', $phone)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Phone must be in format +2507XXXXXXXX']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid email']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
        if ($location === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Location is required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
        if ($experience === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Experience is required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $apps = $this->readApplications();
        $key = $id;
        $existing = $apps[$key] ?? [];
        foreach ($existing as $app) {
            if ((string)($app['user_id'] ?? '') === $userId) {
                $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Already applied']));
                return $response->withHeader('Content-Type', 'application/json');
            }
        }
        $existing[] = [
            'user_id' => $userId,
            'user_name' => $name !== '' ? $name : (string)($user['name'] ?? ''),
            'phone' => $phone,
            'email' => $email,
            'location' => $location,
            'experience' => $experience,
            'note' => $note,
            'applied_at' => date('c')
        ];
        $apps[$key] = $existing;
        file_put_contents($this->applicationsFile, json_encode($apps, JSON_PRETTY_PRINT));

        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function readResources(): array
    {
        $raw = @file_get_contents($this->resourcesFile);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }

    private function readApplications(): array
    {
        $raw = @file_get_contents($this->applicationsFile);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }

    private function applicationCounts(): array
    {
        $apps = $this->readApplications();
        $counts = [];
        foreach ($apps as $rid => $list) { $counts[$rid] = is_array($list) ? count($list) : 0; }
        return $counts;
    }
} 