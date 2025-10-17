<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HiveController
{
    private $firestore;

    public function __construct()
    {
        $this->firestore = Database::getFirestore();
    }

    public function list(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['uid'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');

        try {
            if ($isAdmin) {
                // Admin can see all hives
                $hivesQuery = $this->firestore->collection('hives');
            } else {
                // Regular users can only see their own hives
                $hivesQuery = $this->firestore->collection('hives')
                    ->where('owner_id', '=', $userId);
            }

            $hivesSnapshot = $hivesQuery->documents();
            $hives = [];

            foreach ($hivesSnapshot as $doc) {
                $hiveData = $doc->data();
                $hiveData['id'] = $doc->id;
                
                // Convert Firestore timestamps to ISO strings
                if (isset($hiveData['created_at'])) {
                    $hiveData['created_at'] = $hiveData['created_at']->format('c');
                }
                if (isset($hiveData['approved_at'])) {
                    $hiveData['approved_at'] = $hiveData['approved_at']->format('c');
                }

                // For admins, enrich with owner contact info
                if ($isAdmin && isset($hiveData['owner_id'])) {
                    $ownerDoc = $this->firestore->collection('users')
                        ->document($hiveData['owner_id'])
                        ->snapshot();
                    
                    if ($ownerDoc->exists()) {
                        $ownerData = $ownerDoc->data();
                        $hiveData['owner_email'] = $ownerData['email'] ?? '';
                        $hiveData['owner_phone'] = $ownerData['phone'] ?? '';
                    }
                }

                $hives[] = $hiveData;
            }

            $response->getBody()->write(json_encode(['status' => 'success', 'hives' => $hives]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Failed to fetch hives: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?: [];
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Authentication required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $name = trim((string)($data['name'] ?? ''));
        $deviceId = trim((string)($data['device_id'] ?? ''));
        $location = trim((string)($data['location'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));

        if ($name === '' || $deviceId === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Name and device_id are required']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Check if device ID already exists
            $existingHiveQuery = $this->firestore->collection('hives')
                ->where('device_id', '=', $deviceId)
                ->limit(1);
            
            $existingHives = $existingHiveQuery->documents();
            
            if (!$existingHives->isEmpty()) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Device ID already registered']));
                return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
            }

            // Create new hive document
            $hiveData = [
                'name' => $name,
                'device_id' => $deviceId,
                'location' => $location,
                'description' => $description,
                'owner_id' => $user['uid'],
                'owner_name' => $user['username'],
                'status' => 'pending',
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime()
            ];

            $docRef = $this->firestore->collection('hives')->add($hiveData);
            $hiveData['id'] = $docRef->id();
            $hiveData['created_at'] = $hiveData['created_at']->format('c');
            $hiveData['updated_at'] = $hiveData['updated_at']->format('c');

            $response->getBody()->write(json_encode(['status' => 'success', 'hive' => $hiveData]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Failed to create hive: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['uid'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $hiveId = $args['id'];

        try {
            $hiveDoc = $this->firestore->collection('hives')->document($hiveId)->snapshot();
            
            if (!$hiveDoc->exists()) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Hive not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $hiveData = $hiveDoc->data();
            $hiveData['id'] = $hiveDoc->id();

            // Check permissions
            if (!$isAdmin && $hiveData['owner_id'] !== $userId) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Access denied']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Convert timestamps
            if (isset($hiveData['created_at'])) {
                $hiveData['created_at'] = $hiveData['created_at']->format('c');
            }
            if (isset($hiveData['approved_at'])) {
                $hiveData['approved_at'] = $hiveData['approved_at']->format('c');
            }

            // Get latest sensor data for this hive
            $sensorDataQuery = $this->firestore->collection('sensor_data')
                ->where('hive_id', '=', $hiveId)
                ->orderBy('recorded_at', 'DESC')
                ->limit(1);
            
            $sensorDataSnapshot = $sensorDataQuery->documents();
            
            if (!$sensorDataSnapshot->isEmpty()) {
                $latestData = $sensorDataSnapshot->documents()[0]->data();
                $hiveData['temperature'] = $latestData['temperature'] ?? 24;
                $hiveData['humidity'] = $latestData['humidity'] ?? 65;
                $hiveData['weight'] = $latestData['hive_weight'] ?? 45;
                $hiveData['gas_ppm'] = $latestData['gas_level'] ?? 180;
                $hiveData['last_updated'] = $latestData['recorded_at']->format('c');
            } else {
                // Default values if no sensor data
                $hiveData['temperature'] = 24;
                $hiveData['humidity'] = 65;
                $hiveData['weight'] = 45;
                $hiveData['gas_ppm'] = 180;
                $hiveData['last_updated'] = date('c');
            }

            // Get recent history (last 5 readings)
            $historyQuery = $this->firestore->collection('sensor_data')
                ->where('hive_id', '=', $hiveId)
                ->orderBy('recorded_at', 'DESC')
                ->limit(5);
            
            $historySnapshot = $historyQuery->documents();
            $history = [];
            
            foreach ($historySnapshot as $doc) {
                $data = $doc->data();
                $history[] = [
                    'time' => $data['recorded_at']->format('H:i'),
                    'temperature' => $data['temperature'] ?? 0,
                    'gas_ppm' => $data['gas_level'] ?? 0
                ];
            }
            
            $hiveData['history'] = $history;

            $response->getBody()->write(json_encode(['status' => 'success', 'hive' => $hiveData]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Failed to fetch hive: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['uid'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $hiveId = $args['id'];
        $data = $request->getParsedBody() ?: [];

        try {
            $hiveDoc = $this->firestore->collection('hives')->document($hiveId)->snapshot();
            
            if (!$hiveDoc->exists()) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Hive not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $hiveData = $hiveDoc->data();

            // Check permissions
            if (!$isAdmin && $hiveData['owner_id'] !== $userId) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Access denied']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Prepare update data
            $updateData = ['updated_at' => new \DateTime()];
            
            if (isset($data['name'])) { 
                $updateData['name'] = trim((string)$data['name']); 
            }
            if (isset($data['location'])) { 
                $updateData['location'] = trim((string)$data['location']); 
            }
            if (isset($data['description'])) { 
                $updateData['description'] = trim((string)$data['description']); 
            }
            if (isset($data['status']) && $isAdmin) { 
                $updateData['status'] = trim((string)$data['status']); 
            }

            // Update the document
            $this->firestore->collection('hives')->document($hiveId)->update($updateData);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Failed to update hive: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['uid'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        $hiveId = $args['id'];

        try {
            $hiveDoc = $this->firestore->collection('hives')->document($hiveId)->snapshot();
            
            if (!$hiveDoc->exists()) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Hive not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $hiveData = $hiveDoc->data();

            // Check permissions
            if (!$isAdmin && $hiveData['owner_id'] !== $userId) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Access denied']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Delete the hive document
            $this->firestore->collection('hives')->document($hiveId)->delete();

            // Create alert about deletion
            $alertData = [
                'type' => 'hive-deleted',
                'level' => 'warning',
                'message' => 'Hive deleted',
                'hive_id' => $hiveId,
                'hive_name' => $hiveData['name'] ?? '',
                'deleted_by' => $user['uid'] ?? '',
                'created_at' => new \DateTime()
            ];

            $this->firestore->collection('alerts')->add($alertData);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Failed to delete hive: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $user = $_SESSION['user'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');
        
        if (!$isAdmin) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Access denied']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $hiveId = $args['id'];

        try {
            $hiveDoc = $this->firestore->collection('hives')->document($hiveId)->snapshot();
            
            if (!$hiveDoc->exists()) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Hive not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Update hive status to active
            $updateData = [
                'status' => 'active',
                'approved_at' => new \DateTime(),
                'approved_by' => $user['uid'],
                'updated_at' => new \DateTime()
            ];

            $this->firestore->collection('hives')->document($hiveId)->update($updateData);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Failed to approve hive: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
} 