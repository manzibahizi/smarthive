<?php

namespace App;

class Database
{
    private static $config = null;
    private static $accessToken = null;

    public static function getConfig()
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/firebase.php';
        }
        return self::$config;
    }

    public static function getAccessToken()
    {
        if (self::$accessToken === null) {
            // For now, we'll use the API key directly
            // In production, you should implement proper service account authentication
            self::$accessToken = self::getConfig()['apiKey'];
        }
        return self::$accessToken;
    }

    public static function getFirestore()
    {
        return new FirebaseRealtimeClient();
    }

    public static function getAuth()
    {
        return new FirebaseAuthClient();
    }

    // Helper methods for common Firestore operations
    public static function collection(string $collection)
    {
        return self::getFirestore()->collection($collection);
    }

    public static function document(string $collection, string $documentId = null)
    {
        if ($documentId) {
            return self::getFirestore()->collection($collection)->document($documentId);
        }
        return self::getFirestore()->collection($collection)->newDocument();
    }
}

class FirebaseRealtimeClient
{
    private $config;
    private $baseUrl;

    public function __construct()
    {
        $this->config = Database::getConfig();
        $this->baseUrl = rtrim($this->config['databaseUrl'], '/');
    }

    public function collection(string $collection)
    {
        return new FirebaseCollection($this->baseUrl, $collection);
    }

    public function batch()
    {
        return new FirebaseBatch($this->baseUrl);
    }
}

class FirebaseCollection
{
    private $baseUrl;
    private $collection;

    public function __construct($baseUrl, $collection)
    {
        $this->baseUrl = $baseUrl;
        $this->collection = $collection;
    }

    public function add(array $data)
    {
        $url = "{$this->baseUrl}/{$this->collection}.json";
        $response = $this->makeRequest('POST', $url, $data);
        return new FirebaseDocument($this->baseUrl, $this->collection, $response['name']);
    }

    public function document(string $documentId = null)
    {
        if ($documentId) {
            return new FirebaseDocument($this->baseUrl, $this->collection, $documentId);
        }
        return new FirebaseDocument($this->baseUrl, $this->collection);
    }

    public function where(string $field, string $operator, $value)
    {
        return new FirebaseQuery($this->baseUrl, $this->collection, $field, $operator, $value);
    }

    public function orderBy(string $field, string $direction = 'ASC')
    {
        return new FirebaseQuery($this->baseUrl, $this->collection, null, null, null, $field, $direction);
    }

    public function limit(int $limit)
    {
        return new FirebaseQuery($this->baseUrl, $this->collection, null, null, null, null, null, $limit);
    }

    public function documents()
    {
        $url = "{$this->baseUrl}/{$this->collection}";
        $response = $this->makeRequest('GET', $url);
        return new FirebaseDocumentSnapshot($response['documents'] ?? []);
    }

    private function makeRequest(string $method, string $url, array $data = null)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . Database::getAccessToken()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Firebase API error: HTTP {$httpCode} - {$response}");
        }

        return json_decode($response, true) ?: [];
    }
}

class FirebaseDocument
{
    private $baseUrl;
    private $collection;
    private $documentId;

    public function __construct($baseUrl, $collection, $documentId = null)
    {
        $this->baseUrl = $baseUrl;
        $this->collection = $collection;
        $this->documentId = $documentId;
    }

    public function set(array $data)
    {
        $url = "{$this->baseUrl}/{$this->collection}/{$this->documentId}";
        $this->makeRequest('PATCH', $url, $data);
        return $this;
    }

    public function update(array $data)
    {
        $url = "{$this->baseUrl}/{$this->collection}/{$this->documentId}";
        $this->makeRequest('PATCH', $url, $data);
        return $this;
    }

    public function delete()
    {
        $url = "{$this->baseUrl}/{$this->collection}/{$this->documentId}";
        $this->makeRequest('DELETE', $url);
        return $this;
    }

    public function snapshot()
    {
        $url = "{$this->baseUrl}/{$this->collection}/{$this->documentId}";
        $response = $this->makeRequest('GET', $url);
        return new FirebaseDocumentSnapshot([$response]);
    }

