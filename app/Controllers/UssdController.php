<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UssdController
{
    public function handle(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $sessionId = $data['sessionId'] ?? '';
        $serviceCode = $data['serviceCode'] ?? '';
        $phoneNumber = $data['phoneNumber'] ?? '';
        $text = $data['text'] ?? '';

        // Initialize response
        $responseText = '';

        // Handle different USSD menu levels
        if (empty($text)) {
            // Main menu
            $responseText = "Welcome to Smart Hive Solutions\n";
            $responseText .= "1. View Hive Status\n";
            $responseText .= "2. Get Recommendations\n";
            $responseText .= "3. Training Resources\n";
            $responseText .= "4. Market Prices\n";
            $responseText .= "5. Contact Support";
        } else {
            $textArray = explode('*', $text);
            $level = count($textArray);

            switch ($level) {
                case 1:
                    switch ($textArray[0]) {
                        case '1':
                            $responseText = $this->getHiveStatus($phoneNumber);
                            break;
                        case '2':
                            $responseText = $this->getRecommendations($phoneNumber);
                            break;
                        case '3':
                            $responseText = $this->getTrainingResources();
                            break;
                        case '4':
                            $responseText = $this->getMarketPrices();
                            break;
                        case '5':
                            $responseText = "Contact Support:\n";
                            $responseText .= "Email: support@hivenova.com\n";
                            $responseText .= "Phone: +250787626864";
                            break;
                        default:
                            $responseText = "Invalid option. Please try again.";
                    }
                    break;
                default:
                    $responseText = "Invalid input. Please try again.";
            }
        }

        // Format response for USSD
        $responseData = [
            'sessionId' => $sessionId,
            'serviceCode' => $serviceCode,
            'phoneNumber' => $phoneNumber,
            'text' => $responseText
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getHiveStatus(string $phoneNumber): string
    {
        // TODO: Implement actual hive status retrieval from database
        $status = "Hive Status:\n";
        $status .= "Temperature: 25°C\n";
        $status .= "Humidity: 60%\n";
        $status .= "Weight: 25kg\n";
        $status .= "Status: Normal";
        return $status;
    }

    private function getRecommendations(string $phoneNumber): string
    {
        // TODO: Implement AI recommendations
        $recommendations = "Recommendations:\n";
        $recommendations .= "1. Check hive ventilation\n";
        $recommendations .= "2. Monitor honey levels\n";
        $recommendations .= "3. Schedule inspection";
        return $recommendations;
    }

    private function getTrainingResources(): string
    {
        $resources = "Training Resources:\n";
        $resources .= "1. Basic Beekeeping\n";
        $resources .= "2. Hive Management\n";
        $resources .= "3. Disease Prevention\n";
        $resources .= "4. Honey Production";
        return $resources;
    }

    private function getMarketPrices(): string
    {
        // TODO: Implement market price retrieval
        $prices = "Current Market Prices:\n";
        $prices .= "Raw Honey: 5,000 RWF/kg\n";
        $prices .= "Processed Honey: 7,000 RWF/kg\n";
        $prices .= "Beeswax: 3,000 RWF/kg";
        return $prices;
    }
} 