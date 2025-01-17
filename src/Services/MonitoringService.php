<?php

namespace App\Services;

use DateTime;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use React\EventLoop\LoopInterface;

class MonitoringService
{
    private $redisService;
    private $loop;
    private $logger;

    public function __construct(RedisService $redisService, LoopInterface $loop)
    {
        $this->redisService = $redisService;
        $this->loop = $loop;
        
        // Pobierz konfigurację
        $settings = require __DIR__ . '/../../config/settings.php';
        $logConfig = $settings['settings']['logger'];
        
        // Initialize logger
        $this->logger = new Logger($logConfig['name']);
        $this->logger->pushHandler(
            new StreamHandler(
                $logConfig['path'],
                $logConfig['level']
            )
        );
    }

    public function startMonitoring(): void
    {
        $this->loop->addPeriodicTimer(2, function () {
            $this->checkAllCoasters();
        });
    }

    private function checkAllCoasters(): void
    {
        $redis = $this->redisService->getRedis();
        $keys = $redis->keys('coaster_*');

        foreach ($keys as $coasterId) {
            $coasterData = $redis->get($coasterId);
            if ($coasterData) {
                $coasterData = json_decode($coasterData, true);
                
                echo "\nSprawdzanie kolejki: $coasterId\n";
                echo "----------------------------------------\n";
                
                // Sprawdź podstawowe dane kolejki
                $this->checkBasicCoasterInfo($coasterId, $coasterData);
                
                // Sprawdź wagony
                $this->checkWagons($coasterId);
                
                // Sprawdź problemy
                $this->checkForProblems($coasterId, $coasterData);
                
                echo "----------------------------------------\n";
            }
        }
    }

    private function checkBasicCoasterInfo(string $coasterId, array $coasterData): void
    {
        // Oblicz wymagany personel
        $redis = $this->redisService->getRedis();
        $wagonsListKey = sprintf('coaster:%s:wagons', $coasterId);
        $wagonCount = $redis->scard($wagonsListKey);
        
        $requiredBaseStaff = 1;
        $requiredWagonStaff = $wagonCount * 2;
        $totalRequiredStaff = $requiredBaseStaff + $requiredWagonStaff;
        
        echo "1. Personel:\n";
        echo sprintf("   - Obecny: %d osób\n", $coasterData['liczba_personelu']);
        echo sprintf("   - Wymagany: %d osób (%d na kolejkę + %d na wagony)\n", 
            $totalRequiredStaff,
            $requiredBaseStaff,
            $requiredWagonStaff
        );
        
        if ($coasterData['liczba_personelu'] < $totalRequiredStaff) {
            $missing = $totalRequiredStaff - $coasterData['liczba_personelu'];
            echo sprintf("   - Brakuje: %d osób\n", $missing);
        } elseif ($coasterData['liczba_personelu'] > $totalRequiredStaff) {
            $extra = $coasterData['liczba_personelu'] - $totalRequiredStaff;
            echo sprintf("   - Nadmiar: %d osób\n", $extra);
        }

        // Dodaj informacje o przepustowości
        $capacityInfo = $this->calculateCapacity($coasterId, $coasterData);
        echo "2. Klienci:\n";
        echo sprintf("   - Planowani: %d osób dziennie\n", $coasterData['liczba_klientow']);
        echo sprintf("   - Możliwi do obsługi: %d osób dziennie\n", ceil($capacityInfo['dailyCapacity']));
        echo sprintf("   - Przepustowość na godzinę: %d osób\n", ceil($capacityInfo['hourlyCapacity']));
        
        if ($capacityInfo['dailyCapacity'] < $coasterData['liczba_klientow']) {
            $missing = $coasterData['liczba_klientow'] - $capacityInfo['dailyCapacity'];
            echo sprintf("   - Niedobór: %d miejsc dziennie\n", ceil($missing));
        } elseif ($capacityInfo['dailyCapacity'] > $coasterData['liczba_klientow'] * 2) {
            $excess = $capacityInfo['dailyCapacity'] - ($coasterData['liczba_klientow'] * 2);
            echo sprintf("   - Nadmiar: %d miejsc dziennie\n", ceil($excess));
        }

        echo "3. Długość trasy: {$coasterData['dl_trasy']}m\n";
        echo "4. Godziny pracy: {$coasterData['godziny_od']} - {$coasterData['godziny_do']}\n";
    }

