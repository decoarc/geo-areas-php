// Coordinate conversion utilities using PHP backend with proj4php
// Functions to convert between different coordinate systems via API calls

// Cache for coordinate conversions to avoid repeated API calls
const coordinateCache = new Map();

async function toUTM(lat, lng) {
  // Create cache key
  const cacheKey = `utm_${lat}_${lng}`;

  // Check cache first
  if (coordinateCache.has(cacheKey)) {
    return coordinateCache.get(cacheKey);
  }

  try {
    const response = await fetch("coordinate_converter.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "utm",
        lat: lat,
        lng: lng,
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    if (result.error) {
      throw new Error(result.error);
    }

    // Cache the result
    coordinateCache.set(cacheKey, result);

    return result;
  } catch (error) {
    console.error("Error converting to UTM:", error);
    // Fallback to approximate values if API fails
    const zone = Math.floor((lng + 180) / 6) + 1;
    return {
      easting: 0,
      northing: 0,
      zone: zone,
      hemisphere: lat >= 0 ? "N" : "S",
    };
  }
}

async function toGMS(lat, lng) {
  // Create cache key
  const cacheKey = `gms_${lat}_${lng}`;

  // Check cache first
  if (coordinateCache.has(cacheKey)) {
    return coordinateCache.get(cacheKey);
  }

  try {
    const response = await fetch("coordinate_converter.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "gms",
        lat: lat,
        lng: lng,
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    if (result.error) {
      throw new Error(result.error);
    }

    // Cache the result
    coordinateCache.set(cacheKey, result);

    return result;
  } catch (error) {
    console.error("Error converting to GMS:", error);
    // Fallback to approximate values if API fails
    const latDeg = Math.abs(lat);
    const lngDeg = Math.abs(lng);

    const latD = Math.floor(latDeg);
    const latM = Math.floor((latDeg - latD) * 60);
    const latS = ((latDeg - latD) * 60 - latM) * 60;

    const lngD = Math.floor(lngDeg);
    const lngM = Math.floor((lngDeg - lngD) * 60);
    const lngS = ((lngDeg - lngD) * 60 - lngM) * 60;

    const latDir = lat >= 0 ? "N" : "S";
    const lngDir = lng >= 0 ? "E" : "W";

    return {
      lat: `${latD}°${latM}'${latS.toFixed(2)}"${latDir}`,
      lng: `${lngD}°${lngM}'${lngS.toFixed(2)}"${lngDir}`,
    };
  }
}

const COORD_FORMAT_EXAMPLES = {
  latlng: `-23.550520, -46.633308
Lat: -23.550520, Lng: -46.633308`,
  utm: `Zone 22S: E 320834, N 7394123
22S 320834 7394123`,
  gms: `Lat: 23°33'1.92"S, Lng: 46°37'59.91"W
23°33'1.92"S 46°37'59.91"W`,
};

function getCoordFormatPlaceholder(format) {
  return COORD_FORMAT_EXAMPLES[format] || "";
}

function parseLatLngLine(line) {
  const latLngMatch = line.match(
    /lat[:\s]+(-?\d+(?:\.\d+)?)[,\s]+lng[:\s]+(-?\d+(?:\.\d+)?)/i
  );
  if (latLngMatch) {
    return { lat: parseFloat(latLngMatch[1]), lng: parseFloat(latLngMatch[2]) };
  }

  const lngLatMatch = line.match(
    /lng[:\s]+(-?\d+(?:\.\d+)?)[,\s]+lat[:\s]+(-?\d+(?:\.\d+)?)/i
  );
  if (lngLatMatch) {
    return { lat: parseFloat(lngLatMatch[2]), lng: parseFloat(lngLatMatch[1]) };
  }

  const pairMatch = line.match(/(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)/);
  if (pairMatch) {
    return { lat: parseFloat(pairMatch[1]), lng: parseFloat(pairMatch[2]) };
  }

  throw new Error(`Formato Lat/Lng inválido: ${line}`);
}

function parseGMSComponentsFromLine(line) {
  const labeledMatch = line.match(
    /lat[:\s]+([^,]+)[,\s]+lng[:\s]+(.+)/i
  );
  if (labeledMatch) {
    return { gms_lat: labeledMatch[1].trim(), gms_lng: labeledMatch[2].trim() };
  }

  const parts = line.trim().split(/\s+/);
  if (parts.length >= 2) {
    return { gms_lat: parts[0], gms_lng: parts.slice(1).join(" ") };
  }

  throw new Error(`Formato GMS inválido: ${line}`);
}

function parseUTMComponentsFromLine(line) {
  const zoneMatch = line.match(
    /zone\s*(\d{1,2})\s*([NS])[:\s]+e\s*(-?\d+(?:\.\d+)?)[,\s]+n\s*(-?\d+(?:\.\d+)?)/i
  );
  if (zoneMatch) {
    return {
      zone: parseInt(zoneMatch[1], 10),
      hemisphere: zoneMatch[2].toUpperCase(),
      easting: parseFloat(zoneMatch[3]),
      northing: parseFloat(zoneMatch[4]),
    };
  }

  const compactMatch = line.match(/^(\d{1,2})([NS])\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)$/i);
  if (compactMatch) {
    return {
      zone: parseInt(compactMatch[1], 10),
      hemisphere: compactMatch[2].toUpperCase(),
      easting: parseFloat(compactMatch[3]),
      northing: parseFloat(compactMatch[4]),
    };
  }

  const enMatch = line.match(/e\s*(-?\d+(?:\.\d+)?)[,\s]+n\s*(-?\d+(?:\.\d+)?)/i);
  const zoneOnly = line.match(/(\d{1,2})([NS])/i);
  if (enMatch && zoneOnly) {
    return {
      zone: parseInt(zoneOnly[1], 10),
      hemisphere: zoneOnly[2].toUpperCase(),
      easting: parseFloat(enMatch[1]),
      northing: parseFloat(enMatch[2]),
    };
  }

  throw new Error(`Formato UTM inválido: ${line}`);
}

async function convertFromUTM(easting, northing, zone, hemisphere) {
  const response = await fetch("coordinate_converter.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "from_utm",
      easting,
      northing,
      zone,
      hemisphere,
    }),
  });

  const result = await response.json();
  if (!response.ok || result.error) {
    throw new Error(result.error || `HTTP error! status: ${response.status}`);
  }

  return { lat: result.lat, lng: result.lng };
}

