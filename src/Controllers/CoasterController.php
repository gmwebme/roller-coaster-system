<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Coaster;
use App\Services\RedisService;
use InvalidArgumentException;
use App\Models\Wagon;
use App\Services\WagonScheduleService;
use DateTime;

class CoasterController
{
    private $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Walidacja wymaganych pól
        $requiredFields = ['liczba_personelu', 'liczba_klientow', 'dl_trasy', 'godziny_od', 'godziny_do'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->jsonResponse($response, [
                    'error' => "Brak wymaganego pola: {$field}"
                ], 400);
            }
        }

        try {
            $coaster = new Coaster(
                (int)$data['liczba_personelu'],
                (int)$data['liczba_klientow'],
                (int)$data['dl_trasy'],
                $data['godziny_od'],
                $data['godziny_do']
            );

            // Generuj unikalny identyfikator
            $id = uniqid('coaster_');
            
            // Zapisz do Redis
            $success = $this->redisService->setValue(
                $id,
                json_encode($coaster->toArray())
            );

            if (!$success) {
                return $this->jsonResponse($response, [
                    'error' => 'Błąd podczas zapisywania danych'
                ], 500);
            }

            return $this->jsonResponse($response, [
                'message' => 'Kolejka górska została zarejestrowana',
                'id' => $id,
                'data' => $coaster->toArray()
            ], 201);

        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Błąd podczas przetwarzania danych: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $coasterId = $args['id'];
        $data = $request->getParsedBody();

        try {
            // Pobierz istniejące dane
            $existingData = $this->redisService->getValue($coasterId);
            if (!$existingData) {
                return $this->jsonResponse($response, [
                    'error' => 'Kolejka górska nie została znaleziona'
                ], 404);
            }

            $existingData = json_decode($existingData, true);
            $coaster = Coaster::fromArray($existingData);

            // Aktualizuj dane (bez długości trasy)
            unset($data['dl_trasy']); // Ignoruj próbę zmiany długości trasy
            $coaster->update($data);

            // Zapisz zaktualizowane dane
            $success = $this->redisService->setValue(
                $coasterId,
                json_encode($coaster->toArray())
            );

            if (!$success) {
                return $this->jsonResponse($response, [
                    'error' => 'Błąd podczas zapisywania danych'
                ], 500);
            }

            return $this->jsonResponse($response, [
                'message' => 'Kolejka górska została zaktualizowana',
                'id' => $coasterId,
                'data' => $coaster->toArray()
            ]);

        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Błąd podczas przetwarzania danych: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addWagon(Request $request, Response $response, array $args): Response
    {
        $coasterId = $args['coasterId'];
        $data = $request->getParsedBody();

        // Validate required fields
        $requiredFields = ['ilosc_miejsc', 'predkosc_wagonu'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->jsonResponse($response, [
                    'error' => "Brak wymaganego pola: {$field}"
                ], 400);
            }
        }

        try {
            // Check if coaster exists
            $coasterData = $this->redisService->getValue($coasterId);
            if (!$coasterData) {
                return $this->jsonResponse($response, [
                    'error' => 'Kolejka górska nie została znaleziona'
                ], 404);
            }

            // Create new wagon
            $wagon = Wagon::fromArray($data);
            
            // Generate unique wagon ID
            $wagonId = uniqid('wagon_');
            
            // Create wagon key in Redis
            $wagonKey = sprintf('coaster:%s:wagons:%s', $coasterId, $wagonId);
            
            // Save wagon data
            $success = $this->redisService->setValue(
                $wagonKey,
                json_encode($wagon->toArray())
            );

            if (!$success) {
                return $this->jsonResponse($response, [
                    'error' => 'Błąd podczas zapisywania danych wagonu'
                ], 500);
            }

            // Add wagon ID to coaster's wagon list
            $wagonsListKey = sprintf('coaster:%s:wagons', $coasterId);
            $this->redisService->getRedis()->sadd($wagonsListKey, $wagonId);

            return $this->jsonResponse($response, [
                'message' => 'Wagon został dodany do kolejki górskiej',
                'wagon_id' => $wagonId,
                'data' => $wagon->toArray()
            ], 201);

        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Błąd podczas przetwarzania danych: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteWagon(Request $request, Response $response, array $args): Response
    {
        $coasterId = $args['coasterId'];
        $wagonId = $args['wagonId'];

        try {
            // Sprawdź czy kolejka istnieje
            $coasterData = $this->redisService->getValue($coasterId);
            if (!$coasterData) {
                return $this->jsonResponse($response, [
                    'error' => 'Kolejka górska nie została znaleziona'
                ], 404);
            }

            // Sprawdź czy wagon należy do tej kolejki
            $wagonsListKey = sprintf('coaster:%s:wagons', $coasterId);
            $isWagonBelongsToCoaster = $this->redisService->getRedis()->sismember($wagonsListKey, $wagonId);
            
            if (!$isWagonBelongsToCoaster) {
                return $this->jsonResponse($response, [
                    'error' => 'Wagon nie został znaleziony w tej kolejce'
                ], 404);
            }

            // Usuń wagon z listy wagonów kolejki
            $this->redisService->getRedis()->srem($wagonsListKey, $wagonId);

            // Usuń dane wagonu
            $wagonKey = sprintf('coaster:%s:wagons:%s', $coasterId, $wagonId);
            $this->redisService->getRedis()->del($wagonKey);

            return $this->jsonResponse($response, [
                'message' => 'Wagon został usunięty z kolejki górskiej',
                'wagon_id' => $wagonId
            ], 200);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Błąd podczas usuwania wagonu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startWagonRide(Request $request, Response $response, array $args): Response
    {
        $coasterId = $args['coasterId'];
        $wagonId = $args['wagonId'];

        try {
            $wagonScheduleService = new WagonScheduleService($this->redisService);
            $result = $wagonScheduleService->startRide($coasterId, $wagonId, new DateTime());

            return $this->jsonResponse($response, [
                'message' => 'Wagon rozpoczął przejazd',
                'ride_details' => $result
            ]);

        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Błąd podczas rozpoczynania przejazdu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getWagonStatus(Request $request, Response $response, array $args): Response
    {
        $coasterId = $args['coasterId'];
        $wagonId = $args['wagonId'];

        try {
            $wagonScheduleService = new WagonScheduleService($this->redisService);
            $status = $wagonScheduleService->getWagonStatus($coasterId, $wagonId);

            return $this->jsonResponse($response, [
                'wagon_id' => $wagonId,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => 'Błąd podczas pobierania statusu wagonu: ' . $e->getMessage()
            ], 500);
        }
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
} 