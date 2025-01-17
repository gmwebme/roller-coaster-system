<?php

namespace App\Models;

use InvalidArgumentException;

class Wagon
{
    private int $seatCount;
    private float $speed;

    public function __construct(int $seatCount, float $speed)
    {
        if ($seatCount <= 0) {
            throw new InvalidArgumentException('Liczba miejsc musi być większa od 0');
        }

        if ($speed <= 0) {
            throw new InvalidArgumentException('Prędkość wagonu musi być większa od 0');
        }

        $this->seatCount = $seatCount;
        $this->speed = $speed;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['ilosc_miejsc'],
            (float)$data['predkosc_wagonu']
        );
    }

    public function toArray(): array
    {
        return [
            'ilosc_miejsc' => $this->seatCount,
            'predkosc_wagonu' => $this->speed
        ];
    }
} 