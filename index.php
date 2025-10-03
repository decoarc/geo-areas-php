<!DOCTYPE html>
<html lang=en>
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title>Map Areas</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css"/>
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
        <div id="controls">
            <input id="areaName" placeholder="Name of the Area"/>
            <textarea id="areaDesc" rows="2" placeholder="... A huge area..."></textarea>
            <button id="saveBtn" disabled>Salvar área desenhada</button>
            <button id="clearBtn">Limpar desenho atual</button>
        </div>
        <div id="map"></div>
        <div id="coordinates-display">
            <h3>Polygon Coordinates</h3>
            <div id="coordinate-format-buttons">
                <button id="latlng-btn" class="format-btn active">Lat/Lng</button>
                <button id="utm-btn" class="format-btn">UTM</button>
                <button id="gms-btn" class="format-btn">GMS</button>
            </div>
            <div id="coordinates-list"></div>
        </div>
        <div id="toggles"></div>
    
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet-draw/dist/leaflet.draw.js"></script>
        <script src="coordinate-converter.js"></script>
        <script>
            let activePolygon = null;
            let map = L.map('map').setView([-23.55052, -46.633308], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);

            let drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);
            let drawControl = new L.Control.Draw({
                edit: { featureGroup: drawnItems },
                draw: { polygon: true, polyline: false, rectangle: false, circle: false, marker: false }
            });
            map.addControl(drawControl);

            let currentPolygon = null;
            let currentFormat = 'latlng'; 



            
            function displayCoordinates(polygon) {
                const coordinatesList = document.getElementById('coordinates-list');
                coordinatesList.innerHTML = '';
                
                if (!polygon) {
                    coordinatesList.innerHTML = '<p style="color: #666; font-style: italic;">No polygon selected</p>';
                    return;
                }
                
                const coords = polygon.getLatLngs()[0];
                coords.forEach((coord, index) => {
                    const coordDiv = document.createElement('div');
                    coordDiv.className = 'coordinate-item';
                    
                    let coordText = '';
                    switch(currentFormat) {
                        case 'latlng':
                            coordText = `Lat: ${coord.lat.toFixed(6)}, Lng: ${coord.lng.toFixed(6)}`;
                            break;
                        case 'utm':
                            const utm = toUTM(coord.lat, coord.lng);
                            coordText = `Zone ${utm.zone}${utm.hemisphere}: E ${utm.easting}, N ${utm.northing}`;
                            break;
                        case 'gms':
                            const gms = toGMS(coord.lat, coord.lng);
                            coordText = `S: ${gms.lat}, W: ${gms.lng}`;
                            break;
                    }
                    
                    coordDiv.innerHTML = `
                        <span class="point-number">Point ${index + 1}:</span>
                        <span class="coordinates">${coordText}</span>
                    `;
                    coordinatesList.appendChild(coordDiv);
                });
            }

            map.on(L.Draw.Event.CREATED, function (e) {
                if (currentPolygon) drawnItems.removeLayer(currentPolygon);
                currentPolygon = e.layer;
                drawnItems.addLayer(currentPolygon);
                document.getElementById('saveBtn').disabled = false;
                displayCoordinates(currentPolygon);
            });

            document.getElementById('clearBtn').addEventListener('click', () =>{
                if (currentPolygon) { drawnItems.removeLayer(currentPolygon); currentPolygon = null;}
                    document.getElementById('saveBtn').disabled = true;
                    displayCoordinates(null);                
            });

            document.getElementById('saveBtn').addEventListener('click', async () => {
                if (!currentPolygon) return alert ('Draw an area first!!');
                const name = document.getElementById('areaName').value.trim();
                const description = document.getElementById('areaDesc').value.trim();
                const coords = currentPolygon.getLatLngs()[0].map(p => ({lat: p.lat, lng: p.lng}));

                const resp = await fetch('save_area.php', {
                    method: 'POST',
                    headers: {"Content-Type" : 'application/json'},
                    body: JSON.stringify({ name, description, coords})
                });
                const data = await resp.json();
                if (data.success) {
                    alert('Saved area (id ' + data.id + ')');
                    drawnItems.removeLayer(currentPolygon); currentPolygon = null;
                    document.getElementById('saveBtn').disabled = true;
                    displayCoordinates(null);
                    loadAreas();
                } else {
                    alert ('Error on Save: ' + (data.error || 'unknown'));
                }
            });

            async function loadAreas() {
                const resp = await fetch ('get_areas.php');
                const areas = await resp.json();

                const togglesContainer = document.getElementById('toggles');
                togglesContainer.innerHTML = '';

                areas.forEach(area => {
                    const label = document.createElement('label');

                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'area_radio';
                    radio.value = area.id;

                    label.appendChild(radio);
                    label.appendChild(document.createTextNode(area.name || `Area ${area.id}`));

                    radio .addEventListener('change', () => {
                        if (activePolygon) {
                            map.removeLayer(activePolygon);
                            activePolygon = null;
                            displayCoordinates(null);
                        }

                        if (radio.checked){
                            const coords = JSON.parse(area.coords);
                            activePolygon = L.polygon(coords).addTo(map);
                            map.fitBounds(activePolygon.getBounds());
                            displayCoordinates(activePolygon);
                        }
                    });

                    togglesContainer.appendChild(label);

                })
            }
            
            document.getElementById('latlng-btn').addEventListener('click', () => {
                currentFormat = 'latlng';
                updateFormatButtons('latlng');
                if (currentPolygon) displayCoordinates(currentPolygon);
                if (activePolygon) displayCoordinates(activePolygon);
            });

            document.getElementById('utm-btn').addEventListener('click', () => {
                currentFormat = 'utm';
                updateFormatButtons('utm');
                if (currentPolygon) displayCoordinates(currentPolygon);
                if (activePolygon) displayCoordinates(activePolygon);
            });

            document.getElementById('gms-btn').addEventListener('click', () => {
                currentFormat = 'gms';
                updateFormatButtons('gms');
                if (currentPolygon) displayCoordinates(currentPolygon);
                if (activePolygon) displayCoordinates(activePolygon);
            });

            function updateFormatButtons(activeFormat) {
                document.querySelectorAll('.format-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.getElementById(activeFormat + '-btn').classList.add('active');
            }

            
            displayCoordinates(null);
            loadAreas();
        </script>
    </body>
</html>

