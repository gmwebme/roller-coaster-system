<?php

namespace Tests\Unit\Models;

use App\Models\Wagon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WagonTest extends TestCase
{
    public function testCreateValidWagon()
    {
        $wagon = new Wagon(32, 1.2);
        $data = $wagon->toArray();
        
        $this->assertEquals(32, $data['ilosc_miejsc']);
        $this->assertEquals(1.2, $data['predkosc_wagonu']);
    }

    public function testInvalidSeatCount()
    {
        $this->expectException(InvalidArgumentException::class);
        new Wagon(0, 1.2);
    }

    public function testInvalidSpeed()
    {
        $this->expectException(InvalidArgumentException::class);
        new Wagon(32, 0);
    }

    public function testCreateFromArray()
    {
        $data = [
            'ilosc_miejsc' => 32,
            'predkosc_wagonu' => 1.2
        ];

        $wagon = Wagon::fromArray($data);
        $this->assertEquals($data, $wagon->toArray());
    }
} 