    private function checkWagons(string $coasterId): void
    {
        $redis = $this->redisService->getRedis();
        $wagonScheduleService = new WagonScheduleService($this->redisService);
        $wagonsListKey = sprintf('coaster:%s:wagons', $coasterId);
        $wagonIds = $redis->smembers($wagonsListKey);
        
        echo "5. Wagony:\n";
        
        if (empty($wagonIds)) {
            echo "   - Brak przypisanych wagonów!\n";
            $this->logger->warning(sprintf(
                "[%s] Kolejka %s nie ma przypisanych wagonów",
                (new DateTime())->format('Y-m-d H:i:s'),
                $coasterId
            ));
        } else {
            foreach ($wagonIds as $wagonId) {
                $wagonKey = sprintf('coaster:%s:wagons:%s', $coasterId, $wagonId);
                $wagonData = $redis->get($wagonKey);
                
                if ($wagonData) {
                    $wagonData = json_decode($wagonData, true);
                    $status = $wagonScheduleService->getWagonStatus($coasterId, $wagonId);
                    
                    $statusText = match($status['status']) {
                        'in_ride' => sprintf("w trasie do %s", $status['end_time']),
                        'on_break' => sprintf("przerwa do %s", $status['available_at']),
                        'ready' => "gotowy do jazdy",
                        default => "status nieznany"
                    };

                    echo sprintf(
                        "   - Wagon %s: %d miejsc, prędkość %.1f m/s, Status: %s\n",
                        $wagonId,
                        $wagonData['ilosc_miejsc'],
                        $wagonData['predkosc_wagonu'],
                        $statusText
                    );
                }
            }
        }
    }

    private function checkForProblems(string $coasterId, array $coasterData): void
    {
        $problems = $this->getCoasterProblems($coasterId, $coasterData);
        
        echo "6. Status: ";
        if (empty($problems)) {
            echo "OK\n";
        } else {
            echo "Problem: " . implode(", ", $problems) . "\n";
            $this->logger->warning(sprintf(
                "[%s] Kolejka %s - Problem: %s",
                (new DateTime())->format('Y-m-d H:i:s'),
                $coasterId,
                implode(", ", $problems)
            ));
        }
    }

