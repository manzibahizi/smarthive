<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        // TODO: Validate input, create user in database, and return success/error
        $responseData = [
            'status' => 'success',
            'message' => 'User registered successfully'
        ];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        // NOTE: Replace with DB lookup; for now, allow only admin-provisioned style
        if ($username === '' || $password === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Username and password are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Development-only master override to ensure access even without DB
        // Customize via env MASTER_USERNAME / MASTER_PASSWORD
        $masterUsername = getenv('MASTER_USERNAME') !== false ? getenv('MASTER_USERNAME') : 'Admin';
        $masterPassword = getenv('MASTER_PASSWORD') !== false ? getenv('MASTER_PASSWORD') : 'Admin123';
        if ($username === $masterUsername && $password === $masterPassword) {
            $_SESSION['user'] = [
                'id' => 0,
                'username' => $masterUsername,
                'role' => 'admin'
            ];
            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // DB auth
        try {
            $pdo = Database::getConnection();
            // Ensure users table exists and seed admin
            $this->migrate($pdo);

            $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                throw new \RuntimeException('Invalid credentials');
            }

            if (!password_verify($password, $user['password_hash'])) {
                throw new \RuntimeException('Invalid credentials');
            }

            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Invalid credentials']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }

    private function migrate(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM("admin","user") NOT NULL DEFAULT "user",
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        // Seed default admin if not present
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $stmt->execute([':u' => 'Admin']);
        $exists = (int)$stmt->fetchColumn();
        if ($exists === 0) {
            $hash = password_hash('Admin123', PASSWORD_BCRYPT);
            $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (:u, :p, "admin", 1)');
            $ins->execute([':u' => 'Admin', ':p' => $hash]);
        }
    }
} 