// Interface translations (pt-BR and en).
// Keys are looked up with t(); placeholders use {name} syntax.

const I18N_STRINGS = {
  pt: {
    "app.subtitle": "Desenhe e gerencie áreas no mapa",
    "panel.newArea": "Nova área",
    "panel.savedAreas": "Áreas salvas",
    "form.namePlaceholder": "Nome da área",
    "form.descPlaceholder": "Descrição (opcional)",
    "btn.save": "Salvar",
    "btn.clear": "Limpar",
    "btn.byCoords": "Adicionar por coordenadas",
    "coords.heading": "Coordenadas",
    "coords.empty": "Nenhum polígono selecionado",
    "coords.loading": "Carregando coordenadas...",
    "coords.point": "Ponto {n}:",
    "modal.defaultName": "Área {id}",
    "modal.area": "Área: {value} km²",
    "modal.areaNA": "Área: Não disponível",
    "modal.perimeter": "Perímetro: {value} km",
    "modal.perimeterNA": "Perímetro: Não disponível",
    "list.viewDescription": "Ver descrição",
    "list.viewDescriptionOf": "Ver descrição de {name}",
    "list.defaultName": "Área {id}",
    "alert.drawFirst": "Desenhe uma área primeiro.",
    "alert.saved": "Área salva (id {id}) - Área: {area} km² - Perímetro: {perimeter} km",
    "alert.savedNoPerimeter": "Área salva (id {id}) - Área: {area} km²\nAviso: falha ao calcular perímetro",
    "alert.saveError": "Erro ao salvar: {error}",
    "alert.unknown": "desconhecido",
    "coordsModal.title": "Adicionar área por coordenadas",
    "coordsModal.hint": "Informe os pontos do polígono (mínimo 3). Serão salvos em Lat/Lng.",
    "coordsModal.name": "Nome da área",
    "coordsModal.desc": "Descrição",
    "coordsModal.format": "Formato das coordenadas",
    "coordsModal.text": "Coordenadas (um ponto por linha)",
    "api.invalid_json": "Requisição inválida (JSON).",
    "api.missing_action": "Campo obrigatório ausente: action.",
    "api.missing_fields": "Campos obrigatórios ausentes: {fields}.",
    "api.method_not_allowed": "Método não permitido.",
    "api.invalid_action": "Ação inválida. Use utm, gms, from_utm ou from_gms.",
    "api.text_required": "Nome e descrição devem ser texto.",
    "api.too_few_points": "São necessários pelo menos 3 pontos distintos.",
    "api.too_many_points": "Polígono com pontos demais (máximo {max}).",
    "api.name_too_long": "Nome muito longo (máximo {max} caracteres).",
    "api.description_too_long": "Descrição muito longa (máximo {max} caracteres).",
    "api.invalid_coordinate": "Coordenada inválida.",
    "api.coordinate_out_of_bounds": "Coordenada fora dos limites.",
    "api.area_limit": "Limite de áreas atingido ({max}). Tente novamente mais tarde.",
    "api.save_failed": "Não foi possível salvar a área.",
    "api.area_not_found": "Área não encontrada.",
    "api.perimeter_failed": "Não foi possível calcular o perímetro.",
    "api.perimeter_input_required": "Forneça um ID de área ou coordenadas válidas.",
    "api.gms_format": "Formato GMS inválido: {value}",
    "api.conversion_failed": "Erro de conversão.",
    "api.invalid_response": "Resposta inesperada do servidor.",
    "api.rate_limited": "Muitas requisições. Tente novamente em alguns minutos.",
    "api.content_rejected": "O texto contém conteúdo não permitido.",
    "api.name_invalid_characters": "O nome contém caracteres não permitidos.",
    "coordsModal.example": "Exemplo:",
    "coordsModal.nameRequired": "Informe o nome da área.",
    "coordsModal.converting": "Convertendo...",
    "coordsModal.saving": "Salvando...",
    "coordsModal.save": "Salvar área",
    "coordsModal.cancel": "Cancelar",
    "err.http": "Erro HTTP: status {status}",
    "err.latlng": "Formato Lat/Lng inválido: {line}",
    "err.gms": "Formato GMS inválido: {line}",
    "err.utm": "Formato UTM inválido: {line}",
    "err.min3": "Informe pelo menos 3 pontos para formar um polígono.",
    "err.unknownFormat": "Formato desconhecido: {format}",
    "err.outOfBounds": "Coordenada fora dos limites: {line}",
    "example.latlng": "-23.550520, -46.633308\nLat: -23.550520, Lng: -46.633308",
    "example.utm": "Zone 22S: E 320834, N 7394123\n22S 320834 7394123",
    "example.gms": "Lat: 23°33'1.92\"S, Lng: 46°37'59.91\"W\n23°33'1.92\"S 46°37'59.91\"W",
  },
  en: {
    "app.subtitle": "Draw and manage areas on the map",
    "panel.newArea": "New area",
    "panel.savedAreas": "Saved areas",
    "form.namePlaceholder": "Area name",
    "form.descPlaceholder": "Description (optional)",
    "btn.save": "Save",
    "btn.clear": "Clear",
    "btn.byCoords": "Add by coordinates",
    "coords.heading": "Coordinates",
    "coords.empty": "No polygon selected",
    "coords.loading": "Loading coordinates...",
    "coords.point": "Point {n}:",
    "modal.defaultName": "Area {id}",
    "modal.area": "Area: {value} km²",
    "modal.areaNA": "Area: Not available",
    "modal.perimeter": "Perimeter: {value} km",
    "modal.perimeterNA": "Perimeter: Not available",
    "list.viewDescription": "View description",
    "list.viewDescriptionOf": "View description of {name}",
    "list.defaultName": "Area {id}",
    "alert.drawFirst": "Draw an area first.",
    "alert.saved": "Area saved (id {id}) - Area: {area} km² - Perimeter: {perimeter} km",
    "alert.savedNoPerimeter": "Area saved (id {id}) - Area: {area} km²\nWarning: failed to calculate perimeter",
    "alert.saveError": "Error saving: {error}",
    "alert.unknown": "unknown",
    "coordsModal.title": "Add area by coordinates",
    "coordsModal.hint": "Enter the polygon points (minimum 3). They will be saved as Lat/Lng.",
    "coordsModal.name": "Area name",
    "coordsModal.desc": "Description",
    "coordsModal.format": "Coordinate format",
    "coordsModal.text": "Coordinates (one point per line)",
    "api.invalid_json": "Invalid request (JSON).",
    "api.missing_action": "Required field missing: action.",
    "api.missing_fields": "Required fields missing: {fields}.",
    "api.method_not_allowed": "Method not allowed.",
    "api.invalid_action": "Invalid action. Use utm, gms, from_utm or from_gms.",
    "api.text_required": "Name and description must be text.",
    "api.too_few_points": "At least 3 distinct points are required.",
    "api.too_many_points": "Polygon has too many points (maximum {max}).",
    "api.name_too_long": "Name is too long (maximum {max} characters).",
    "api.description_too_long": "Description is too long (maximum {max} characters).",
    "api.invalid_coordinate": "Invalid coordinate.",
    "api.coordinate_out_of_bounds": "Coordinate out of bounds.",
    "api.area_limit": "Area limit reached ({max}). Try again later.",
    "api.save_failed": "Could not save the area.",
    "api.area_not_found": "Area not found.",
    "api.perimeter_failed": "Could not calculate the perimeter.",
    "api.perimeter_input_required": "Provide an area ID or valid coordinates.",
    "api.gms_format": "Invalid GMS format: {value}",
    "api.conversion_failed": "Conversion error.",
    "api.invalid_response": "Unexpected server response.",
    "api.rate_limited": "Too many requests. Try again in a few minutes.",
    "api.content_rejected": "The text contains content that is not allowed.",
    "api.name_invalid_characters": "The name contains characters that are not allowed.",
    "coordsModal.example": "Example:",
    "coordsModal.nameRequired": "Enter the area name.",
    "coordsModal.converting": "Converting...",
    "coordsModal.saving": "Saving...",
    "coordsModal.save": "Save area",
    "coordsModal.cancel": "Cancel",
    "err.http": "HTTP error: status {status}",
    "err.latlng": "Invalid Lat/Lng format: {line}",
    "err.gms": "Invalid GMS format: {line}",
    "err.utm": "Invalid UTM format: {line}",
    "err.min3": "Enter at least 3 points to form a polygon.",
    "err.unknownFormat": "Unknown format: {format}",
    "err.outOfBounds": "Coordinate out of bounds: {line}",
    "example.latlng": "-23.550520, -46.633308\nLat: -23.550520, Lng: -46.633308",
    "example.utm": "Zone 22S: E 320834, N 7394123\n22S 320834 7394123",
    "example.gms": "Lat: 23°33'1.92\"S, Lng: 46°37'59.91\"W\n23°33'1.92\"S 46°37'59.91\"W",
  },
};