async function convertFromGMS(gmsLat, gmsLng) {
  const response = await fetch("coordinate_converter.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "from_gms",
      gms_lat: gmsLat,
      gms_lng: gmsLng,
    }),
  });

  const result = await response.json();
  if (!response.ok || result.error) {
    throw new Error(result.error || `HTTP error! status: ${response.status}`);
  }

  return { lat: result.lat, lng: result.lng };
}

async function parseCoordinateLines(text, format) {
  const lines = text
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => line.length > 0);

  if (lines.length < 3) {
    throw new Error("Informe pelo menos 3 pontos para formar um polígono.");
  }

  const coords = [];

  for (const line of lines) {
    let point;

    switch (format) {
      case "latlng":
        point = parseLatLngLine(line);
        break;
      case "utm": {
        const utm = parseUTMComponentsFromLine(line);
        point = await convertFromUTM(
          utm.easting,
          utm.northing,
          utm.zone,
          utm.hemisphere
        );
        break;
      }
      case "gms": {
        const gms = parseGMSComponentsFromLine(line);
        point = await convertFromGMS(gms.gms_lat, gms.gms_lng);
        break;
      }
      default:
        throw new Error(`Formato desconhecido: ${format}`);
    }

    if (
      point.lat < -90 ||
      point.lat > 90 ||
      point.lng < -180 ||
      point.lng > 180
    ) {
      throw new Error(`Coordenada fora dos limites: ${line}`);
    }

    coords.push(point);
  }

  return coords;
}
