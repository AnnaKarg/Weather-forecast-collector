<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather Forecast Collector</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f7f8fa; color: #222; margin: 0; padding: 24px; }
        .container { max-width: 1050px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid #ddd; padding-bottom: 12px; margin-bottom: 20px; }
        h1 { font-size: 20px; margin: 0; }
        .subtitle { font-size: 13px; color: #666; margin-top: 4px; }
        a.api-link { font-size: 13px; color: #0b66c3; text-decoration: none; }
        a.api-link:hover { text-decoration: underline; }
        .filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; background: #fff; padding: 14px; border: 1px solid #e1e4e8; border-radius: 6px; margin-bottom: 16px; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        label { font-size: 12px; font-weight: 600; color: #555; }
        select, input[type="date"] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; background: #fff; }
        button { padding: 7px 14px; background: #1f6feb; color: #fff; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; }
        button:hover { background: #1859bc; }
        button.secondary { background: #eee; color: #333; }
        button.secondary:hover { background: #e0e0e0; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e1e4e8; font-size: 13px; }
        th, td { padding: 9px 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f1f3f5; font-weight: 600; color: #444; }
        tr:hover { background: #fafbfc; }
        .muted { color: #777; font-size: 12px; }
        .empty { text-align: center; padding: 24px; color: #777; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <h1>Weather Forecast Collector</h1>
            <div class="subtitle">Multi-provider &amp; Multi-location Forecast Store · UTC</div>
        </div>
        <a class="api-link" href="/api/forecasts?resolution=daily&latest_only=1" target="_blank">JSON Endpoint (/api/forecasts)</a>
    </header>

    <div class="filters">
        <div class="field">
            <label for="location_id">Location</label>
            <select id="location_id"></select>
        </div>

        <div class="field">
            <label for="resolution">Resolution</label>
            <select id="resolution">
                <option value="daily" selected>Daily</option>
                <option value="hourly">Hourly</option>
            </select>
        </div>

        <div class="field">
            <label for="provider">Provider</label>
            <select id="provider">
                <option value="">All Providers</option>
                <option value="Open-Meteo">Open-Meteo</option>
                <option value="WeatherAPI">WeatherAPI</option>
            </select>
        </div>

        <div class="field">
            <label for="date">Target Date</label>
            <input type="date" id="date">
        </div>

        <div class="field">
            <label for="latest_only">Snapshot</label>
            <select id="latest_only">
                <option value="1" selected>Latest Run</option>
                <option value="0">All Runs</option>
            </select>
        </div>

        <button onclick="loadForecasts()">Filter</button>
        <button class="secondary" onclick="resetFilters()">Reset</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>Location</th>
                <th>Forecast Date / Time (UTC)</th>
                <th>Provider</th>
                <th>Temp (°C)</th>
                <th>Min / Max (°C)</th>
                <th>Precipitation (mm)</th>
                <th>Fetched At (UTC)</th>
            </tr>
        </thead>
        <tbody id="forecast-body">
        </tbody>
    </table>
</div>

<script>
    function formatGreekDateTime(isoString, includeTime = true) {
        if (!isoString) return '—';
        const [datePart, timePart] = isoString.split('T');
        const [year, month, day] = datePart.split('-');
        const formattedDate = `${day}/${month}/${year}`;
        if (!includeTime || !timePart) return formattedDate;
        return `${formattedDate} ${timePart.slice(0, 5)}`;
    }

    async function loadLocations() {
        const res = await fetch('/api/metadata');
        const data = await res.json();
        const select = document.getElementById('location_id');
        select.innerHTML = data.locations.map(loc =>
            `<option value="${loc.id}">${loc.name} (${Number(loc.latitude).toFixed(2)}, ${Number(loc.longitude).toFixed(2)})</option>`
        ).join('');
    }

    function resetFilters() {
        const select = document.getElementById('location_id');
        if (select.options.length > 0) {
            select.selectedIndex = 0;
        }
        document.getElementById('resolution').value = 'daily';
        document.getElementById('provider').value = '';
        document.getElementById('date').value = '';
        document.getElementById('latest_only').value = '1';
        document.getElementById('forecast-body').innerHTML = '';
    }

    async function loadForecasts() {
        const locationId = document.getElementById('location_id').value;
        const resolution = document.getElementById('resolution').value;
        const provider = document.getElementById('provider').value;
        const date = document.getElementById('date').value;
        const latestOnly = document.getElementById('latest_only').value;

        const params = new URLSearchParams({
            resolution,
            latest_only: latestOnly,
            per_page: 200
        });
        if (locationId) params.append('location_id', locationId);
        if (provider) params.append('provider', provider);
        if (date) params.append('date', date);

        const tbody = document.getElementById('forecast-body');
        tbody.innerHTML = '<tr><td colspan="7" class="empty">Loading...</td></tr>';

        try {
            const res = await fetch(`/api/forecasts?${params.toString()}`);
            const json = await res.json();
            const rows = json.data || [];

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty">No forecasts found for the selected filters.</td></tr>';
                return;
            }

            const isHourly = resolution === 'hourly';

            tbody.innerHTML = rows.map(row => {
                const targetDt = formatGreekDateTime(row.forecast_date_time, isHourly);
                const fetchedDt = formatGreekDateTime(row.fetched_at, true);
                const temp = row.temperature_c !== null ? `${row.temperature_c.toFixed(1)}°` : '—';
                const minMax = (row.temperature_min_c !== null || row.temperature_max_c !== null)
                    ? `${row.temperature_min_c?.toFixed(1) ?? '—'}° / ${row.temperature_max_c?.toFixed(1) ?? '—'}°`
                    : '—';
                const precip = row.precipitation_mm !== null ? `${row.precipitation_mm.toFixed(2)}` : '0.00';

                return `
                    <tr>
                        <td>${row.location}</td>
                        <td>${targetDt}</td>
                        <td>${row.provider}</td>
                        <td>${temp}</td>
                        <td>${minMax}</td>
                        <td>${precip}</td>
                        <td class="muted">${fetchedDt}</td>
                    </tr>
                `;
            }).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty">Failed to load forecasts.</td></tr>';
        }
    }

    (async () => {
        await loadLocations();
    })();
</script>
</body>
</html>