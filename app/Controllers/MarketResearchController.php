<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MarketResearchController
{
    public function getData(Request $request, Response $response): Response
    {
        // TODO: Fetch market research data from database
        $responseData = [
            'status' => 'success',
            'data' => [
                'raw_honey_price' => 5000,
                'processed_honey_price' => 7000,
                'beeswax_price' => 3000
            ]
        ];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
} 