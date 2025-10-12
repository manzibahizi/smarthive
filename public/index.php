<?php

// Sessions for simple auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables only if .env exists
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Helper function to get environment variable
function getEnvVar($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// Helper function to send JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper function to get request data
function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    return $_POST;
}

// Helper function to check authentication
function requireAuth() {
    if (empty($_SESSION['user'])) {
        jsonResponse(['status' => 'error', 'message' => 'Authentication required'], 401);
    }
    return $_SESSION['user'];
}

// Helper function to check admin role
function requireAdmin() {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        jsonResponse(['status' => 'error', 'message' => 'Admin access required'], 403);
    }
    return $user;
}

// Get current request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// When using PHP's built-in server, let it serve existing static files directly
if (PHP_SAPI === 'cli-server') {
    // Serve the dashboard for the root path, gated by login
    if ($path === '/' || $path === '/index.html') {
        if (empty($_SESSION['user'])) {
            readfile(__DIR__ . '/login.html');
        } else {
            readfile(__DIR__ . '/index.html');
        }
        exit;
    }

    // Redirect any .html to extensionless
    if (str_ends_with($path, '.html')) {
        $map = [
            '/index.html' => '/',
            '/hives.html' => '/hives',
            '/analytics.html' => '/analytics',
            '/weather.html' => '/weather',
            '/alerts.html' => '/alerts',
            '/settings.html' => '/settings',
            '/training.html' => '/training',
            '/admin-training.html' => '/admin/training',
            '/admin-hives.html' => '/admin/hives',
            '/admin-users.html' => '/admin/users',
            '/tips.html' => '/tips',
            '/admin-tips.html' => '/admin/tips',
        ];
        $to = $map[$path] ?? rtrim(substr($path, 0, -5), '/') ?: '/';
        header('Location: ' . $to, true, 301);
        exit;
    }

    // Gate other app pages too (general auth) including pretty URLs (no .html)
    $prettyToFile = [
        '/hives' => '/hives.html',
        '/analytics' => '/analytics.html',
        '/weather' => '/weather.html',
        '/alerts' => '/alerts.html',
        '/settings' => '/settings.html',
        '/training' => '/training.html',
        '/admin/training' => '/admin-training.html',
        '/admin/hives' => '/admin-hives.html',
        '/admin/users' => '/admin-users.html',
        '/tips' => '/tips.html',
        '/admin/tips' => '/admin-tips.html',
    ];

    if (isset($prettyToFile[$path])) {
        if (empty($_SESSION['user'])) {
            header('Location: /login.html', true, 302);
            exit;
        }
        readfile(__DIR__ . $prettyToFile[$path]);
        exit;
    }

    // If the requested file exists (e.g., CSS/JS/images), let the server handle it
    $file = __DIR__ . $path;
    if (is_file($file)) {
        return false;
    }
}

