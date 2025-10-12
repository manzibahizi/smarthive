<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        // TODO: Fetch dashboard data from database
        $responseData = [
            'status' => 'success',
            'data' => [
                'total_hives' => 10,
                'active_alerts' => 2,
                'recent_activities' => []
            ]
        ];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getStats(Request $request, Response $response): Response
    {
        // TODO: Fetch detailed statistics from database
        $responseData = [
            'status' => 'success',
            'stats' => [
                'temperature_avg' => 25.5,
                'humidity_avg' => 60,
                'weight_avg' => 25.0
            ]
        ];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
} 