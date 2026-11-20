# Weather Forecast Collector

A dockerized Laravel backend service built to collect, normalize, store, and serve daily and hourly weather forecasts from multiple external providers (Open-Meteo and WeatherAPI) for agricultural planning and forecast comparison.

## 1. Installation and Setup

### Prerequisites
- Docker and Docker Compose
- A free API key from WeatherAPI (https://www.weatherapi.com/)

### Environment Variables
Create your `.env` file from `.env.example`:

`cp .env.example .env`

Update the required database and weather provider variables in `.env`:

- `DB_CONNECTION`: `pgsql`
- `DB_HOST`: `db`
- `DB_PORT`: `5432`
- `DB_DATABASE`: `weather_db`
- `DB_USERNAME`: `weather_user`
- `DB_PASSWORD`: `secret`
- `OPEN_METEO_BASE_URL`: `https://api.open-meteo.com/v1`
- `WEATHER_API_BASE_URL`: `https://api.weatherapi.com/v1`
- `WEATHER_API_KEY`: your WeatherAPI key

### Running Locally

1. Build and start the containers (`app`, `scheduler`, and `db`):
`docker-compose up -d --build`

2. Install PHP dependencies and generate the application key:
`docker-compose exec app composer install`
`docker-compose exec app php artisan key:generate`

3. Run database migrations and seed initial data (Thessaloniki, Open-Meteo, and WeatherAPI):
`docker-compose exec app php artisan migrate --seed`

4. Run an initial manual forecast fetch:
`docker-compose exec app php artisan forecasts:fetch`

5. Verify the daily automated schedule:
`docker-compose exec app php artisan schedule:list`
The `scheduler` container runs `php artisan schedule:work` continuously and executes `forecasts:fetch` once per day at `06:00 UTC`.

### Accessing Stored Data
- Web viewer: `http://localhost:8000` (filterable table by location, resolution, provider, target date, and snapshot run).
- JSON API endpoints (`http://localhost:8000/api`):
  - `GET /api/metadata` — lists configured locations and providers.
  - `GET /api/forecasts` — returns paginated forecasts with optional query parameters: `location_id`, `provider`, `resolution` (`hourly` | `daily`), `date` (`YYYY-MM-DD`), `latest_only` (`1` | `0`), and `per_page` (`1`–`200`, default `50`).

## 2. Implementation Choices

- PHP 8.3 and Laravel were chosen for clean service separation, built-in HTTP client with timeout and retry support, Eloquent ORM, Artisan CLI commands, and task scheduling.
- PostgreSQL 16 was chosen for strong relational integrity, exact `DECIMAL` storage for meteorological values, and indexing on `(location_id, provider_id, resolution, forecast_date_time)` and `fetched_at`.
- Normalized schema (`locations`, `providers`, `forecasts`) starts with a single location (`Thessaloniki`) and two providers (`Open-Meteo` and `WeatherAPI`), allowing new locations and providers to be added as database rows without schema changes.
- Historical snapshots (`fetched_at`) store a shared UTC timestamp per collection run so agronomists can compare how forecasts for a target date or hour evolve across days.
- Strategy pattern (`WeatherProviderInterface`) implemented by `OpenMeteoService` and `WeatherApiService` normalizes payloads into a common format (`°C`, `mm`, and UTC timestamps) and makes adding future providers straightforward.
- Provider differences and free tiers: Open-Meteo provides 7 days of forecast data on its free tier, while WeatherAPI's free tier provides 3 days; each provider's available forecast window is stored as returned. Daily mean `temperature` is populated when supplied by the provider (such as WeatherAPI `avgtemp_c`) and stored as `null` when unavailable (such as Open-Meteo daily).
- Fault isolation and batch inserts: each provider/location fetch is isolated in a `try/catch` block with HTTP timeouts and retries so a single provider error does not block others, and records are inserted in batches per run.

## 3. Incomplete Requirements

None. All core requirements—dockerized PHP application, relational database storage, daily scheduled collection from Open-Meteo and WeatherAPI, hourly and daily temperature and precipitation storage, extensible design, error handling, and data access—are implemented.

## 4. Next Steps

- Expand coverage from a single location (`Thessaloniki`) to multiple agricultural regions and support additional weather providers via the existing `WeatherProviderInterface` contract.
- Add additional meteorological and agronomic variables (such as relative humidity, wind speed, solar radiation, or soil moisture) alongside temperature and precipitation.
- Build queries or endpoints to highlight forecast disagreement across providers and day-over-day forecast drift for the same target date and hour (for example, spotting frost risk or rain window shifts).
- Add automated unit and feature tests (using `Http::fake()` and a test database) to verify payload normalization, partial provider failures, and API filtering and validation.
- Improve operational resilience with structured failure alerts or retry tracking when a provider times out or hits free-tier rate limits, and use a production web server stack (such as Nginx + PHP-FPM) instead of `php artisan serve`.

## 5. Additional Notes

- Security: API keys and secrets are excluded from version control via `.gitignore` and loaded through `config()`. API query parameters are validated with `$request->validate()` and rate-limited via `throttle:60,1`. Default database credentials in `docker-compose.yml` are provided for local setup only.
- Timezone standardization: all stored timestamps (`fetched_at` and `forecast_date_time`) are normalized to UTC to prevent local timezone and daylight saving discrepancies.