const I18N_STORAGE_KEY = "geoAreasLang";
const I18N_DEFAULT_LANG = "en";

function detectLang() {
  try {
    const saved = localStorage.getItem(I18N_STORAGE_KEY);
    if (saved && I18N_STRINGS[saved]) return saved;
  } catch (e) {
    // localStorage indisponível (modo privado, bloqueado): usa o padrão
  }
  const browser = (navigator.language || "").toLowerCase();
  return browser.startsWith("pt") ? "pt" : I18N_DEFAULT_LANG;
}

let currentLang = detectLang();

function getLang() {
  return currentLang;
}

function t(key, vars = {}) {
  const table = I18N_STRINGS[currentLang] || I18N_STRINGS[I18N_DEFAULT_LANG];
  const template = table[key] ?? I18N_STRINGS[I18N_DEFAULT_LANG][key] ?? key;
  return template.replace(/\{(\w+)\}/g, (_, name) =>
    vars[name] !== undefined ? String(vars[name]) : `{${name}}`
  );
}

function applyStaticTranslations() {
  document.documentElement.lang = currentLang === "pt" ? "pt-BR" : "en";

  document.querySelectorAll("[data-i18n]").forEach((el) => {
    el.textContent = t(el.dataset.i18n);
  });
  document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
    el.placeholder = t(el.dataset.i18nPlaceholder);
  });
  document.querySelectorAll("[data-i18n-aria]").forEach((el) => {
    el.setAttribute("aria-label", t(el.dataset.i18nAria));
  });

  document.querySelectorAll("[data-lang]").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.lang === currentLang);
  });
}

/**
 * Lê o corpo JSON de uma resposta da API. Respostas que não são JSON
 * (ex.: erro fatal do PHP) viram o código "invalid_response".
 */
async function readApi(resp) {
  try {
    const data = await resp.json();
    return data && typeof data === "object" ? data : { error_code: "invalid_response" };
  } catch (e) {
    return { error_code: "invalid_response" };
  }
}

/**
 * Mensagem traduzida para um erro da API. Usa o error_code quando há tradução
 * e cai no texto original do servidor caso contrário.
 */
function apiErrorMessage(data) {
  if (data.error_code) {
    const key = "api." + data.error_code;
    const message = t(key, data.params || {});
    if (message !== key) return message;
  }
  return data.error || t("alert.unknown");
}

function setLang(lang) {
  if (!I18N_STRINGS[lang]) return;
  currentLang = lang;
  try {
    localStorage.setItem(I18N_STORAGE_KEY, lang);
  } catch (e) {
    // Sem persistência: a troca vale só para esta página
  }
  applyStaticTranslations();
  if (typeof onLanguageChange === "function") onLanguageChange();
}
