<?php

namespace App\Services;

use DateTime;
use InvalidArgumentException;

class WagonScheduleService
{
    private const BREAK_TIME_MINUTES = 5;
    private $redisService;

    public function __construct(RedisService $redisService)
    {
        $this->redisService = $redisService;
    }

    public function canStartRide(string $coasterId, string $wagonId, DateTime $currentTime): array
    {
        $redis = $this->redisService->getRedis();
        
        // Pobierz dane kolejki
        $coasterData = json_decode($this->redisService->getValue($coasterId), true);
        if (!$coasterData) {
            throw new InvalidArgumentException('Kolejka nie istnieje');
        }

        // Sprawdź godziny operacyjne
        $operatingStart = DateTime::createFromFormat('H:i', $coasterData['godziny_od']);
        $operatingEnd = DateTime::createFromFormat('H:i', $coasterData['godziny_do']);
        
        $currentTimeStr = $currentTime->format('H:i');
        if ($currentTimeStr < $coasterData['godziny_od'] || $currentTimeStr > $coasterData['godziny_do']) {
            return [
                'can_start' => false,
                'reason' => 'Kolejka nie działa w tych godzinach'
            ];
        }

        // Pobierz dane wagonu
        $wagonKey = sprintf('coaster:%s:wagons:%s', $coasterId, $wagonId);
        $wagonData = json_decode($redis->get($wagonKey), true);
        if (!$wagonData) {
            throw new InvalidArgumentException('Wagon nie istnieje');
        }

        // Oblicz czas przejazdu
        $rideTimeInSeconds = ($coasterData['dl_trasy'] / $wagonData['predkosc_wagonu']);
        $rideEndTime = (clone $currentTime)->modify(sprintf('+%d seconds', $rideTimeInSeconds));
        
        // Sprawdź czy wagon zdąży wrócić przed końcem pracy
        $rideEndTimeStr = $rideEndTime->format('H:i');
        if ($rideEndTimeStr > $coasterData['godziny_do']) {
            return [
                'can_start' => false,
                'reason' => 'Wagon nie zdąży wrócić przed końcem czasu pracy'
            ];
        }

        // Sprawdź czy wagon nie jest w trakcie przejazdu lub przerwy
        $lastRideKey = sprintf('coaster:%s:wagons:%s:last_ride', $coasterId, $wagonId);
        $lastRideEnd = $redis->get($lastRideKey);
        
        if ($lastRideEnd) {
            $lastRideEnd = new DateTime($lastRideEnd);
            $breakEndTime = (clone $lastRideEnd)->modify(sprintf('+%d minutes', self::BREAK_TIME_MINUTES));
            
            if ($currentTime < $breakEndTime) {
                return [
                    'can_start' => false,
                    'reason' => sprintf(
                        'Wagon musi odpocząć do %s',
                        $breakEndTime->format('H:i:s')
                    )
                ];
            }
        }

        return [
            'can_start' => true,
            'estimated_end' => $rideEndTime->format('H:i:s')
        ];
    }

    public function startRide(string $coasterId, string $wagonId, DateTime $startTime): array
    {
        $status = $this->canStartRide($coasterId, $wagonId, $startTime);
        if (!$status['can_start']) {
            throw new InvalidArgumentException($status['reason']);
        }

        $redis = $this->redisService->getRedis();
        
        // Pobierz dane wagonu i kolejki
        $wagonKey = sprintf('coaster:%s:wagons:%s', $coasterId, $wagonId);
        $wagonData = json_decode($redis->get($wagonKey), true);
        $coasterData = json_decode($this->redisService->getValue($coasterId), true);

        // Oblicz czas przejazdu
        $rideTimeInSeconds = ($coasterData['dl_trasy'] / $wagonData['predkosc_wagonu']);
        $endTime = (clone $startTime)->modify(sprintf('+%d seconds', $rideTimeInSeconds));

        // Zapisz czas zakończenia przejazdu
        $lastRideKey = sprintf('coaster:%s:wagons:%s:last_ride', $coasterId, $wagonId);
        $redis->set($lastRideKey, $endTime->format('Y-m-d H:i:s'));

        return [
            'start_time' => $startTime->format('H:i:s'),
            'end_time' => $endTime->format('H:i:s'),
            'next_available' => $endTime->modify(sprintf('+%d minutes', self::BREAK_TIME_MINUTES))->format('H:i:s')
        ];
    }

    public function getWagonStatus(string $coasterId, string $wagonId): array
    {
        $redis = $this->redisService->getRedis();
        
        // Pobierz ostatni przejazd
        $lastRideKey = sprintf('coaster:%s:wagons:%s:last_ride', $coasterId, $wagonId);
        $lastRideEnd = $redis->get($lastRideKey);
        
        if (!$lastRideEnd) {
            return ['status' => 'ready'];
        }

        $lastRideEnd = new DateTime($lastRideEnd);
        $now = new DateTime();
        
        if ($now < $lastRideEnd) {
            return [
                'status' => 'in_ride',
                'end_time' => $lastRideEnd->format('H:i:s')
            ];
        }

        $breakEndTime = (clone $lastRideEnd)->modify(sprintf('+%d minutes', self::BREAK_TIME_MINUTES));
        
        if ($now < $breakEndTime) {
            return [
                'status' => 'on_break',
                'available_at' => $breakEndTime->format('H:i:s')
            ];
        }

        return ['status' => 'ready'];
    }
} 