    public function getCoasterProblems(string $coasterId, array $coasterData): array
    {
        $problems = [];
        
        // Pobierz liczbę wagonów i oblicz personel
        $redis = $this->redisService->getRedis();
        $wagonsListKey = sprintf('coaster:%s:wagons', $coasterId);
        $wagonCount = $redis->scard($wagonsListKey);

        // Oblicz wymagany personel
        $requiredBaseStaff = 1;
        $requiredWagonStaff = $wagonCount * 2;
        $totalRequiredStaff = $requiredBaseStaff + $requiredWagonStaff;
        $currentStaff = $coasterData['liczba_personelu'];

        // Oblicz przepustowość
        $capacityInfo = $this->calculateCapacity($coasterId, $coasterData);
        $requiredDailyCapacity = $coasterData['liczba_klientow'];
        
        // Sprawdź czy moc przerobowa jest wystarczająca
        if ($capacityInfo['dailyCapacity'] < $requiredDailyCapacity) {
            // Oblicz ile brakuje wagonów
            $missingCapacity = $requiredDailyCapacity - $capacityInfo['dailyCapacity'];
            $averageWagonCapacity = $wagonCount > 0 ? $capacityInfo['dailyCapacity'] / $wagonCount : 0;
            $neededExtraWagons = ceil($missingCapacity / max(1, $averageWagonCapacity));
            
            // Oblicz dodatkowy wymagany personel dla brakujących wagonów
            $additionalStaffNeeded = $neededExtraWagons * 2;
            
            $problems[] = sprintf(
                "Niewystarczająca przepustowość (brakuje miejsc na %d osób dziennie). " .
                "Potrzeba dodatkowo %d wagonów i %d pracowników",
                ceil($missingCapacity),
                $neededExtraWagons,
                $additionalStaffNeeded
            );
        } elseif ($capacityInfo['dailyCapacity'] > $requiredDailyCapacity * 2) {
            // Oblicz nadmiar wagonów
            $excessCapacity = $capacityInfo['dailyCapacity'] - ($requiredDailyCapacity * 2);
            $averageWagonCapacity = $capacityInfo['dailyCapacity'] / max(1, $wagonCount);
            $excessWagons = floor($excessCapacity / $averageWagonCapacity);
            
            // Oblicz nadmiar personelu związany z nadmiarem wagonów
            $excessStaff = $excessWagons * 2;
            
            $problems[] = sprintf(
                "Nadmierna przepustowość (można obsłużyć o %d osób więcej dziennie). " .
                "Można zredukować o %d wagonów i %d pracowników",
                ceil($excessCapacity),
                $excessWagons,
                $excessStaff
            );
        }

        // Sprawdź aktualny stan personelu
        if ($currentStaff < $totalRequiredStaff) {
            $missingStaff = $totalRequiredStaff - $currentStaff;
            $problems[] = sprintf(
                "Brakuje %d pracowników (potrzeba: %d, jest: %d)",
                $missingStaff,
                $totalRequiredStaff,
                $currentStaff
            );
        } elseif ($currentStaff > $totalRequiredStaff) {
            $extraStaff = $currentStaff - $totalRequiredStaff;
            $problems[] = sprintf(
                "Nadmiar %d pracowników (potrzeba: %d, jest: %d)",
                $extraStaff,
                $totalRequiredStaff,
                $currentStaff
            );
        }

        // Sprawdź podstawowe wymagania dla wagonów
        if ($wagonCount === 0) {
            $problems[] = "Brak wagonów";
        } elseif ($wagonCount < 3) {
            $problems[] = "Za mało wagonów (minimum 3)";
        }
        
        return $problems;
    }

    public function calculateCapacity(string $coasterId, array $coasterData): array
    {
        $redis = $this->redisService->getRedis();
        $wagonsListKey = sprintf('coaster:%s:wagons', $coasterId);
        $wagonIds = $redis->smembers($wagonsListKey);
        
        $totalCapacityPerHour = 0;
        $totalSeats = 0;
        
        foreach ($wagonIds as $wagonId) {
            $wagonKey = sprintf('coaster:%s:wagons:%s', $coasterId, $wagonId);
            $wagonData = $redis->get($wagonKey);
            if ($wagonData) {
                $wagonData = json_decode($wagonData, true);
                $totalSeats += $wagonData['ilosc_miejsc'];
                
                // Oblicz przepustowość na godzinę dla tego wagonu
                $rideTimeInSeconds = ($coasterData['dl_trasy'] / $wagonData['predkosc_wagonu']) + 60;
                $ridesPerHour = 3600 / $rideTimeInSeconds;
                $capacityPerHour = $wagonData['ilosc_miejsc'] * $ridesPerHour;
                $totalCapacityPerHour += $capacityPerHour;
            }
        }

        // Oblicz godziny pracy
        $startTime = DateTime::createFromFormat('H:i', $coasterData['godziny_od']);
        $endTime = DateTime::createFromFormat('H:i', $coasterData['godziny_do']);
        $operatingHours = ($endTime->format('H') - $startTime->format('H')) +
                         ($endTime->format('i') - $startTime->format('i')) / 60;

        return [
            'hourlyCapacity' => $totalCapacityPerHour,
            'dailyCapacity' => $totalCapacityPerHour * $operatingHours,
            'totalSeats' => $totalSeats,
            'operatingHours' => $operatingHours
        ];
    }
} 