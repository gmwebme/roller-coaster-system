<?php

namespace Tests\Unit\Models;

use App\Models\Coaster;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoasterTest extends TestCase
{
    public function testCreateValidCoaster()
    {
        $coaster = new Coaster(
            15, // liczba_personelu
            1000, // liczba_klientow
            500, // dl_trasy
            '09:00', // godziny_od
            '18:00' // godziny_do
        );

        $data = $coaster->toArray();
        $this->assertEquals(15, $data['liczba_personelu']);
        $this->assertEquals(1000, $data['liczba_klientow']);
        $this->assertEquals(500, $data['dl_trasy']);
        $this->assertEquals('09:00', $data['godziny_od']);
        $this->assertEquals('18:00', $data['godziny_do']);
    }

    public function testInvalidTimeFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nieprawidłowy format czasu. Wymagany format HH:MM');
        new Coaster(15, 1000, 500, '24:00', '09:00');
    }

    public function testInvalidOperatingHours()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Czas zakończenia musi być późniejszy niż czas rozpoczęcia');
        new Coaster(15, 1000, 500, '18:00', '09:00');
    }

    public function testUpdateCoaster()
    {
        $coaster = new Coaster(15, 1000, 500, '09:00', '18:00');
        
        $coaster->update([
            'liczba_personelu' => 20,
            'liczba_klientow' => 1500,
            'godziny_od' => '10:00',
            'godziny_do' => '19:00'
        ]);

        $data = $coaster->toArray();
        $this->assertEquals(20, $data['liczba_personelu']);
        $this->assertEquals(1500, $data['liczba_klientow']);
        $this->assertEquals('10:00', $data['godziny_od']);
        $this->assertEquals('19:00', $data['godziny_do']);
    }
} 