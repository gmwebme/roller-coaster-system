# SYSTEM ZARZĄDZANIA KOLEJKĄ GÓRSKĄ

## SPIS TREŚCI
1. Opis projektu  
2. Wymagania systemowe  
3. Instalacja  
4. Konfiguracja  
5. Uruchamianie  
6. API  
7. Monitoring  
8. Testy  
9. Rozwiązywanie problemów  

---

## 1. OPIS PROJEKTU
System służący do zarządzania kolejką górską w parku rozrywki. Umożliwia:  
- Zarządzanie parametrami kolejki  
- Zarządzanie wagonami  
- Monitorowanie stanu w czasie rzeczywistym  
- Harmonogramowanie przejazdów  
- Analizę wydajności i przepustowości  

---

## 2. WYMAGANIA SYSTEMOWE
- Docker i Docker Compose  
- Redis (zewnętrzny lub lokalny)  
- Git  

---

## 3. INSTALACJA
1. **Klonowanie repozytorium**:  
   ```bash
   git clone [URL_REPO]
   cd [KATALOG_PROJEKTU]
   ```
2. **Konfiguracja środowiska**:  
   ```bash
   cp .env.example .env
   ```
3. **Budowa i uruchomienie kontenerów**:  
   ```bash
   docker-compose up -d
   ```
4. **Instalacja zależności**:  
   ```bash
   docker exec rollercsystem-php-1 composer install
   ```

---

## 4. KONFIGURACJA
### a) Plik `.env`:
```env
APP_ENV=dev                    # dev lub prod
NGINX_PORT=8080               # port dla środowiska dev
REDIS_HOST=redisrc.orb.local  # adres Redis
REDIS_PORT=6379               # port Redis
REDIS_PASSWORD=               # hasło Redis (jeśli wymagane)
```

### b) Środowiska:
#### - **Deweloperskie (dev)**:
  - Pełne logowanie  
  - Port 8080  
  - Redis DB 1  
  - Prefiks `dev:`  

#### - **Produkcyjne (prod)**:
  - Logowanie WARNING i ERROR  
  - Port 80  
  - Redis DB 0  
  - Prefiks `prod:`  

---

## 5. URUCHAMIANIE
1. **Środowisko deweloperskie**:  
   ```bash
   composer start:dev
   ```
2. **Środowisko produkcyjne**:  
   ```bash
   composer start:prod
   ```
3. **Monitoring**:  
   ```bash
   docker exec rollercsystem-php-1 php bin/monitor.php
   ```

---

## 6. API
### a) Kolejki górskie:
1. **Rejestracja kolejki**:  
   `POST /api/coasters`  
   ```json
   {
       "liczba_personelu": 15,
       "liczba_klientow": 1000,
       "dl_trasy": 500,
       "godziny_od": "09:00",
       "godziny_do": "18:00"
   }
   ```

2. **Aktualizacja kolejki**:  
   `PUT /api/coasters/{id}`  
   ```json
   {
       "liczba_personelu": 20,
       "liczba_klientow": 1500,
       "godziny_od": "10:00",
       "godziny_do": "19:00"
   }
   ```

### b) Wagony:
1. **Dodawanie wagonu**:  
   `POST /api/coasters/{coasterId}/wagons`  
   ```json
   {
       "ilosc_miejsc": 32,
       "predkosc_wagonu": 1.2
   }
   ```

2. **Usuwanie wagonu**:  
   `DELETE /api/coasters/{coasterId}/wagons/{wagonId}`  

3. **Start przejazdu**:  
   `POST /api/coasters/{coasterId}/wagons/{wagonId}/start`  

4. **Status wagonu**:  
   `GET /api/coasters/{coasterId}/wagons/{wagonId}/status`  

---

## 7. MONITORING
System monitoruje:  
- Liczbę personelu (1 osoba na kolejkę + 2 osoby na wagon)  
- Przepustowość kolejki  
- Status wagonów  
- Godziny operacyjne  
- Minimalną liczbę wagonów (3)  

**Uruchomienie monitoringu**:  
```bash
docker exec rollercsystem-php-1 php bin/monitor.php
```

---

## 8. TESTY
1. **Uruchomienie wszystkich testów**:  

   Sprawdź konfigurację pliku phpunit.xml i zmieniaj w zależności od potrzeb.
   ```bash
   composer test:docker
   ```

2. **Dostępne zestawy testów**:  
   - Testy jednostkowe (Unit)  
   - Testy integracyjne (Integration)  

---

## 9. ROZWIĄZYWANIE PROBLEMÓW
### a) Problem z połączeniem do Redis:
- Sprawdź konfigurację `REDIS_HOST` w `.env`  
- Upewnij się, że Redis jest uruchomiony  
- Sprawdź logi:  
  ```bash
  docker logs rollercsystem-php-1
  ```

### b) Problemy z uruchomieniem testów:
- Upewnij się, że kontenery są uruchomione  
- Sprawdź połączenie z Redis  
- Sprawdź uprawnienia do zapisu logów  

### c) Problemy z monitoringiem:
- Sprawdź, czy katalog `logs/` jest zapisywalny  
- Sprawdź konfigurację logowania w `config/settings.php`  

---

## WYMAGANIA BIZNESOWE
1. **Kolejka górska**:  
   - Minimum 3 wagony  
   - 1 pracownik do obsługi podstawowej  
   - Określone godziny operacyjne  

2. **Wagony**:  
   - 2 pracowników na wagon  
   - 5 minut przerwy między przejazdami  
   - Powrót przed końcem pracy kolejki  

3. **Monitoring**:  
   - Stan personelu  
   - Przepustowość  
   - Dostępność wagonów  
   - Raportowanie problemów  

---

## STRUKTURA KATALOGÓW
```
.
├── bin/                # Skrypty konsolowe
├── config/            # Pliki konfiguracyjne
├── docker/            # Konfiguracja Docker
├── logs/             # Logi aplikacji
├── public/           # Punkt wejścia aplikacji
├── src/              # Kod źródłowy
│   ├── Controllers/  # Kontrolery
│   ├── Models/      # Modele
│   └── Services/    # Serwisy
└── tests/            # Testy
    ├── Unit/        # Testy jednostkowe
    └── Integration/ # Testy integracyjne
