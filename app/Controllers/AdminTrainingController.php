<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminTrainingController
{
    private string $resourcesFile;
    private string $applicationsFile;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../../storage';
        if (!is_dir($dataDir)) {@mkdir($dataDir, 0777, true);}    
        $this->resourcesFile = $dataDir . '/training.json';
        $this->applicationsFile = $dataDir . '/training_applications.json';
        if (!file_exists($this->resourcesFile)) {
            file_put_contents($this->resourcesFile, json_encode([], JSON_PRETTY_PRINT));
        }
        if (!file_exists($this->applicationsFile)) {
            file_put_contents($this->applicationsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    private function assertAdmin(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
    }

    public function list(Request $request, Response $response): Response
    {
        $this->assertAdmin();
        $resources = $this->readResources();
        $response->getBody()->write(json_encode(['status' => 'success', 'resources' => $resources]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $this->assertAdmin();
        $data = $request->getParsedBody() ?: [];
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $date = (string)($data['date'] ?? '');
        $published = (bool)($data['published'] ?? false);
        if ($title === '' || $description === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Title and description required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
        $resources = $this->readResources();
        $nextId = empty($resources) ? 1 : (max(array_map(fn($r)=>(int)$r['id'], $resources)) + 1);
        $resources[] = [
            'id' => $nextId,
            'title' => $title,
            'description' => $description,
            'date' => $date,
            'published' => $published
        ];
        file_put_contents($this->resourcesFile, json_encode($resources, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success', 'id' => $nextId]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $this->assertAdmin();
        $id = (int)$args['id'];
        $data = $request->getParsedBody() ?: [];
        $resources = $this->readResources();
        $updated = false;
        foreach ($resources as &$r) {
            if ((int)$r['id'] === $id) {
                $r['title'] = trim((string)($data['title'] ?? $r['title']));
                $r['description'] = trim((string)($data['description'] ?? $r['description']));
                if (isset($data['date'])) { $r['date'] = (string)$data['date']; }
                if (isset($data['published'])) { $r['published'] = (bool)$data['published']; }
                $updated = true; break;
            }
        }
        unset($r);
        if (!$updated) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        file_put_contents($this->resourcesFile, json_encode($resources, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->assertAdmin();
        $id = (int)$args['id'];
        $resources = $this->readResources();
        $before = count($resources);
        $resources = array_values(array_filter($resources, fn($r) => (int)$r['id'] !== $id));
        if (count($resources) === $before) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        file_put_contents($this->resourcesFile, json_encode($resources, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function applicants(Request $request, Response $response, array $args): Response
    {
        $this->assertAdmin();
        $rid = (string)$args['id'];
        $apps = $this->readApplications();
        $list = array_values($apps[$rid] ?? []);
        $response->getBody()->write(json_encode(['status' => 'success', 'applicants' => $list]));
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
}


