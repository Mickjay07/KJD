document.addEventListener('DOMContentLoaded', () => {
    // Check config
    if (typeof CONFIG === 'undefined') {
        alert("Chybí soubor config.js!");
        return;
    }

    const headerTitle = document.getElementById('header-title');
    const listContainer = document.getElementById('departures-list');
    const statusLabel = document.getElementById('status');

    // Set Header
    headerTitle.textContent = `${CONFIG.STOP_NAME} -> ${CONFIG.DIRECTION_FILTER}`;

    function updateData() {
        // Construct API URL
        const url = new URL("https://api.golemio.cz/v2/pid/departureboards");
        url.searchParams.append("names", CONFIG.STOP_NAME);
        url.searchParams.append("limit", "50"); // Fetch more, filter later



        fetch(url, {
            headers: {
                "X-Access-Token": CONFIG.API_KEY
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                renderDepartures(data);
                updateStatus_Success();
            })
            .catch(error => {
                console.error("Fetch error:", error);
                renderError("Chyba načítání dat");
                updateStatus_Error(error.message);
            });
    }

    function renderDepartures(data) {
        listContainer.innerHTML = ''; // Clear existing

        if (!data.departures || data.departures.length === 0) {
            renderNoData();
            return;
        }

        const now = new Date();
        let count = 0;

        data.departures.forEach(dep => {
            // Filter Direction
            const headsign = dep.stop_headsign || '';
            if (!headsign.toLowerCase().includes(CONFIG.DIRECTION_FILTER.toLowerCase())) {
                return;
            }

            // Calculate Time
            const departureTime = new Date(dep.departure_timestamp.predicted);
            const diffMs = departureTime - now;
            const diffMins = Math.floor(diffMs / 60000);

            // Filter: Show only next 30 minutes
            if (diffMins < 0 || diffMins > 30) {
                return;
            }

            // Format Display
            let timeDisplay = `${diffMins} min`;
            let isUrgent = false;

            if (diffMins <= 0) {
                timeDisplay = "< 1 min";
                isUrgent = true;
            }

            createRow(dep.route.short_name, timeDisplay, isUrgent);
            count++;
        });

        if (count === 0) {
            renderNoData();
        }
    }

    function createRow(route, time, isUrgent) {
        const row = document.createElement('div');
        row.className = 'departure-row';

        const routeEl = document.createElement('div');
        routeEl.className = 'route-name';
        routeEl.textContent = route;

        const timeEl = document.createElement('div');
        timeEl.className = 'time-left ' + (isUrgent ? 'time-urgent' : '');
        timeEl.textContent = time;

        row.appendChild(routeEl);
        row.appendChild(timeEl);
        listContainer.appendChild(row);
    }

    function renderError(msg) {
        listContainer.innerHTML = `<div class="error">${msg}</div>`;
    }

    function renderNoData() {
        listContainer.innerHTML = `<div class="no-data">Žádné odjezdy (30 min)</div>`;
    }

    function updateStatus_Success() {
        const now = new Date();
        statusLabel.textContent = `Aktualizováno: ${now.toLocaleTimeString()}`;
        statusLabel.style.color = '#666';
    }

    function updateStatus_Error(msg) {
        const now = new Date();
        statusLabel.textContent = `Chyba (${now.toLocaleTimeString()}): ${msg}`;
        statusLabel.style.color = 'red';
    }

    // Initial Load
    updateData();

    // Auto Refresh every 30 seconds
    setInterval(updateData, 30000);
});
