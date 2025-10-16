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
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
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
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Ensure users table exists
            $pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM("admin","user") NOT NULL DEFAULT "user",
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
            
            $stmt = $pdo->query('SELECT id, username, role, is_active, created_at FROM users ORDER BY id DESC');
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['status' => 'success', 'users' => $users]);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    if ($path === '/api/admin/users' && $requestMethod === 'POST') {
        requireAdmin();
        $data = getRequestData();
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $role = in_array(($data['role'] ?? 'user'), ['admin','user'], true) ? $data['role'] : 'user';
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        
        // Validate required fields with specific messages
        if ($username === '') {
            jsonResponse(['status' => 'error', 'message' => 'Username is required'], 400);
        }
        
        if ($password === '') {
            jsonResponse(['status' => 'error', 'message' => 'Password is required'], 400);
        }
        
        if ($email === '') {
            jsonResponse(['status' => 'error', 'message' => 'Email address is required'], 400);
        }
        
        // Validate field lengths
        if (strlen($username) < 3) {
            jsonResponse(['status' => 'error', 'message' => 'Username must be at least 3 characters long'], 400);
        }
        
        if (strlen($password) < 6) {
            jsonResponse(['status' => 'error', 'message' => 'Password must be at least 6 characters long'], 400);
        }
        
        if (strlen($username) > 50) {
            jsonResponse(['status' => 'error', 'message' => 'Username must be less than 50 characters'], 400);
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid email format'], 400);
        }
        
        // Validate phone format (basic validation)
        if (!empty($phone) && !preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid phone number format'], 400);
        }
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Ensure users table exists
            $pdo->exec('CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM("admin","user") NOT NULL DEFAULT "user",
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
            
            // Add email and phone columns if they don't exist (for existing installations)
            try {
                $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT ""');
            } catch (PDOException $e) {
                // Column already exists, ignore
            }
            
            try {
                $pdo->exec('ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL');
            } catch (PDOException $e) {
                // Column already exists, ignore
            }
            
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, email, phone, role, is_active) VALUES (:u, :p, :e, :ph, :r, 1)');
            $stmt->execute([
                ':u' => $username, 
                ':p' => password_hash($password, PASSWORD_BCRYPT), 
                ':e' => $email,
                ':ph' => $phone,
                ':r' => $role
            ]);
            
            jsonResponse(['status' => 'success', 'message' => "User '{$username}' created successfully with email '{$email}'"]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                jsonResponse(['status' => 'error', 'message' => "Username '{$username}' already exists. Please choose a different username."], 409);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
            }
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    if (preg_match('#^/api/admin/users/(\d+)$#', $path, $matches) && $requestMethod === 'PUT') {
        requireAdmin();
        $userId = (int)$matches[1];
        $data = getRequestData();
        $role = in_array(($data['role'] ?? 'user'), ['admin','user'], true) ? $data['role'] : 'user';
        $isActive = isset($data['is_active']) ? (int)!!$data['is_active'] : 1;
        $password = trim($data['password'] ?? '');
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET role = :r, is_active = :a, password_hash = :p WHERE id = :id');
                $stmt->execute([
                    ':r' => $role, 
                    ':a' => $isActive, 
                    ':p' => password_hash($password, PASSWORD_BCRYPT), 
                    ':id' => $userId
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET role = :r, is_active = :a WHERE id = :id');
                $stmt->execute([':r' => $role, ':a' => $isActive, ':id' => $userId]);
            }
            
            jsonResponse(['status' => 'success', 'message' => 'User updated successfully']);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    if ($path === '/api/admin/training' && $requestMethod === 'GET') {
        requireAdmin();
        $trainingFile = __DIR__ . '/../storage/training.json';
        $training = file_exists($trainingFile) ? json_decode(file_get_contents($trainingFile), true) : [];
        jsonResponse(['status' => 'success', 'training' => $training]);
    }

    if ($path === '/api/admin/training' && $requestMethod === 'POST') {
        requireAdmin();
        $data = getRequestData();
        
        $trainingFile = __DIR__ . '/../storage/training.json';
        $training = file_exists($trainingFile) ? json_decode(file_get_contents($trainingFile), true) : [];
        
        $newTraining = [
            'id' => count($training) + 1,
            'title' => $data['title'] ?? 'New Training',
            'description' => $data['description'] ?? '',
            'date' => $data['date'] ?? date('Y-m-d'),
            'published' => isset($data['published']) ? (bool)$data['published'] : false,
            'created_at' => date('c')
        ];
        
        $training[] = $newTraining;
        file_put_contents($trainingFile, json_encode($training, JSON_PRETTY_PRINT));
        
        jsonResponse(['status' => 'success', 'training' => $newTraining]);
    }

    if (preg_match('#^/api/admin/training/(\d+)/applicants$#', $path, $matches) && $requestMethod === 'GET') {
        requireAdmin();
        $trainingId = (int)$matches[1];
        try {
            $applicationsData = json_decode(file_get_contents(__DIR__ . '/../storage/training_applications.json'), true) ?: [];
            $applicants = array_filter($applicationsData, function($app) use ($trainingId) {
                return $app['training_id'] == $trainingId;
            });
            jsonResponse(['status' => 'success', 'applicants' => array_values($applicants)]);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to load applicants: ' . $e->getMessage()], 500);
        }
    }
    
    if (preg_match('#^/api/admin/training/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        requireAdmin();
        $id = (int)$matches[1];
        try {
            $trainingData = json_decode(file_get_contents(__DIR__ . '/../storage/training.json'), true) ?: [];
            $found = false;
            foreach ($trainingData as $i => $t) {
                if ($t['id'] == $id) {
                    unset($trainingData[$i]);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                jsonResponse(['status' => 'error', 'message' => 'Training not found'], 404);
            }
            file_put_contents(__DIR__ . '/../storage/training.json', json_encode(array_values($trainingData), JSON_PRETTY_PRINT));
            jsonResponse(['status' => 'success', 'message' => 'Training deleted successfully']);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete training: ' . $e->getMessage()], 500);
        }
    }
    
    if (preg_match('#^/api/admin/training/(\d+)$#', $path, $matches) && $requestMethod === 'PUT') {
        requireAdmin();
        $trainingId = (int)$matches[1];
        $data = getRequestData();
        
        $trainingFile = __DIR__ . '/../storage/training.json';
        $training = file_exists($trainingFile) ? json_decode(file_get_contents($trainingFile), true) : [];
        
        $found = false;
        foreach ($training as &$t) {
            if ((int)$t['id'] === $trainingId) {
                $t['title'] = $data['title'] ?? $t['title'];
                $t['description'] = $data['description'] ?? $t['description'];
                $t['date'] = $data['date'] ?? $t['date'];
                $t['published'] = isset($data['published']) ? (bool)$data['published'] : $t['published'];
                $t['active'] = isset($data['active']) ? (bool)$data['active'] : $t['active'];
                $t['updated_at'] = date('c');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            jsonResponse(['status' => 'error', 'message' => 'Training not found'], 404);
        }
        
        file_put_contents($trainingFile, json_encode($training, JSON_PRETTY_PRINT));
        jsonResponse(['status' => 'success', 'message' => 'Training updated successfully']);
    }

    if ($path === '/api/admin/tips' && $requestMethod === 'GET') {
        requireAdmin();
        $tipsFile = __DIR__ . '/../storage/tips.json';
        $tips = file_exists($tipsFile) ? json_decode(file_get_contents($tipsFile), true) : [];
        jsonResponse(['status' => 'success', 'tips' => $tips]);
    }

    if ($path === '/api/admin/tips' && $requestMethod === 'POST') {
        requireAdmin();
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

    if (preg_match('#^/api/admin/tips/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        requireAdmin();
        $id = (int)$matches[1];
        try {
            $tipsData = json_decode(file_get_contents(__DIR__ . '/../storage/tips.json'), true) ?: [];
            $found = false;
            foreach ($tipsData as $i => $t) {
                if ($t['id'] == $id) {
                    unset($tipsData[$i]);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                jsonResponse(['status' => 'error', 'message' => 'Tip not found'], 404);
            }
            file_put_contents(__DIR__ . '/../storage/tips.json', json_encode(array_values($tipsData), JSON_PRETTY_PRINT));
            jsonResponse(['status' => 'success', 'message' => 'Tip deleted successfully']);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete tip: ' . $e->getMessage()], 500);
        }
    }
    
    if (preg_match('#^/api/admin/tips/(\d+)$#', $path, $matches) && $requestMethod === 'PUT') {
        requireAdmin();
        $tipId = (int)$matches[1];
        $data = getRequestData();
        
        $tipsFile = __DIR__ . '/../storage/tips.json';
        $tips = file_exists($tipsFile) ? json_decode(file_get_contents($tipsFile), true) : [];
        
        $found = false;
        foreach ($tips as &$t) {
            if ((int)$t['id'] === $tipId) {
                $t['title'] = $data['title'] ?? $t['title'];
                $t['description'] = $data['description'] ?? $t['description'];
                $t['icon'] = $data['icon'] ?? $t['icon'];
                $t['updated_at'] = date('c');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            jsonResponse(['status' => 'error', 'message' => 'Tip not found'], 404);
        }
        
        file_put_contents($tipsFile, json_encode($tips, JSON_PRETTY_PRINT));
        jsonResponse(['status' => 'success', 'message' => 'Tip updated successfully']);
    }

    // Sensor Data API Endpoints
    if ($path === '/api/sensor/data' && $requestMethod === 'POST') {
        $data = getRequestData();
        
        // Validate sensor authentication
        $sensorId = $data['sensor_id'] ?? null;
        $sensorKey = $data['sensor_key'] ?? null;
        
        if (!$sensorId || !$sensorKey) {
            jsonResponse(['status' => 'error', 'message' => 'Sensor ID and key required'], 400);
        }
        
        // Validate sensor credentials (you can implement proper sensor authentication)
        if (!validateSensorCredentials($sensorId, $sensorKey)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid sensor credentials'], 401);
        }
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Create sensor_data table if it doesn't exist
            $pdo->exec('CREATE TABLE IF NOT EXISTS sensor_data (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sensor_id VARCHAR(50) NOT NULL,
                hive_id VARCHAR(50) NOT NULL,
                temperature DECIMAL(5,2),
                humidity DECIMAL(5,2),
                gas_level DECIMAL(5,2),
                hive_weight DECIMAL(8,2),
                battery_level INT,
                signal_strength INT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sensor_hive (sensor_id, hive_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
            
            // Insert sensor data
            $stmt = $pdo->prepare('INSERT INTO sensor_data (sensor_id, hive_id, temperature, humidity, gas_level, hive_weight, battery_level, signal_strength) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $sensorId,
                $data['hive_id'] ?? 'default',
                $data['temperature'] ?? null,
                $data['humidity'] ?? null,
                $data['gas_level'] ?? null,
                $data['hive_weight'] ?? null,
                $data['battery_level'] ?? null,
                $data['signal_strength'] ?? null
            ]);
            
            // Update hive status with latest data
            // Update hive status with health analysis
            $healthAnalysis = analyzeHiveHealth($data);
            updateHiveStatus($pdo, $data['hive_id'] ?? 'default', array_merge($data, $healthAnalysis));
            
            // Check for alerts
            checkAndCreateAlerts($pdo, $data);
            
            jsonResponse(['status' => 'success', 'message' => 'Sensor data recorded']);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/sensor/status' && $requestMethod === 'GET') {
        $sensorId = $_GET['sensor_id'] ?? null;
        
        if (!$sensorId) {
            jsonResponse(['status' => 'error', 'message' => 'Sensor ID required'], 400);
        }
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Get latest sensor data
            $stmt = $pdo->prepare('SELECT * FROM sensor_data WHERE sensor_id = ? ORDER BY timestamp DESC LIMIT 1');
            $stmt->execute([$sensorId]);
            $latestData = $stmt->fetch();
            
            // Get sensor configuration
            $stmt = $pdo->prepare('SELECT * FROM sensors WHERE sensor_id = ?');
            $stmt->execute([$sensorId]);
            $sensorConfig = $stmt->fetch();
            
            jsonResponse([
                'status' => 'success',
                'sensor_id' => $sensorId,
                'latest_data' => $latestData,
                'sensor_config' => $sensorConfig,
                'timestamp' => date('c')
            ]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/sensor/register' && $requestMethod === 'POST') {
        requireAdmin();
        $data = getRequestData();
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Create sensors table if it doesn't exist
            $pdo->exec('CREATE TABLE IF NOT EXISTS sensors (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sensor_id VARCHAR(50) NOT NULL UNIQUE,
                sensor_key VARCHAR(255) NOT NULL,
                hive_id VARCHAR(50) NOT NULL,
                sensor_type ENUM("temperature","humidity","gas","weight","multi") NOT NULL DEFAULT "multi",
                location VARCHAR(255),
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sensor_id (sensor_id),
                INDEX idx_hive_id (hive_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
            
            $sensorId = $data['sensor_id'] ?? uniqid('sensor_');
            $sensorKey = $data['sensor_key'] ?? bin2hex(random_bytes(16));
            $hiveId = $data['hive_id'] ?? 'default';
            
            $stmt = $pdo->prepare('INSERT INTO sensors (sensor_id, sensor_key, hive_id, sensor_type, location, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([
                $sensorId,
                password_hash($sensorKey, PASSWORD_BCRYPT),
                $hiveId,
                $data['sensor_type'] ?? 'multi',
                $data['location'] ?? null
            ]);
            
            jsonResponse([
                'status' => 'success',
                'message' => 'Sensor registered successfully',
                'sensor_id' => $sensorId,
                'sensor_key' => $sensorKey
            ]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    if ($path === '/api/admin/sensors' && $requestMethod === 'GET') {
        requireAdmin();
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Get sensors with latest data
            $stmt = $pdo->query('
                SELECT s.*, 
                       sd.temperature, sd.humidity, sd.gas_level, sd.hive_weight, 
                       sd.battery_level, sd.signal_strength, sd.timestamp as last_update
                FROM sensors s
                LEFT JOIN sensor_data sd ON s.sensor_id = sd.sensor_id
                LEFT JOIN (
                    SELECT sensor_id, MAX(timestamp) as max_timestamp
                    FROM sensor_data
                    GROUP BY sensor_id
                ) latest ON s.sensor_id = latest.sensor_id AND sd.timestamp = latest.max_timestamp
                ORDER BY s.created_at DESC
            ');
            $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['status' => 'success', 'sensors' => $sensors]);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    if (preg_match('#^/api/admin/sensors/([^/]+)/toggle$#', $path, $matches) && $requestMethod === 'POST') {
        requireAdmin();
        $sensorId = $matches[1];
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $stmt = $pdo->prepare('UPDATE sensors SET is_active = NOT is_active WHERE sensor_id = ?');
            $stmt->execute([$sensorId]);
            
            jsonResponse(['status' => 'success', 'message' => 'Sensor status updated']);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    if (preg_match('#^/api/admin/sensors/([^/]+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        requireAdmin();
        $sensorId = $matches[1];
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $stmt = $pdo->prepare('DELETE FROM sensors WHERE sensor_id = ?');
            $stmt->execute([$sensorId]);
            
            jsonResponse(['status' => 'success', 'message' => 'Sensor deleted successfully']);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/admin/sensor-data-overview' && $requestMethod === 'GET') {
        requireAdmin();
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Get average values from latest sensor data
            $stmt = $pdo->query('
                SELECT 
                    AVG(temperature) as temperature,
                    AVG(humidity) as humidity,
                    AVG(gas_level) as gas_level,
                    AVG(hive_weight) as hive_weight
                FROM sensor_data sd1
                WHERE sd1.timestamp = (
                    SELECT MAX(sd2.timestamp)
                    FROM sensor_data sd2
                    WHERE sd2.sensor_id = sd1.sensor_id
                )
            ');
            $averages = $stmt->fetch(PDO::FETCH_ASSOC);
            
            jsonResponse(['status' => 'success', 'averages' => $averages]);
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/system/health' && $requestMethod === 'GET') {
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $health = [
                'timestamp' => date('c'),
                'api_server' => 'online',
                'database' => 'connected',
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'server_time' => date('Y-m-d H:i:s'),
                'uptime' => time() - $_SERVER['REQUEST_TIME']
            ];
            
            // Get sensor statistics
            try {
                $stmt = $pdo->query('SELECT COUNT(*) as total, SUM(is_active) as active FROM sensors');
                $sensorStats = $stmt->fetch(PDO::FETCH_ASSOC);
                $health['sensors'] = $sensorStats;
            } catch (Throwable $e) {
                $health['sensors'] = ['total' => 0, 'active' => 0];
            }
            
            // Get alert statistics
            try {
                $stmt = $pdo->query('SELECT COUNT(*) as total, SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) as active FROM alerts');
                $alertStats = $stmt->fetch(PDO::FETCH_ASSOC);
                $health['alerts'] = $alertStats;
            } catch (Throwable $e) {
                $health['alerts'] = ['total' => 0, 'active' => 0];
            }
            
            // Get data statistics
            try {
                $stmt = $pdo->query('SELECT COUNT(*) as total FROM sensor_data');
                $dataStats = $stmt->fetch(PDO::FETCH_ASSOC);
                $health['data_records'] = $dataStats['total'];
            } catch (Throwable $e) {
                $health['data_records'] = 0;
            }
            
            jsonResponse(['status' => 'success', 'health' => $health]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Health check failed: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/system/backup' && $requestMethod === 'POST') {
        requireAdmin();
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $backupData = [
                'timestamp' => date('c'),
                'version' => '1.0.0',
                'tables' => []
            ];
            
            // Get all tables
            $tables = ['users', 'sensors', 'sensor_data', 'hive_status', 'alerts'];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT * FROM $table");
                    $backupData['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    $backupData['tables'][$table] = [];
                }
            }
            
            // Save backup file
            $backupFile = __DIR__ . '/../storage/backup_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT));
            
            jsonResponse([
                'status' => 'success', 
                'message' => 'Backup created successfully',
                'backup_file' => basename($backupFile),
                'records_backed_up' => array_sum(array_map('count', $backupData['tables']))
            ]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    if ($path === '/api/hive/status' && $requestMethod === 'GET') {
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Get latest sensor data for each hive
            $stmt = $pdo->query('
                SELECT 
                    hs.hive_id,
                    hs.temperature,
                    hs.humidity,
                    hs.gas_level,
                    hs.hive_weight,
                    hs.battery_level,
                    hs.signal_strength,
                    hs.health_status,
                    hs.last_updated,
                    s.sensor_id,
                    s.is_active
                FROM hive_status hs
                LEFT JOIN sensors s ON hs.hive_id = s.hive_id
                WHERE s.is_active = 1 OR s.is_active IS NULL
                ORDER BY hs.last_updated DESC
            ');
            
            $hives = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $hives[] = [
                    'hive_id' => $row['hive_id'],
                    'temperature' => $row['temperature'],
                    'humidity' => $row['humidity'],
                    'gas_level' => $row['gas_level'],
                    'hive_weight' => $row['hive_weight'],
                    'battery_level' => $row['battery_level'],
                    'signal_strength' => $row['signal_strength'],
                    'health_status' => $row['health_status'],
                    'last_updated' => $row['last_updated'],
                    'sensor_id' => $row['sensor_id'],
                    'is_active' => $row['is_active']
                ];
            }
            
            jsonResponse(['status' => 'success', 'hives' => $hives]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to load hive status: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/hive/list' && $requestMethod === 'GET') {
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $stmt = $pdo->query('SELECT DISTINCT hive_id FROM hive_status ORDER BY hive_id');
            $hives = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $hives[] = ['hive_id' => $row['hive_id']];
            }
            
            jsonResponse(['status' => 'success', 'hives' => $hives]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to load hive list: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/sensor/data/live' && $requestMethod === 'GET') {
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            // Get sensor data from last 24 hours
            $stmt = $pdo->query('
                SELECT 
                    temperature,
                    humidity,
                    gas_level,
                    hive_weight,
                    battery_level,
                    signal_strength,
                    timestamp
                FROM sensor_data 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY timestamp ASC
                LIMIT 100
            ');
            
            $sensorData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['status' => 'success', 'sensor_data' => $sensorData]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to load sensor data: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/tasks/upcoming' && $requestMethod === 'GET') {
        try {
            $tasksFile = __DIR__ . '/../storage/tasks.json';
            $tasks = [];
            
            if (file_exists($tasksFile)) {
                $tasks = json_decode(file_get_contents($tasksFile), true) ?: [];
            }
            
            // Filter upcoming tasks (next 7 days)
            $upcomingTasks = array_filter($tasks, function($task) {
                $taskDate = new DateTime($task['scheduled_date']);
                $now = new DateTime();
                $weekFromNow = (new DateTime())->add(new DateInterval('P7D'));
                
                return $taskDate >= $now && $taskDate <= $weekFromNow && !$task['completed'];
            });
            
            // Sort by priority and date
            usort($upcomingTasks, function($a, $b) {
                $priorityOrder = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
                $aPriority = $priorityOrder[$a['priority']] ?? 0;
                $bPriority = $priorityOrder[$b['priority']] ?? 0;
                
                if ($aPriority === $bPriority) {
                    return strtotime($a['scheduled_date']) - strtotime($b['scheduled_date']);
                }
                return $bPriority - $aPriority;
            });
            
            jsonResponse(['status' => 'success', 'tasks' => array_values($upcomingTasks)]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to load tasks: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/inspections' && $requestMethod === 'POST') {
        try {
            $data = getRequestData();
            
            $task = [
                'id' => uniqid(),
                'type' => 'inspection',
                'title' => ucfirst($data['type']) . ' Inspection - Hive ' . $data['hive_id'],
                'description' => 'Scheduled inspection for Hive ' . $data['hive_id'],
                'hive_id' => $data['hive_id'],
                'inspection_type' => $data['type'],
                'priority' => $data['priority'],
                'scheduled_date' => $data['scheduled_date'],
                'notes' => $data['notes'] ?? '',
                'completed' => false,
                'created_at' => date('c')
            ];
            
            $tasksFile = __DIR__ . '/../storage/tasks.json';
            $tasks = [];
            
            if (file_exists($tasksFile)) {
                $tasks = json_decode(file_get_contents($tasksFile), true) ?: [];
            }
            
            $tasks[] = $task;
            file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
            
            jsonResponse(['status' => 'success', 'task' => $task]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to schedule inspection: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/harvests' && $requestMethod === 'POST') {
        try {
            $data = getRequestData();
            
            $harvest = [
                'id' => uniqid(),
                'hive_id' => $data['hive_id'],
                'harvest_date' => $data['harvest_date'],
                'honey_weight' => $data['honey_weight'],
                'honey_type' => $data['honey_type'],
                'honey_grade' => $data['honey_grade'],
                'notes' => $data['notes'] ?? '',
                'created_at' => date('c')
            ];
            
            $harvestsFile = __DIR__ . '/../storage/harvests.json';
            $harvests = [];
            
            if (file_exists($harvestsFile)) {
                $harvests = json_decode(file_get_contents($harvestsFile), true) ?: [];
            }
            
            $harvests[] = $harvest;
            file_put_contents($harvestsFile, json_encode($harvests, JSON_PRETTY_PRINT));
            
            jsonResponse(['status' => 'success', 'harvest' => $harvest]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to record harvest: ' . $e->getMessage()], 500);
        }
    }
    
    if ($path === '/api/weather/current' && $requestMethod === 'GET') {
        try {
            // Mock weather data - in production, integrate with real weather API
            $weather = [
                'temperature' => rand(15, 35),
                'humidity' => rand(40, 80),
                'wind_speed' => rand(0, 20),
                'conditions' => ['sunny', 'cloudy', 'rainy', 'partly_cloudy'][rand(0, 3)],
                'forecast' => [
                    'today' => ['sunny', 28, 15],
                    'tomorrow' => ['cloudy', 25, 18],
                    'day_after' => ['rainy', 22, 20]
                ],
                'beekeeping_advice' => [
                    'activity_level' => 'high',
                    'recommendation' => 'Good weather for hive inspection',
                    'feeding_needed' => false,
                    'ventilation_needed' => false
                ]
            ];
            
            jsonResponse(['status' => 'success', 'weather' => $weather]);
            
        } catch (Throwable $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to load weather: ' . $e->getMessage()], 500);
        }
    }

    // 404 for unknown API routes
    jsonResponse(['status' => 'error', 'message' => 'API endpoint not found'], 404);
}

// Helper function to analyze hive health
function analyzeHiveHealth($sensorData) {
    $score = 100;
    $issues = [];
    $recommendations = [];
    
    // Temperature analysis (optimal: 32-35°C)
    if ($sensorData['temperature'] < 15) {
        $score -= 30;
        $issues[] = 'Temperature too low - bees may be inactive';
        $recommendations[] = 'Check hive insulation and consider feeding';
    } elseif ($sensorData['temperature'] > 40) {
        $score -= 25;
        $issues[] = 'Temperature too high - risk of overheating';
        $recommendations[] = 'Ensure proper ventilation and shade';
    } elseif ($sensorData['temperature'] < 20 || $sensorData['temperature'] > 35) {
        $score -= 10;
        $issues[] = 'Temperature outside optimal range';
    }
    
    // Humidity analysis (optimal: 50-60%)
    if ($sensorData['humidity'] < 30) {
        $score -= 20;
        $issues[] = 'Humidity too low - may affect brood development';
        $recommendations[] = 'Add water source near hive';
    } elseif ($sensorData['humidity'] > 80) {
        $score -= 25;
        $issues[] = 'Humidity too high - risk of mold and disease';
        $recommendations[] = 'Improve hive ventilation';
    }
    
    // Gas level analysis (CO2 levels)
    if ($sensorData['gas_level'] > 200) {
        $score -= 35;
        $issues[] = 'High gas levels detected - ventilation issue';
        $recommendations[] = 'Check hive ventilation immediately';
    } elseif ($sensorData['gas_level'] > 100) {
        $score -= 15;
        $issues[] = 'Elevated gas levels';
        $recommendations[] = 'Monitor ventilation';
    }
    
    // Hive weight analysis
    if ($sensorData['hive_weight'] < 20) {
        $score -= 20;
        $issues[] = 'Low hive weight - possible food shortage';
        $recommendations[] = 'Check food stores and consider feeding';
    }
    
    // Battery level analysis
    if ($sensorData['battery_level'] < 20) {
        $score -= 10;
        $issues[] = 'Low sensor battery';
        $recommendations[] = 'Replace sensor battery soon';
    }
    
    // Signal strength analysis
    if ($sensorData['signal_strength'] < 30) {
        $score -= 5;
        $issues[] = 'Weak sensor signal';
        $recommendations[] = 'Check sensor connectivity';
    }
    
    // Determine overall status
    if ($score >= 80) {
        $status = 'healthy';
    } elseif ($score >= 60) {
        $status = 'warning';
    } else {
        $status = 'critical';
    }
    
    $notes = '';
    if (!empty($issues)) {
        $notes .= 'Issues: ' . implode(', ', $issues) . '. ';
    }
    if (!empty($recommendations)) {
        $notes .= 'Recommendations: ' . implode(', ', $recommendations) . '.';
    }
    
    return [
        'status' => $status,
        'score' => max(0, $score),
        'issues' => $issues,
        'recommendations' => $recommendations,
        'notes' => $notes
    ];
}

// Helper functions for sensor operations
function validateSensorCredentials($sensorId, $sensorKey) {
    try {
        require_once __DIR__ . '/../app/Database.php';
        $pdo = \App\Database::getConnection();
        
        $stmt = $pdo->prepare('SELECT sensor_key FROM sensors WHERE sensor_id = ? AND is_active = 1');
        $stmt->execute([$sensorId]);
        $sensor = $stmt->fetch();
        
        return $sensor && password_verify($sensorKey, $sensor['sensor_key']);
    } catch (Throwable $e) {
        return false;
    }
}

function updateHiveStatus($pdo, $hiveId, $data) {
    try {
        // Create hive_status table if it doesn't exist
        $pdo->exec('CREATE TABLE IF NOT EXISTS hive_status (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            hive_id VARCHAR(50) NOT NULL UNIQUE,
            temperature DECIMAL(5,2),
            humidity DECIMAL(5,2),
            gas_level DECIMAL(5,2),
            hive_weight DECIMAL(8,2),
            battery_level INT,
            signal_strength INT,
            status ENUM("healthy","warning","critical") DEFAULT "healthy",
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hive_id (hive_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
        
        // Determine status based on sensor readings
        $status = 'healthy';
        if (isset($data['temperature']) && ($data['temperature'] < 15 || $data['temperature'] > 35)) {
            $status = 'warning';
        }
        if (isset($data['humidity']) && ($data['humidity'] < 30 || $data['humidity'] > 80)) {
            $status = 'warning';
        }
        if (isset($data['gas_level']) && $data['gas_level'] > 100) {
            $status = 'critical';
        }
        
        $stmt = $pdo->prepare('INSERT INTO hive_status (hive_id, temperature, humidity, gas_level, hive_weight, battery_level, signal_strength, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE temperature = VALUES(temperature), humidity = VALUES(humidity), gas_level = VALUES(gas_level), hive_weight = VALUES(hive_weight), battery_level = VALUES(battery_level), signal_strength = VALUES(signal_strength), status = VALUES(status), last_update = CURRENT_TIMESTAMP');
        $stmt->execute([
            $hiveId,
            $data['temperature'] ?? null,
            $data['humidity'] ?? null,
            $data['gas_level'] ?? null,
            $data['hive_weight'] ?? null,
            $data['battery_level'] ?? null,
            $data['signal_strength'] ?? null,
            $status
        ]);
        
    } catch (Throwable $e) {
        error_log('Error updating hive status: ' . $e->getMessage());
    }
}

function checkAndCreateAlerts($pdo, $data) {
    try {
        // Create alerts table if it doesn't exist
        $pdo->exec('CREATE TABLE IF NOT EXISTS alerts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            hive_id VARCHAR(50) NOT NULL,
            alert_type ENUM("temperature","humidity","gas","weight","battery","signal") NOT NULL,
            severity ENUM("low","medium","high","critical") NOT NULL,
            message TEXT NOT NULL,
            sensor_value DECIMAL(10,2),
            threshold_value DECIMAL(10,2),
            is_resolved TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX idx_hive_id (hive_id),
            INDEX idx_alert_type (alert_type),
            INDEX idx_severity (severity),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
        
        $hiveId = $data['hive_id'] ?? 'default';
        $alerts = [];
        
        // Check temperature alerts
        if (isset($data['temperature'])) {
            if ($data['temperature'] < 10) {
                $alerts[] = ['type' => 'temperature', 'severity' => 'critical', 'message' => 'Critical: Temperature too low', 'value' => $data['temperature'], 'threshold' => 10];
            } elseif ($data['temperature'] < 15) {
                $alerts[] = ['type' => 'temperature', 'severity' => 'high', 'message' => 'Warning: Temperature low', 'value' => $data['temperature'], 'threshold' => 15];
            } elseif ($data['temperature'] > 40) {
                $alerts[] = ['type' => 'temperature', 'severity' => 'critical', 'message' => 'Critical: Temperature too high', 'value' => $data['temperature'], 'threshold' => 40];
            } elseif ($data['temperature'] > 35) {
                $alerts[] = ['type' => 'temperature', 'severity' => 'high', 'message' => 'Warning: Temperature high', 'value' => $data['temperature'], 'threshold' => 35];
            }
        }
        
        // Check humidity alerts
        if (isset($data['humidity'])) {
            if ($data['humidity'] < 20) {
                $alerts[] = ['type' => 'humidity', 'severity' => 'critical', 'message' => 'Critical: Humidity too low', 'value' => $data['humidity'], 'threshold' => 20];
            } elseif ($data['humidity'] < 30) {
                $alerts[] = ['type' => 'humidity', 'severity' => 'high', 'message' => 'Warning: Humidity low', 'value' => $data['humidity'], 'threshold' => 30];
            } elseif ($data['humidity'] > 90) {
                $alerts[] = ['type' => 'humidity', 'severity' => 'critical', 'message' => 'Critical: Humidity too high', 'value' => $data['humidity'], 'threshold' => 90];
            } elseif ($data['humidity'] > 80) {
                $alerts[] = ['type' => 'humidity', 'severity' => 'high', 'message' => 'Warning: Humidity high', 'value' => $data['humidity'], 'threshold' => 80];
            }
        }
        
        // Check gas level alerts
        if (isset($data['gas_level'])) {
            if ($data['gas_level'] > 200) {
                $alerts[] = ['type' => 'gas', 'severity' => 'critical', 'message' => 'Critical: Gas level dangerous', 'value' => $data['gas_level'], 'threshold' => 200];
            } elseif ($data['gas_level'] > 100) {
                $alerts[] = ['type' => 'gas', 'severity' => 'high', 'message' => 'Warning: Gas level elevated', 'value' => $data['gas_level'], 'threshold' => 100];
            }
        }
        
        // Check battery level alerts
        if (isset($data['battery_level'])) {
            if ($data['battery_level'] < 10) {
                $alerts[] = ['type' => 'battery', 'severity' => 'critical', 'message' => 'Critical: Battery very low', 'value' => $data['battery_level'], 'threshold' => 10];
            } elseif ($data['battery_level'] < 20) {
                $alerts[] = ['type' => 'battery', 'severity' => 'high', 'message' => 'Warning: Battery low', 'value' => $data['battery_level'], 'threshold' => 20];
            }
        }
        
        // Insert alerts and send notifications
        foreach ($alerts as $alert) {
            $stmt = $pdo->prepare('INSERT INTO alerts (hive_id, alert_type, severity, message, sensor_value, threshold_value) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $hiveId,
                $alert['type'],
                $alert['severity'],
                $alert['message'],
                $alert['value'],
                $alert['threshold']
            ]);
            
            // Send notifications for critical and high severity alerts
            if (in_array($alert['severity'], ['critical', 'high'])) {
                sendAlertNotifications($pdo, $alert, $hiveId);
            }
        }
        
    } catch (Throwable $e) {
        error_log('Error creating alerts: ' . $e->getMessage());
    }
}

function sendAlertNotifications($pdo, $alert, $hiveId) {
    try {
        require_once __DIR__ . '/../app/Services/NotificationService.php';
        $notificationService = new \App\Services\NotificationService();
        
        // Get all active users for notifications
        $stmt = $pdo->prepare('SELECT id, username, email, phone, role FROM users WHERE is_active = 1 AND (email IS NOT NULL OR phone IS NOT NULL)');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            return;
        }
        
        // Determine priority based on severity
        $priority = ($alert['severity'] === 'critical') ? 'high' : 'normal';
        
        // Enhanced message with hive information
        $enhancedMessage = "Hive ID: {$hiveId}\n" . 
                          "Alert: {$alert['message']}\n" . 
                          "Current Value: {$alert['value']}\n" . 
                          "Threshold: {$alert['threshold']}\n" . 
                          "Time: " . date('Y-m-d H:i:s');
        
        // Send notifications
        $results = $notificationService->sendBulkAlerts(
            $users,
            $alert['type'],
            $enhancedMessage,
            $priority
        );
        
        // Log notification results
        foreach ($results as $userId => $result) {
            if (isset($result['email']) && $result['email']) {
                error_log("Email alert sent to user $userId for {$alert['type']} alert in hive $hiveId");
            }
            if (isset($result['sms']) && $result['sms']) {
                error_log("SMS alert sent to user $userId for {$alert['type']} alert in hive $hiveId");
            }
        }
        
    } catch (Exception $e) {
        error_log("Failed to send alert notifications: " . $e->getMessage());
    }
    }

    // Test notification system
    if ($path === '/api/admin/test-notifications' && $requestMethod === 'POST') {
        requireAdmin();
        $data = getRequestData();
        
        try {
            require_once __DIR__ . '/../app/Services/NotificationService.php';
            $notificationService = new \App\Services\NotificationService();
            
            $results = $notificationService->testNotifications(
                $data['test_email'] ?? null,
                $data['test_phone'] ?? null
            );
            
            jsonResponse([
                'status' => 'success',
                'message' => 'Test notifications sent',
                'results' => $results
            ]);
        } catch (Exception $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to send test notifications: ' . $e->getMessage()], 500);
        }
    }

    // Get notification settings
    if ($path === '/api/notifications/settings' && $requestMethod === 'GET') {
        requireAuth();
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $stmt = $pdo->prepare('SELECT email, phone FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            jsonResponse([
                'status' => 'success',
                'settings' => [
                    'email' => $user['email'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'email_enabled' => !empty($user['email']),
                    'sms_enabled' => !empty($user['phone'])
                ]
            ]);
        } catch (Exception $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to get notification settings'], 500);
        }
    }

    // Update notification settings
    if ($path === '/api/notifications/settings' && $requestMethod === 'PUT') {
        requireAuth();
        $data = getRequestData();
        
        try {
            require_once __DIR__ . '/../app/Database.php';
            $pdo = \App\Database::getConnection();
            
            $email = trim($data['email'] ?? '');
            $phone = trim($data['phone'] ?? '');
            
            // Validate email format
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid email format'], 400);
            }
            
            // Validate phone format
            if (!empty($phone) && !preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone)) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid phone number format'], 400);
            }
            
            $stmt = $pdo->prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?');
            $stmt->execute([$email, $phone, $_SESSION['user_id']]);
            
            jsonResponse(['status' => 'success', 'message' => 'Notification settings updated successfully']);
        } catch (Exception $e) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to update notification settings'], 500);
        }
}

// 404 for unknown routes
http_response_code(404);
echo 'Page not found';