// API Routes
if (strpos($path, '/api/') === 0) {
    // Authentication routes
    if ($path === '/api/auth/login' && $requestMethod === 'POST') {
        $data = getRequestData();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        if ($username === '' || $password === '') {
            jsonResponse(['status' => 'error', 'message' => 'Username and password are required'], 400);
        }

        // Development-only master override
        $masterUsername = getEnvVar('MASTER_USERNAME', 'Admin');
        $masterPassword = getEnvVar('MASTER_PASSWORD', 'Admin123');
        if ($username === $masterUsername && $password === $masterPassword) {
            $_SESSION['user'] = [
                'id' => 0,
                'username' => $masterUsername,
                'role' => 'admin'
            ];
            jsonResponse(['status' => 'success']);
        }

        // DB auth
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=smart_hive', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Ensure users table exists and seed admin
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

            $stmt = $pdo->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                throw new RuntimeException('Invalid credentials');
            }

            if (!password_verify($password, $user['password_hash'])) {
                throw new RuntimeException('Invalid credentials');
            }

            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            jsonResponse(['status' => 'success']);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }
    }

    if ($path === '/api/auth/logout' && $requestMethod === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonResponse(['status' => 'success']);
    }

    if ($path === '/api/auth/me' && $requestMethod === 'GET') {
        $user = $_SESSION['user'] ?? null;
        jsonResponse(['authenticated' => (bool)$user, 'user' => $user]);
    }

    // Dashboard routes
    if ($path === '/api/dashboard' && $requestMethod === 'GET') {
        requireAuth();
        jsonResponse(['status' => 'success', 'message' => 'Dashboard data']);
    }

    if ($path === '/api/dashboard/stats' && $requestMethod === 'GET') {
        requireAuth();
        jsonResponse(['status' => 'success', 'stats' => [
            'total_hives' => 5,
            'active_hives' => 4,
            'alerts' => 2
        ]]);
    }

    // Hive routes
    if ($path === '/api/hives' && $requestMethod === 'GET') {
        requireAuth();
        $hivesFile = __DIR__ . '/../storage/hives.json';
        $hives = file_exists($hivesFile) ? json_decode(file_get_contents($hivesFile), true) : [];
        jsonResponse(['status' => 'success', 'hives' => $hives]);
    }

    if ($path === '/api/hives' && $requestMethod === 'POST') {
        $user = requireAuth();
        $data = getRequestData();
        
        $hivesFile = __DIR__ . '/../storage/hives.json';
        $hives = file_exists($hivesFile) ? json_decode(file_get_contents($hivesFile), true) : [];
        
        $newHive = [
            'id' => count($hives) + 1,
            'name' => $data['name'] ?? 'New Hive',
            'location' => $data['location'] ?? '',
            'created_by' => $user['username'],
            'created_at' => date('c'),
            'status' => 'active'
        ];
        
        $hives[] = $newHive;
        file_put_contents($hivesFile, json_encode($hives, JSON_PRETTY_PRINT));
        
        jsonResponse(['status' => 'success', 'hive' => $newHive]);
    }

    if (preg_match('#^/api/hives/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        $user = requireAuth();
        $hiveId = (int)$matches[1];
        
        $hivesFile = __DIR__ . '/../storage/hives.json';
        $hives = file_exists($hivesFile) ? json_decode(file_get_contents($hivesFile), true) : [];
        
        $found = null;
        foreach ($hives as $hive) {
            if ((int)$hive['id'] === $hiveId) {
                $found = $hive;
                break;
            }
        }
        
        if (!$found) {
            jsonResponse(['status' => 'error', 'message' => 'Hive not found'], 404);
        }
        
        $hives = array_values(array_filter($hives, fn($h) => (int)$h['id'] !== $hiveId));
        file_put_contents($hivesFile, json_encode($hives, JSON_PRETTY_PRINT));
        
        // Record alert for admin
        $alertsFile = __DIR__ . '/../storage/alerts.json';
        $alerts = file_exists($alertsFile) ? json_decode(file_get_contents($alertsFile), true) : [];
        $alerts[] = [
            'type' => 'hive-deleted',
            'level' => 'warning',
            'message' => "Hive '{$found['name']}' (ID: {$found['id']}) was deleted by {$user['username']}.",
            'hive_id' => $found['id'],
            'hive_name' => $found['name'],
            'deleted_by' => $user['username'],
            'created_at' => date('c')
        ];
        file_put_contents($alertsFile, json_encode($alerts, JSON_PRETTY_PRINT));
        
        jsonResponse(['status' => 'success']);
    }

    // Weather routes
    if ($path === '/api/weather/current' && $requestMethod === 'GET') {
        requireAuth();
        $weather = [
            'temperature' => 22 + rand(-5, 10),
            'humidity' => 65 + rand(-15, 20),
            'pressure' => 1013 + rand(-20, 20),
            'wind_speed' => rand(5, 25),
            'wind_direction' => ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'][rand(0, 7)],
            'conditions' => ['Sunny', 'Partly Cloudy', 'Cloudy', 'Rainy'][rand(0, 3)],
            'timestamp' => date('c')
        ];
        
        // Check humidity threshold and create alert if needed
        if ($weather['humidity'] > 80) {
            $alertsFile = __DIR__ . '/../storage/alerts.json';
            $alerts = file_exists($alertsFile) ? json_decode(file_get_contents($alertsFile), true) : [];
            $alerts[] = [
                'type' => 'humidity',
                'level' => $weather['humidity'] > 90 ? 'critical' : 'warning',
                'message' => 'Humidity level is high!',
                'value' => $weather['humidity'],
                'unit' => '%',
                'created_at' => date('c')
            ];
            file_put_contents($alertsFile, json_encode($alerts, JSON_PRETTY_PRINT));
        }
        
        jsonResponse(['status' => 'success', 'weather' => $weather]);
    }

    // Sensor routes
    if ($path === '/api/sensors/gas' && $requestMethod === 'GET') {
        requireAuth();
        $value = 180 + rand(0, 40);
        $safeMax = 200;
        $warningThreshold = 250;

        if ($value > $safeMax) {
            $alertsFile = __DIR__ . '/../storage/alerts.json';
            $alerts = file_exists($alertsFile) ? json_decode(file_get_contents($alertsFile), true) : [];
            $alerts[] = [
                'type' => 'gas',
                'level' => $value > $warningThreshold ? 'critical' : 'warning',
                'message' => 'Gas level is high!',
                'value' => $value,
                'unit' => 'ppm',
                'created_at' => date('c')
            ];
            file_put_contents($alertsFile, json_encode($alerts, JSON_PRETTY_PRINT));
        }

        jsonResponse(['status' => 'success', 'sensor' => [
            'type' => 'gas',
            'value' => $value,
            'unit' => 'ppm',
            'safe_max' => $safeMax,
            'warning_threshold' => $warningThreshold,
            'status' => $value > $safeMax ? ($value > $warningThreshold ? 'critical' : 'warning') : 'normal',
            'timestamp' => date('c')
        ]]);
    }

    // Alerts routes
    if ($path === '/api/alerts' && $requestMethod === 'GET') {
        requireAuth();
        $alertsFile = __DIR__ . '/../storage/alerts.json';
        $alerts = file_exists($alertsFile) ? json_decode(file_get_contents($alertsFile), true) : [];
        jsonResponse(['status' => 'success', 'alerts' => $alerts]);
    }

    if ($path === '/api/alerts/clear' && $requestMethod === 'POST') {
        requireAuth();
        $alertsFile = __DIR__ . '/../storage/alerts.json';
        file_put_contents($alertsFile, json_encode([]));
        jsonResponse(['status' => 'success']);
    }

    // Training routes
    if ($path === '/api/training' && $requestMethod === 'GET') {
        requireAuth();
        $trainingFile = __DIR__ . '/../storage/training.json';
        $training = file_exists($trainingFile) ? json_decode(file_get_contents($trainingFile), true) : [];
        jsonResponse(['status' => 'success', 'training' => $training]);
    }

    if ($path === '/api/training' && $requestMethod === 'POST') {
        requireAuth();
        $data = getRequestData();
        
        $trainingFile = __DIR__ . '/../storage/training.json';
        $training = file_exists($trainingFile) ? json_decode(file_get_contents($trainingFile), true) : [];
        
        $newTraining = [
            'id' => count($training) + 1,
            'title' => $data['title'] ?? 'New Training',
            'content' => $data['content'] ?? '',
            'created_at' => date('c')
        ];
        
        $training[] = $newTraining;
        file_put_contents($trainingFile, json_encode($training, JSON_PRETTY_PRINT));
        
        jsonResponse(['status' => 'success', 'training' => $newTraining]);
    }

    // Tips routes
    if ($path === '/api/tips' && $requestMethod === 'GET') {
        requireAuth();
        $tipsFile = __DIR__ . '/../storage/tips.json';
        $tips = file_exists($tipsFile) ? json_decode(file_get_contents($tipsFile), true) : [];
        jsonResponse(['status' => 'success', 'tips' => $tips]);
    }

    if ($path === '/api/tips' && $requestMethod === 'POST') {
        requireAuth();
        $data = getRequestData();
        
        $tipsFile = __DIR__ . '/../storage/tips.json';
        $tips = file_exists($tipsFile) ? json_decode(file_get_contents($tipsFile), true) : [];
        
        $newTip = [
            'id' => count($tips) + 1,
            'title' => $data['title'] ?? 'New Tip',
            'content' => $data['content'] ?? '',
            'created_at' => date('c')
        ];
        
        $tips[] = $newTip;
        file_put_contents($tipsFile, json_encode($tips, JSON_PRETTY_PRINT));
        
        jsonResponse(['status' => 'success', 'tip' => $newTip]);
    }

    // Admin routes
    if ($path === '/api/admin/users' && $requestMethod === 'GET') {
        requireAdmin();
        jsonResponse(['status' => 'success', 'users' => []]);
    }

    if ($path === '/api/admin/training' && $requestMethod === 'GET') {
        requireAdmin();
        $trainingFile = __DIR__ . '/../storage/training.json';
        $training = file_exists($trainingFile) ? json_decode(file_get_contents($trainingFile), true) : [];
        jsonResponse(['status' => 'success', 'training' => $training]);
    }

    if ($path === '/api/admin/tips' && $requestMethod === 'GET') {
        requireAdmin();
        $tipsFile = __DIR__ . '/../storage/tips.json';
        $tips = file_exists($tipsFile) ? json_decode(file_get_contents($tipsFile), true) : [];
        jsonResponse(['status' => 'success', 'tips' => $tips]);
    }

    // 404 for unknown API routes
    jsonResponse(['status' => 'error', 'message' => 'API endpoint not found'], 404);
}

// 404 for unknown routes
http_response_code(404);
echo 'Page not found';