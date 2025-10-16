<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    private $firestore;

    public function __construct()
    {
        $this->firestore = Database::getFirestoreClient();
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['uid'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');

        try {
            // Get total hives count
            if ($isAdmin) {
                $hivesQuery = $this->firestore->collection('hives');
            } else {
                $hivesQuery = $this->firestore->collection('hives')
                    ->where('owner_id', '=', $userId);
            }
            $hivesSnapshot = $hivesQuery->documents();
            $totalHives = $hivesSnapshot->size();

            // Get active alerts count
            $alertsQuery = $this->firestore->collection('alerts')
                ->where('is_read', '=', false);
            $alertsSnapshot = $alertsQuery->documents();
            $activeAlerts = $alertsSnapshot->size();

            // Get recent activities (last 10 alerts)
            $recentAlertsQuery = $this->firestore->collection('alerts')
                ->orderBy('created_at', 'DESC')
                ->limit(10);
            $recentAlertsSnapshot = $recentAlertsQuery->documents();
            $recentActivities = [];

            foreach ($recentAlertsSnapshot as $doc) {
                $alertData = $doc->data();
                $alertData['id'] = $doc->id();
                if (isset($alertData['created_at'])) {
                    $alertData['created_at'] = $alertData['created_at']->format('c');
                }
                $recentActivities[] = $alertData;
            }

            $responseData = [
                'status' => 'success',
                'data' => [
                    'total_hives' => $totalHives,
                    'active_alerts' => $activeAlerts,
                    'recent_activities' => $recentActivities
                ]
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getStats(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['uid'] ?? null;
        $isAdmin = $user && (($user['role'] ?? '') === 'admin');

        try {
            // Get user's hives or all hives for admin
            if ($isAdmin) {
                $hivesQuery = $this->firestore->collection('hives');
            } else {
                $hivesQuery = $this->firestore->collection('hives')
                    ->where('owner_id', '=', $userId);
            }
            $hivesSnapshot = $hivesQuery->documents();
            
            $hiveIds = [];
            foreach ($hivesSnapshot as $doc) {
                $hiveIds[] = $doc->id();
            }

            if (empty($hiveIds)) {
                $responseData = [
                    'status' => 'success',
                    'stats' => [
                        'temperature_avg' => 0,
                        'humidity_avg' => 0,
                        'weight_avg' => 0,
                        'gas_level_avg' => 0
                    ]
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Get latest sensor data for each hive
            $totalTemp = 0;
            $totalHumidity = 0;
            $totalWeight = 0;
            $totalGas = 0;
            $dataCount = 0;

            foreach ($hiveIds as $hiveId) {
                $sensorDataQuery = $this->firestore->collection('sensor_data')
                    ->where('hive_id', '=', $hiveId)
                    ->orderBy('recorded_at', 'DESC')
                    ->limit(1);
                
                $sensorDataSnapshot = $sensorDataQuery->documents();
                
                if (!$sensorDataSnapshot->isEmpty()) {
                    $data = $sensorDataSnapshot->documents()[0]->data();
                    $totalTemp += $data['temperature'] ?? 0;
                    $totalHumidity += $data['humidity'] ?? 0;
                    $totalWeight += $data['hive_weight'] ?? 0;
                    $totalGas += $data['gas_level'] ?? 0;
                    $dataCount++;
                }
            }

            $avgTemp = $dataCount > 0 ? round($totalTemp / $dataCount, 1) : 0;
            $avgHumidity = $dataCount > 0 ? round($totalHumidity / $dataCount, 1) : 0;
            $avgWeight = $dataCount > 0 ? round($totalWeight / $dataCount, 1) : 0;
            $avgGas = $dataCount > 0 ? round($totalGas / $dataCount, 1) : 0;

            $responseData = [
                'status' => 'success',
                'stats' => [
                    'temperature_avg' => $avgTemp,
                    'humidity_avg' => $avgHumidity,
                    'weight_avg' => $avgWeight,
                    'gas_level_avg' => $avgGas
                ]
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
} 