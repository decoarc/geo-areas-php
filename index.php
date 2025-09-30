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
        <div id="toggles"></div>
    
        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet-draw/dist/leaflet.draw.js"></script>
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

            map.on(L.Draw.Event.CREATED, function (e) {
                if (currentPolygon) drawnItems.removeLayer(currentPolygon);
                currentPolygon = e.layer;
                drawnItems.addLayer(currentPolygon);
                document.getElementById('saveBtn').disabled = false;
            });

            document.getElementById('clearBtn').addEventListener('click', () =>{
                if (currentPolygon) { drawnItems.removeLayer(currentPolygon); currentPolygon = null;}
                    document.getElementById('saveBtn').disabled = true;                
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
                        }

                        if (radio.checked){
                            const coords = JSON.parse(area.coords);
                            activePolygon = L.polygon(coords).addTo(map);
                            map.fitBounds(activePolygon.getBounds());
                        }
                    });

                    togglesContainer.appendChild(label);

                })
            }
            loadAreas();
        </script>
    </body>
</html>

