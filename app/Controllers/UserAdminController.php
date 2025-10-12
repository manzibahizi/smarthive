<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserAdminController
{
    private function ensureAdmin(): void
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u || ($u['role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            throw new \RuntimeException('Forbidden');
        }
    }

    public function list(Request $request, Response $response): Response
    {
        $this->ensureAdmin();
        $pdo = Database::getConnection();
        $rows = $pdo->query('SELECT id, username, role, is_active, created_at FROM users ORDER BY id DESC')->fetchAll();
        $response->getBody()->write(json_encode(['status' => 'success', 'users' => $rows]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $this->ensureAdmin();
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $role = in_array(($data['role'] ?? 'user'), ['admin','user'], true) ? $data['role'] : 'user';
        if ($username === '' || $password === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Username and password required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (:u, :p, :r, 1)');
        try {
            $stmt->execute([':u' => $username, ':p' => password_hash($password, PASSWORD_BCRYPT), ':r' => $role]);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Username already exists']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $this->ensureAdmin();
        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();
        $role = in_array(($data['role'] ?? 'user'), ['admin','user'], true) ? $data['role'] : 'user';
        $isActive = isset($data['is_active']) ? (int)!!$data['is_active'] : 1;
        $password = trim($data['password'] ?? '');
        $pdo = Database::getConnection();
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET role = :r, is_active = :a, password_hash = :p WHERE id = :id');
            $stmt->execute([':r'=>$role, ':a'=>$isActive, ':p'=>password_hash($password, PASSWORD_BCRYPT), ':id'=>$id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET role = :r, is_active = :a WHERE id = :id');
            $stmt->execute([':r'=>$role, ':a'=>$isActive, ':id'=>$id]);
        }
        $response->getBody()->write(json_encode(['status'=>'success']));
        return $response->withHeader('Content-Type','application/json');
    }
}


