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
                    <p data-i18n="app.subtitle">Desenhe e gerencie áreas no mapa</p>
                    <div class="lang-switch">
                        <button type="button" class="format-btn lang-btn" data-lang="pt">PT</button>
                        <button type="button" class="format-btn lang-btn" data-lang="en">EN</button>
                    </div>
                </header>
                <section class="panel">
                    <div class="panel-title" data-i18n="panel.newArea">Nova área</div>
                    <div id="controls">
                        <input id="areaName" data-i18n-placeholder="form.namePlaceholder" placeholder="Nome da área"/>
                        <textarea id="areaDesc" rows="2" data-i18n-placeholder="form.descPlaceholder" placeholder="Descrição (opcional)"></textarea>
                        <div class="button-group">
                            <button id="saveBtn" data-i18n="btn.save" disabled>Salvar</button>
                            <button id="clearBtn" data-i18n="btn.clear">Limpar</button>
                        </div>
                        <button type="button" id="coordsBtn" class="secondary-btn" data-i18n="btn.byCoords">Adicionar por coordenadas</button>
                    </div>
                </section>
                <section class="panel areas-panel">
                    <div class="panel-title" data-i18n="panel.savedAreas">Áreas salvas</div>
                    <div id="toggles"></div>
                </section>
            </aside>
            <main class="main">
                <div id="map"></div>
                <div id="coordinates-display">
                    <div class="coordinates-header">
                        <h3 data-i18n="coords.heading">Coordenadas</h3>
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

        <div id="coordsInputModal" class="modal">
            <div class="modal-content modal-form">
                <span class="modal-close coords-modal-close">&times;</span>
                <h2 data-i18n="coordsModal.title">Adicionar área por coordenadas</h2>
                <p class="modal-hint" data-i18n="coordsModal.hint">Informe os pontos do polígono (mínimo 3). Serão salvos em Lat/Lng.</p>
                <form id="coordsForm" class="coords-form">
                    <label for="coordsAreaName" data-i18n="coordsModal.name">Nome da área</label>
                    <input id="coordsAreaName" data-i18n-placeholder="form.namePlaceholder" placeholder="Nome da área" required />

                    <label for="coordsAreaDesc" data-i18n="coordsModal.desc">Descrição</label>
                    <textarea id="coordsAreaDesc" rows="2" data-i18n-placeholder="form.descPlaceholder" placeholder="Descrição (opcional)"></textarea>

                    <label data-i18n="coordsModal.format">Formato das coordenadas</label>
                    <div id="coords-format-buttons" class="coords-format-buttons">
                        <button type="button" id="coords-latlng-btn" class="format-btn active" data-format="latlng">Lat/Lng</button>
                        <button type="button" id="coords-utm-btn" class="format-btn" data-format="utm">UTM</button>
                        <button type="button" id="coords-gms-btn" class="format-btn" data-format="gms">GMS</button>
                    </div>

                    <label for="coordsText" data-i18n="coordsModal.text">Coordenadas (um ponto por linha)</label>
                    <textarea id="coordsText" rows="8" required></textarea>
                    <p id="coordsFormatHint" class="coords-format-hint"></p>
                    <p id="coordsError" class="coords-error" hidden></p>

                    <div class="modal-actions">
                        <button type="button" id="coordsCancelBtn" class="secondary-btn" data-i18n="coordsModal.cancel">Cancelar</button>
                        <button type="submit" id="coordsSaveBtn" data-i18n="coordsModal.save">Salvar área</button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet-draw/dist/leaflet.draw.js"></script>
        <script src="i18n.js"></script>
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
                    coordinatesList.innerHTML = `<p class="empty-state">${t('coords.empty')}</p>`;
                    return;
                }

                const coords = polygon.getLatLngs()[0];
                const isAsyncFormat = currentFormat === 'utm' || currentFormat === 'gms';

                if (isAsyncFormat) {
                    coordinatesList.innerHTML = `<p class="empty-state">${t('coords.loading')}</p>`;
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
                        <span class="point-number">${t('coords.point', { n: item.index + 1 })}</span>
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
                if (!currentPolygon) return alert(t('alert.drawFirst'));
                const name = document.getElementById('areaName').value.trim();
                const description = document.getElementById('areaDesc').value.trim();
                const coords = currentPolygon.getLatLngs()[0].map(p => ({lat: p.lat, lng: p.lng}));

                await saveArea(name, description, coords, async () => {
                    drawnItems.removeLayer(currentPolygon);
                    currentPolygon = null;
                    document.getElementById('saveBtn').disabled = true;
                    document.getElementById('areaName').value = '';
                    document.getElementById('areaDesc').value = '';
                    await displayCoordinates(null);
                });
            });

            async function saveArea(name, description, coords, onSuccess) {
                const resp = await fetch('save_area.php', {
                    method: 'POST',
                    headers: {"Content-Type" : 'application/json'},
                    body: JSON.stringify({ name, description, coords})
                });
                const data = await readApi(resp);
                if (data.success) {
                    if (onSuccess) await onSuccess();

                    const perimeterResp = await fetch('save_perimeter.php', {
                        method: 'POST',
                        headers: {"Content-Type" : 'application/json'},
                        body: JSON.stringify({ id: data.id })
                    });
                    const perimeterData = await readApi(perimeterResp);

                    await loadAreas();

                    if (perimeterData.success) {
                        alert(t('alert.saved', { id: data.id, area: data.area_km2.toFixed(6), perimeter: perimeterData.perimeter_km.toFixed(6) }));
                    } else {
                        alert(t('alert.savedNoPerimeter', { id: data.id, area: data.area_km2.toFixed(6) }));
                    }
                } else {
                    alert(t('alert.saveError', { error: apiErrorMessage(data) }));
                }
            }

            async function loadAreas() {
                const resp = await fetch ('get_areas.php');
                const areas = await readApi(resp);
                if (!resp.ok || !Array.isArray(areas)) {
                    alert(apiErrorMessage(Array.isArray(areas) ? {} : areas));
                    return;
                }

                const togglesContainer = document.getElementById('toggles');
                togglesContainer.innerHTML = '';

                areas.forEach(area => {
                    const item = document.createElement('div');
                    item.className = 'area-item';

                    const label = document.createElement('label');

                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'area_radio';
                    radio.value = area.id;
                    radio.checked = !!activeAreaData && activeAreaData.id === area.id;

                    label.appendChild(radio);
                    const areaName = area.name || t('list.defaultName', { id: area.id });
                    label.appendChild(document.createTextNode(areaName));

                    const descBtn = document.createElement('button');
                    descBtn.type = 'button';
                    descBtn.className = 'area-desc-btn';
                    descBtn.title = t('list.viewDescription');
                    descBtn.setAttribute('aria-label', t('list.viewDescriptionOf', { name: areaName }));
                    descBtn.textContent = 'ℹ';
                    descBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        showPolygonModal(area);
                    });

                    radio.addEventListener('change', async () => {
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

                    item.appendChild(label);
                    item.appendChild(descBtn);
                    togglesContainer.appendChild(item);

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
                document.querySelectorAll('#coordinate-format-buttons .format-btn').forEach(btn => {
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
                const modalPerimeter = document.getElementById('modalPerimeter');
                
                modalName.textContent = area.name || t('modal.defaultName', { id: area.id });
                modalDescription.textContent = area.description || '';

                // Formatar área com 3 casas decimais
                if (area.area_poly !== null && area.area_poly !== undefined) {
                    const areaValue = parseFloat(area.area_poly);
                    modalArea.textContent = t('modal.area', { value: areaValue.toFixed(3) });
                } else {
                    modalArea.textContent = t('modal.areaNA');
                }

                if (area.perimeter !== null && area.perimeter !== undefined) {
                    const perimeterValue = parseFloat(area.perimeter);
                    modalPerimeter.textContent = t('modal.perimeter', { value: perimeterValue.toFixed(3) });
                } else {
                    modalPerimeter.textContent = t('modal.perimeterNA');
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
                    closeCoordsModal();
                }
            });

            let coordsInputFormat = 'latlng';

            function updateCoordsFormatButtons(format) {
                document.querySelectorAll('#coords-format-buttons .format-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.format === format);
                });
                document.getElementById('coordsFormatHint').textContent =
                    t('coordsModal.example') + '\n' + getCoordFormatPlaceholder(format);
                document.getElementById('coordsText').placeholder = getCoordFormatPlaceholder(format);
            }

            function openCoordsModal() {
                document.getElementById('coordsAreaName').value = document.getElementById('areaName').value;
                document.getElementById('coordsAreaDesc').value = document.getElementById('areaDesc').value;
                document.getElementById('coordsText').value = '';
                document.getElementById('coordsError').hidden = true;
                document.getElementById('coordsError').textContent = '';
                coordsInputFormat = 'latlng';
                updateCoordsFormatButtons(coordsInputFormat);
                document.getElementById('coordsInputModal').classList.add('show');
                document.getElementById('coordsAreaName').focus();
            }

            function closeCoordsModal() {
                document.getElementById('coordsInputModal').classList.remove('show');
            }

            document.getElementById('coordsBtn').addEventListener('click', openCoordsModal);
            document.querySelector('.coords-modal-close').addEventListener('click', closeCoordsModal);
            document.getElementById('coordsCancelBtn').addEventListener('click', closeCoordsModal);
            document.getElementById('coordsInputModal').addEventListener('click', function(e) {
                if (e.target === this) closeCoordsModal();
            });

            document.querySelectorAll('#coords-format-buttons .format-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    coordsInputFormat = btn.dataset.format;
                    updateCoordsFormatButtons(coordsInputFormat);
                });
            });

            document.getElementById('coordsForm').addEventListener('submit', async (e) => {
                e.preventDefault();

                const name = document.getElementById('coordsAreaName').value.trim();
                const description = document.getElementById('coordsAreaDesc').value.trim();
                const coordsText = document.getElementById('coordsText').value;
                const errorEl = document.getElementById('coordsError');
                const saveBtn = document.getElementById('coordsSaveBtn');

                if (!name) {
                    errorEl.textContent = t('coordsModal.nameRequired');
                    errorEl.hidden = false;
                    return;
                }

                errorEl.hidden = true;
                saveBtn.disabled = true;
                saveBtn.textContent = t('coordsModal.converting');

                try {
                    const coords = await parseCoordinateLines(coordsText, coordsInputFormat);

                    saveBtn.textContent = t('coordsModal.saving');
                    await saveArea(name, description, coords, async () => {
                        if (currentPolygon) {
                            drawnItems.removeLayer(currentPolygon);
                            currentPolygon = null;
                            document.getElementById('saveBtn').disabled = true;
                        }
                        document.getElementById('areaName').value = '';
                        document.getElementById('areaDesc').value = '';
                        await displayCoordinates(null);
                        closeCoordsModal();
                    });
                } catch (error) {
                    errorEl.textContent = error.message;
                    errorEl.hidden = false;
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = t('coordsModal.save');
                }
            });
            
            function onLanguageChange() {
                updateCoordsFormatButtons(coordsInputFormat);
                displayCoordinates(getActivePolygon());
                loadAreas();
            }

            document.querySelectorAll('[data-lang]').forEach(btn => {
                btn.addEventListener('click', () => setLang(btn.dataset.lang));
            });
            applyStaticTranslations();

            displayCoordinates(null);
            loadAreas();

            requestAnimationFrame(() => map.invalidateSize());
            window.addEventListener('resize', () => map.invalidateSize());
        </script>
    </body>
</html>