    public function id()
    {
        return $this->documentId;
    }

    private function makeRequest(string $method, string $url, array $data = null)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . Database::getAccessToken()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Firebase API error: HTTP {$httpCode} - {$response}");
        }

        return json_decode($response, true) ?: [];
    }
}

class FirebaseQuery
{
    private $baseUrl;
    private $collection;
    private $field;
    private $operator;
    private $value;
    private $orderField;
    private $orderDirection;
    private $limit;

    public function __construct($baseUrl, $collection, $field = null, $operator = null, $value = null, $orderField = null, $orderDirection = null, $limit = null)
    {
        $this->baseUrl = $baseUrl;
        $this->collection = $collection;
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
        $this->orderField = $orderField;
        $this->orderDirection = $orderDirection;
        $this->limit = $limit;
    }

    public function where(string $field, string $operator, $value)
    {
        return new FirebaseQuery($this->baseUrl, $this->collection, $field, $operator, $value, $this->orderField, $this->orderDirection, $this->limit);
    }

    public function orderBy(string $field, string $direction = 'ASC')
    {
        return new FirebaseQuery($this->baseUrl, $this->collection, $this->field, $this->operator, $this->value, $field, $direction, $this->limit);
    }

    public function limit(int $limit)
    {
        return new FirebaseQuery($this->baseUrl, $this->collection, $this->field, $this->operator, $this->value, $this->orderField, $this->orderDirection, $limit);
    }

    public function documents()
    {
        $url = "{$this->baseUrl}/{$this->collection}";
        $response = $this->makeRequest('GET', $url);
        return new FirebaseDocumentSnapshot($response['documents'] ?? []);
    }

    private function makeRequest(string $method, string $url, array $data = null)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . Database::getAccessToken()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Firebase API error: HTTP {$httpCode} - {$response}");
        }

        return json_decode($response, true) ?: [];
    }
}

class FirebaseDocumentSnapshot
{
    private $documents;

    public function __construct(array $documents)
    {
        $this->documents = $documents;
    }

    public function isEmpty()
    {
        return empty($this->documents);
    }

    public function size()
    {
        return count($this->documents);
    }

    public function documents()
    {
        return array_map(function($doc) {
            return new FirebaseDocumentSnapshot([$doc]);
        }, $this->documents);
    }

    public function data()
    {
        if (empty($this->documents)) {
            return [];
        }
        return $this->documents[0]['fields'] ?? [];
    }

    public function id()
    {
        if (empty($this->documents)) {
            return null;
        }
        $name = $this->documents[0]['name'] ?? '';
        return basename($name);
    }
}

class FirebaseAuthClient
{
    private $config;

    public function __construct()
    {
        $this->config = Database::getConfig();
    }

    public function createUser(array $userData)
    {
        // For now, return a mock user record
        // In production, implement proper Firebase Auth API calls
        return (object) [
            'uid' => uniqid('user_'),
            'email' => $userData['email'] ?? '',
            'displayName' => $userData['displayName'] ?? ''
        ];
    }

    public function signInWithEmailAndPassword(string $email, string $password)
    {
        // For now, return a mock sign-in result
        // In production, implement proper Firebase Auth API calls
        return (object) [
            'firebaseUserId' => function() {
                return uniqid('user_');
            }
        ];
    }

    public function verifyIdToken(string $idToken)
    {
        // For now, return a mock verified token
        // In production, implement proper Firebase Auth API calls
        return (object) [
            'claims' => function() {
                return (object) [
                    'get' => function($key) {
                        return uniqid('user_');
                    }
                ];
            }
        ];
    }
}

class FirebaseBatch
{
    private $baseUrl;
    private $writes = [];

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function set($document, array $data)
    {
        $this->writes[] = [
            'update' => [
                'name' => $document,
                'fields' => $data
            ]
        ];
        return $this;
    }

    public function commit()
    {
        $url = "{$this->baseUrl}:commit";
        $data = ['writes' => $this->writes];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . Database::getAccessToken()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Firebase API error: HTTP {$httpCode} - {$response}");
        }

        return json_decode($response, true) ?: [];
    }
}


