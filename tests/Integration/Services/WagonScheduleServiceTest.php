<?php

namespace Tests\Integration\Services;

use App\Services\RedisService;
use App\Services\WagonScheduleService;
use DateTime;
use PHPUnit\Framework\TestCase;

class WagonScheduleServiceTest extends TestCase
{
    private $redisService;
    private $wagonScheduleService;
    private $coasterId;
    private $wagonId;

    protected function setUp(): void
    {
        parent::setUp();
        // Poczekaj na dostępność Redis
        $maxRetries = 5;
        $retryDelay = 1;
        
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $this->redisService = new RedisService();
                if ($this->redisService->testConnection()) {
                    break;
                }
            } catch (\Exception $e) {
                if ($i === $maxRetries - 1) {
                    throw $e;
                }
                sleep($retryDelay);
            }
        }
        
        $this->wagonScheduleService = new WagonScheduleService($this->redisService);
        
        // Przygotuj dane testowe
        $this->coasterId = 'test_coaster_' . uniqid();
        $this->wagonId = 'test_wagon_' . uniqid();
        
        // Zapisz testową kolejkę
        $coasterData = [
            'liczba_personelu' => 15,
            'liczba_klientow' => 1000,
            'dl_trasy' => 500,
            'godziny_od' => '09:00',
            'godziny_do' => '18:00'
        ];
        
        $this->redisService->setValue($this->coasterId, json_encode($coasterData));
        
        // Zapisz testowy wagon
        $wagonData = [
            'ilosc_miejsc' => 32,
            'predkosc_wagonu' => 1.2
        ];
        
        $wagonKey = sprintf('coaster:%s:wagons:%s', $this->coasterId, $this->wagonId);
        $this->redisService->setValue($wagonKey, json_encode($wagonData));
    }

    protected function tearDown(): void
    {
        // Wyczyść dane testowe
        $redis = $this->redisService->getRedis();
        $redis->del($this->coasterId);
        $redis->del(sprintf('coaster:%s:wagons:%s', $this->coasterId, $this->wagonId));
        $redis->del(sprintf('coaster:%s:wagons:%s:last_ride', $this->coasterId, $this->wagonId));
        
        parent::tearDown();
    }

    public function testStartRide()
    {
        $currentTime = new DateTime('2024-01-01 10:00:00');
        $result = $this->wagonScheduleService->startRide($this->coasterId, $this->wagonId, $currentTime);
        
        $this->assertArrayHasKey('start_time', $result);
        $this->assertArrayHasKey('end_time', $result);
        $this->assertArrayHasKey('next_available', $result);
    }

    public function testCannotStartRideOutsideOperatingHours()
    {
        $currentTime = new DateTime('2024-01-01 20:00:00');
        $status = $this->wagonScheduleService->canStartRide($this->coasterId, $this->wagonId, $currentTime);
        
        $this->assertFalse($status['can_start']);
        $this->assertEquals('Kolejka nie działa w tych godzinach', $status['reason']);
    }

    public function testWagonNeedsBreakBetweenRides()
    {
        // Rozpocznij pierwszy przejazd
        $firstRideTime = new DateTime('2024-01-01 10:00:00');
        $this->wagonScheduleService->startRide($this->coasterId, $this->wagonId, $firstRideTime);
        
        // Próba rozpoczęcia drugiego przejazdu przed upływem przerwy
        $secondRideTime = new DateTime('2024-01-01 10:02:00');
        $status = $this->wagonScheduleService->canStartRide($this->coasterId, $this->wagonId, $secondRideTime);
        
        $this->assertFalse($status['can_start']);
        $this->assertStringContainsString('Wagon musi odpocząć', $status['reason']);
    }
} 