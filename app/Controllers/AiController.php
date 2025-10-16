<?php

namespace App\Controllers;

use Phpml\Regression\LeastSquares;
use Phpml\Preprocessing\Normalizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AiController
{
    private $model;
    private $normalizer;

    public function __construct()
    {
        $this->model = new LeastSquares();
        $this->normalizer = new Normalizer();
    }

    public function getRecommendations(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $hiveId = $data['hiveId'] ?? null;

        if (!$hiveId) {
            return $this->errorResponse($response, 'Hive ID is required');
        }

        // Get hive data
        $hiveData = $this->getHiveData($hiveId);
        
        // Generate recommendations
        $recommendations = $this->generateRecommendations($hiveData);

        $responseData = [
            'status' => 'success',
            'recommendations' => $recommendations
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getHiveData(string $hiveId): array
    {
        // TODO: Implement actual database query
        return [
            'temperature' => 25.5,
            'humidity' => 60,
            'weight' => 25.0,
            'sound_level' => 45,
            'last_inspection' => '2024-03-15'
        ];
    }

    private function generateRecommendations(array $hiveData): array
    {
        $recommendations = [];

        // Temperature-based recommendations
        if ($hiveData['temperature'] > 30) {
            $recommendations[] = [
                'type' => 'temperature',
                'priority' => 'high',
                'message' => 'High temperature detected. Consider improving hive ventilation.',
                'action' => 'Check hive ventilation and ensure proper airflow.'
            ];
        }

        // Humidity-based recommendations
        if ($hiveData['humidity'] > 70) {
            $recommendations[] = [
                'type' => 'humidity',
                'priority' => 'medium',
                'message' => 'High humidity detected. Monitor for mold growth.',
                'action' => 'Inspect hive for moisture and consider moisture control measures.'
            ];
        }

        // Weight-based recommendations
        if ($hiveData['weight'] < 20) {
            $recommendations[] = [
                'type' => 'weight',
                'priority' => 'high',
                'message' => 'Low hive weight detected. Check hive stores and bee activity.',
                'action' => 'Consider supplemental feeding if necessary.'
            ];
        }

        // Sound-based recommendations
        if ($hiveData['sound_level'] > 50) {
            $recommendations[] = [
                'type' => 'sound',
                'priority' => 'medium',
                'message' => 'Unusual sound levels detected. Monitor for swarming behavior.',
                'action' => 'Check for queen cells and prepare for potential swarming.'
            ];
        }

        // Time-based recommendations
        $lastInspection = new \DateTime($hiveData['last_inspection']);
        $now = new \DateTime();
        $daysSinceInspection = $now->diff($lastInspection)->days;

        if ($daysSinceInspection > 14) {
            $recommendations[] = [
                'type' => 'inspection',
                'priority' => 'medium',
                'message' => 'Regular inspection due.',
                'action' => 'Schedule a hive inspection within the next 7 days.'
            ];
        }

        return $recommendations;
    }

    private function errorResponse(Response $response, string $message): Response
    {
        $responseData = [
            'status' => 'error',
            'message' => $message
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
} 