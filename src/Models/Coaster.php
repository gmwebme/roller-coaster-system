<?php

namespace App\Models;

use DateTime;
use InvalidArgumentException;

class Coaster
{
    private int $staffCount;
    private int $dailyCustomers;
    private int $trackLength;
    private DateTime $operatingHoursStart;
    private DateTime $operatingHoursEnd;

    public function __construct(
        int $staffCount,
        int $dailyCustomers,
        int $trackLength,
        string $operatingHoursStart,
        string $operatingHoursEnd
    ) {
        if ($staffCount <= 0) {
            throw new InvalidArgumentException('Liczba personelu musi być większa od 0');
        }
        if ($dailyCustomers <= 0) {
            throw new InvalidArgumentException('Liczba klientów musi być większa od 0');
        }
        if ($trackLength <= 0) {
            throw new InvalidArgumentException('Długość trasy musi być większa od 0');
        }

        // Walidacja formatu czasu za pomocą wyrażenia regularnego
        $timePattern = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';
        if (!preg_match($timePattern, $operatingHoursStart) || !preg_match($timePattern, $operatingHoursEnd)) {
            throw new InvalidArgumentException('Nieprawidłowy format czasu. Wymagany format HH:MM');
        }

        // Konwersja czasu
        $startTime = DateTime::createFromFormat('H:i', $operatingHoursStart);
        $endTime = DateTime::createFromFormat('H:i', $operatingHoursEnd);
        
        // Walidacja kolejności godzin
        if ($endTime <= $startTime) {
            throw new InvalidArgumentException('Czas zakończenia musi być późniejszy niż czas rozpoczęcia');
        }

        $this->staffCount = $staffCount;
        $this->dailyCustomers = $dailyCustomers;
        $this->trackLength = $trackLength;
        $this->operatingHoursStart = $startTime;
        $this->operatingHoursEnd = $endTime;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['liczba_personelu'],
            (int)$data['liczba_klientow'],
            (int)$data['dl_trasy'],
            $data['godziny_od'],
            $data['godziny_do']
        );
    }

    public function update(array $data): void
    {
        if (isset($data['liczba_personelu'])) {
            if ((int)$data['liczba_personelu'] <= 0) {
                throw new InvalidArgumentException('Liczba personelu musi być większa od 0');
            }
            $this->staffCount = (int)$data['liczba_personelu'];
        }

        if (isset($data['liczba_klientow'])) {
            if ((int)$data['liczba_klientow'] <= 0) {
                throw new InvalidArgumentException('Liczba klientów musi być większa od 0');
            }
            $this->dailyCustomers = (int)$data['liczba_klientow'];
        }

        if (isset($data['godziny_od'])) {
            $startTime = DateTime::createFromFormat('H:i', $data['godziny_od']);
            if (!$startTime) {
                throw new InvalidArgumentException('Nieprawidłowy format czasu rozpoczęcia. Wymagany format HH:MM');
            }
            $this->operatingHoursStart = $startTime;
        }

        if (isset($data['godziny_do'])) {
            $endTime = DateTime::createFromFormat('H:i', $data['godziny_do']);
            if (!$endTime) {
                throw new InvalidArgumentException('Nieprawidłowy format czasu zakończenia. Wymagany format HH:MM');
            }
            if (isset($this->operatingHoursStart) && $endTime <= $this->operatingHoursStart) {
                throw new InvalidArgumentException('Czas zakończenia musi być późniejszy niż czas rozpoczęcia');
            }
            $this->operatingHoursEnd = $endTime;
        }
    }

    public function toArray(): array
    {
        return [
            'liczba_personelu' => $this->staffCount,
            'liczba_klientow' => $this->dailyCustomers,
            'dl_trasy' => $this->trackLength,
            'godziny_od' => $this->operatingHoursStart->format('H:i'),
            'godziny_do' => $this->operatingHoursEnd->format('H:i')
        ];
    }
} 