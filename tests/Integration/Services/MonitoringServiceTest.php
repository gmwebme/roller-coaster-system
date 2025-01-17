<?php

namespace Tests\Integration\Services;

use App\Services\MonitoringService;
use App\Services\RedisService;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;

class MonitoringServiceTest extends TestCase
{
    private $redisService;
    private $monitoringService;
    private $coasterId;
    private $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redisService = new RedisService();
        $this->monitoringService = new MonitoringService($this->redisService, Loop::get());
        
        // Utwórz refleksję dla dostępu do prywatnych metod
        $this->reflection = new \ReflectionClass(MonitoringService::class);
        
        // Przygotuj dane testowe
        $this->coasterId = 'test_coaster_' . uniqid();
        
        $coasterData = [
            'liczba_personelu' => 15,
            'liczba_klientow' => 1000,
            'dl_trasy' => 500,
            'godziny_od' => '09:00',
            'godziny_do' => '18:00'
        ];
        
        $this->redisService->setValue($this->coasterId, json_encode($coasterData));
    }

    protected function tearDown(): void
    {
        $redis = $this->redisService->getRedis();
        $redis->del($this->coasterId);
        parent::tearDown();
    }

    public function testCalculateCapacity()
    {
        $coasterData = json_decode($this->redisService->getValue($this->coasterId), true);
        
        // Dodaj testowe wagony
        $wagonData = ['ilosc_miejsc' => 32, 'predkosc_wagonu' => 1.2];
        for ($i = 0; $i < 3; $i++) {
            $wagonId = 'test_wagon_' . $i;
            $wagonKey = sprintf('coaster:%s:wagons:%s', $this->coasterId, $wagonId);
            $this->redisService->setValue($wagonKey, json_encode($wagonData));
            
            $wagonsListKey = sprintf('coaster:%s:wagons', $this->coasterId);
            $this->redisService->getRedis()->sadd($wagonsListKey, $wagonId);
        }

        $capacityInfo = $this->monitoringService->calculateCapacity($this->coasterId, $coasterData);
        
        $this->assertArrayHasKey('hourlyCapacity', $capacityInfo);
        $this->assertArrayHasKey('dailyCapacity', $capacityInfo);
        $this->assertArrayHasKey('totalSeats', $capacityInfo);
        $this->assertArrayHasKey('operatingHours', $capacityInfo);
        
        $this->assertEquals(96, $capacityInfo['totalSeats']); // 3 wagony * 32 miejsca
        $this->assertEquals(9, $capacityInfo['operatingHours']); // 09:00 - 18:00
    }

    public function testGetCoasterProblems()
    {
        $coasterData = json_decode($this->redisService->getValue($this->coasterId), true);
        
        $problems = $this->monitoringService->getCoasterProblems($this->coasterId, $coasterData);
        
        $this->assertContains('Brak wagonów', $problems);
    }

    public function testCalculateCapacityWithNoWagons()
    {
        $coasterData = json_decode($this->redisService->getValue($this->coasterId), true);
        $capacityInfo = $this->monitoringService->calculateCapacity($this->coasterId, $coasterData);
        
        $this->assertEquals(0, $capacityInfo['totalSeats']);
        $this->assertEquals(0, $capacityInfo['hourlyCapacity']);
        $this->assertEquals(0, $capacityInfo['dailyCapacity']);
        $this->assertEquals(9, $capacityInfo['operatingHours']); // 09:00 - 18:00
    }
} 