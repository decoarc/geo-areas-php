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
