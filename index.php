<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title>Map Areas</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css"/>
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
        <div class="app">
            <aside class="sidebar">
                <header class="app-header">
                    <h1>Map Areas</h1>
                    <p>Desenhe e gerencie áreas no mapa</p>
                </header>
                <section class="panel">
                    <div class="panel-title">Nova área</div>
                    <div id="controls">
                        <input id="areaName" placeholder="Nome da área"/>
                        <textarea id="areaDesc" rows="2" placeholder="Descrição (opcional)"></textarea>
                        <div class="button-group">
                            <button id="saveBtn" disabled>Salvar</button>
                            <button id="clearBtn">Limpar</button>
                        </div>
                    </div>
                </section>
                <section class="panel areas-panel">
                    <div class="panel-title">Áreas salvas</div>
                    <div id="toggles"></div>
                </section>
            </aside>
            <main class="main">
                <div id="map"></div>
                <div id="coordinates-display">
                    <div class="coordinates-header">
                        <h3>Coordenadas</h3>
                        <div id="coordinate-format-buttons">
                            <button id="latlng-btn" class="format-btn active">Lat/Lng</button>
                            <button id="utm-btn" class="format-btn">UTM</button>
                            <button id="gms-btn" class="format-btn">GMS</button>
                        </div>
                    </div>
                    <div id="coordinates-list"></div>
                </div>
            </main>
        </div>

        <div id="polygonModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2 id="modalName"></h2>
                <p id="modalDescription"></p>
                <p id="modalArea" class="modal-stat"></p>
                <p id="modalPerimeter" class="modal-stat"></p>
            </div>
        </div>

        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet-draw/dist/leaflet.draw.js"></script>
        <script src="coordinate-converter.js"></script>
        <script>
            let activePolygon = null;
            let activeAreaData = null; // Armazenar dados da área selecionada
            let map = L.map('map', {
                scrollWheelZoom: false
            }).setView([-23.55052, -46.633308], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);

            let drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);
            let drawControl = new L.Control.Draw({
                edit: { 
                    featureGroup: drawnItems,
                    remove: false
                },
                draw: { polygon: true, polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false }
            });
            map.addControl(drawControl);

            let currentPolygon = null;
            let currentFormat = 'latlng';
            let displayRequestId = 0;

            function getActivePolygon() {
                return currentPolygon || activePolygon;
            }

            async function displayCoordinates(polygon) {
                const coordinatesList = document.getElementById('coordinates-list');
                const requestId = ++displayRequestId;

                if (!polygon) {
                    coordinatesList.innerHTML = '<p class="empty-state">Nenhum polígono selecionado</p>';
                    return;
                }

                const coords = polygon.getLatLngs()[0];
                const isAsyncFormat = currentFormat === 'utm' || currentFormat === 'gms';

                if (isAsyncFormat) {
                    coordinatesList.innerHTML = '<p class="empty-state">Carregando coordenadas...</p>';
                }

                const items = [];
                for (let index = 0; index < coords.length; index++) {
                    const coord = coords[index];
                    let coordText = '';

                    switch (currentFormat) {
                        case 'latlng':
                            coordText = `Lat: ${coord.lat.toFixed(6)}, Lng: ${coord.lng.toFixed(6)}`;
                            break;
                        case 'utm':
                            try {
                                const utm = await toUTM(coord.lat, coord.lng);
                                coordText = `Zone ${utm.zone}${utm.hemisphere}: E ${utm.easting}, N ${utm.northing}`;
                            } catch (error) {
                                coordText = `Error: ${error.message}`;
                            }
                            break;
                        case 'gms':
                            try {
                                const gms = await toGMS(coord.lat, coord.lng);
                                coordText = `Lat: ${gms.lat}, Lng: ${gms.lng}`;
                            } catch (error) {
                                coordText = `Error: ${error.message}`;
                            }
                            break;
                    }

                    items.push({ index, coordText });
                }

                if (requestId !== displayRequestId) return;

                coordinatesList.innerHTML = '';
                for (const item of items) {
                    const coordDiv = document.createElement('div');
                    coordDiv.className = 'coordinate-item';
                    coordDiv.innerHTML = `
                        <span class="point-number">Point ${item.index + 1}:</span>
                        <span class="coordinates">${item.coordText}</span>
                    `;
                    coordinatesList.appendChild(coordDiv);
                }
            }

            map.on(L.Draw.Event.CREATED, async function (e) {
                if (currentPolygon) drawnItems.removeLayer(currentPolygon);
                currentPolygon = e.layer;
                drawnItems.addLayer(currentPolygon);
                document.getElementById('saveBtn').disabled = false;
                await displayCoordinates(currentPolygon);
            });

            document.getElementById('clearBtn').addEventListener('click', async () =>{
                if (currentPolygon) { drawnItems.removeLayer(currentPolygon); currentPolygon = null;}
                    document.getElementById('saveBtn').disabled = true;
                    await displayCoordinates(null);                
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
                    // Limpa o polígono antes de calcular o perímetro
                    drawnItems.removeLayer(currentPolygon); 
                    currentPolygon = null;
                    document.getElementById('saveBtn').disabled = true;
                    await displayCoordinates(null);
                    
                    // Após salvar a área, calcula e salva o perímetro
                    const perimeterResp = await fetch('save_perimeter.php', {
                        method: 'POST',
                        headers: {"Content-Type" : 'application/json'},
                        body: JSON.stringify({ id: data.id })
                    });
                    const perimeterData = await perimeterResp.json();
                    
                    // Atualiza a lista de áreas antes de mostrar o alert
                    await loadAreas();
                    
                    // Mostra o alert após atualizar a lista
                    if (perimeterData.success) {
                        alert('Saved area (id ' + data.id + ') - Area: ' + data.area_km2.toFixed(6) + ' km² - Perimeter: ' + perimeterData.perimeter_km.toFixed(6) + ' km');
                    } else {
                        alert('Saved area (id ' + data.id + ') - Area: ' + data.area_km2.toFixed(6) + ' km²\nWarning: Perimeter calculation failed');
                    }
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

                    radio .addEventListener('change', async () => {
                        if (activePolygon) {
                            map.removeLayer(activePolygon);
                            activePolygon = null;
                            activeAreaData = null;
                            await displayCoordinates(null);
                        }

                        if (radio.checked){
                            const coords = JSON.parse(area.coords);
                            activePolygon = L.polygon(coords).addTo(map);
                            activeAreaData = area; // Armazenar dados da área
                            
                            // Adicionar evento de click no polígono
                            activePolygon.on('click', function(e) {
                                showPolygonModal(area);
                            });
                            
                            map.fitBounds(activePolygon.getBounds());
                            await displayCoordinates(activePolygon);
                        }
                    });

                    togglesContainer.appendChild(label);

                })
            }
            
            async function switchCoordinateFormat(format) {
                currentFormat = format;
                updateFormatButtons(format);
                const polygon = getActivePolygon();
                if (polygon) await displayCoordinates(polygon);
            }

            document.getElementById('latlng-btn').addEventListener('click', () => switchCoordinateFormat('latlng'));
            document.getElementById('utm-btn').addEventListener('click', () => switchCoordinateFormat('utm'));
            document.getElementById('gms-btn').addEventListener('click', () => switchCoordinateFormat('gms'));

            function updateFormatButtons(activeFormat) {
                document.querySelectorAll('.format-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.getElementById(activeFormat + '-btn').classList.add('active');
            }

            // Função para mostrar o modal
            function showPolygonModal(area) {
                const modal = document.getElementById('polygonModal');
                const modalName = document.getElementById('modalName');
                const modalDescription = document.getElementById('modalDescription');
                const modalArea = document.getElementById('modalArea');
                
                modalName.textContent = area.name || `Área ${area.id}`;
                modalDescription.textContent = area.description || '';
                
                // Formatar área com 3 casas decimais
                if (area.area_poly !== null && area.area_poly !== undefined) {
                    const areaValue = parseFloat(area.area_poly);
                    modalArea.textContent = `Área: ${areaValue.toFixed(3)} km²`;
                } else {
                    modalArea.textContent = 'Área: Não disponível';
                }
                
                if (area.perimeter !== null && area.perimeter !== undefined) {
                    const perimeterValue = parseFloat(area.perimeter);
                    modalPerimeter.textContent = `Perímetro: ${perimeterValue.toFixed(3)} km`;
                } else {
                    modalPerimeter.textContent = 'Perímetro: Não disponível';
                }
                
                modal.classList.add('show');
            }

            // Fechar modal ao clicar no X
            document.querySelector('.modal-close').addEventListener('click', function() {
                document.getElementById('polygonModal').classList.remove('show');
            });

            // Fechar modal ao clicar fora dele
            document.getElementById('polygonModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });

            // Fechar modal com ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.getElementById('polygonModal').classList.remove('show');
                }
            });
            
            displayCoordinates(null);
            loadAreas();

            requestAnimationFrame(() => map.invalidateSize());
            window.addEventListener('resize', () => map.invalidateSize());
        </script>
    </body>
</html>

