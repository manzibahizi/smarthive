<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TipsController
{
    private string $tipsFile;

    public function __construct()
    {
        $dataDir = __DIR__ . '/../../storage';
        if (!is_dir($dataDir)) { @mkdir($dataDir, 0777, true); }
        $this->tipsFile = $dataDir . '/tips.json';
        if (!file_exists($this->tipsFile)) {
            file_put_contents($this->tipsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    public function list(Request $request, Response $response): Response
    {
        $tips = $this->readTips();
        $response->getBody()->write(json_encode(['status' => 'success', 'tips' => array_values($tips)]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        if (!$isAdmin) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Accept JSON or form data
        $data = $request->getParsedBody() ?: [];
        if (empty($data)) {
            $raw = (string)$request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) { $data = $decoded; }
            }
        }
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $icon = trim((string)($data['icon'] ?? ''));

        if ($title === '' || $description === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Title and description are required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $tips = $this->readTips();
        $nextId = empty($tips) ? 1 : (max(array_map(fn($x)=> (int)($x['id'] ?? 0), $tips)) + 1);
        $new = [
            'id' => $nextId,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'created_at' => date('c'),
            'created_by' => (string)($user['id'] ?? '')
        ];
        $tips[] = $new;
        file_put_contents($this->tipsFile, json_encode($tips, JSON_PRETTY_PRINT));

        $response->getBody()->write(json_encode(['status' => 'success', 'tip' => $new]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        if (!$isAdmin) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody() ?: [];
        if (empty($data)) {
            $raw = (string)$request->getBody();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) { $data = $decoded; }
            }
        }

        $tips = $this->readTips();
        $found = false;
        foreach ($tips as &$t) {
            if ((int)($t['id'] ?? 0) === $id) {
                if (isset($data['title'])) { $t['title'] = trim((string)$data['title']); }
                if (isset($data['description'])) { $t['description'] = trim((string)$data['description']); }
                if (isset($data['icon'])) { $t['icon'] = trim((string)$data['icon']); }
                $t['updated_at'] = date('c');
                $found = true; break;
            }
        }
        unset($t);
        if (!$found) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        file_put_contents($this->tipsFile, json_encode($tips, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        if (!$isAdmin) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Forbidden']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        $id = (int)($args['id'] ?? 0);
        $tips = $this->readTips();
        $before = count($tips);
        $tips = array_values(array_filter($tips, fn($t) => (int)($t['id'] ?? 0) !== $id));
        if (count($tips) === $before) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        file_put_contents($this->tipsFile, json_encode($tips, JSON_PRETTY_PRINT));
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function readTips(): array
    {
        $raw = @file_get_contents($this->tipsFile);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }
}


