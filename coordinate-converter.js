// Coordinate conversion utilities
// Functions to convert between different coordinate systems

function toUTM(lat, lng) {
  const a = 6378137.0;
  const f = 1 / 298.257223563;
  const e2 = 2 * f - f * f;
  const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));

  const zone = Math.floor((lng + 180) / 6) + 1;

  const latRad = (lat * Math.PI) / 180;
  const lngRad = (lng * Math.PI) / 180;
  const lng0Rad = (((zone - 1) * 6 - 180 + 3) * Math.PI) / 180;

  const N = a / Math.sqrt(1 - e2 * Math.sin(latRad) * Math.sin(latRad));
  const T = Math.tan(latRad) * Math.tan(latRad);
  const C = (e2 * Math.cos(latRad) * Math.cos(latRad)) / (1 - e2);
  const A = Math.cos(latRad) * (lngRad - lng0Rad);

  const M =
    a *
    ((1 - e2 / 4 - (3 * e2 * e2) / 64 - (5 * e2 * e2 * e2) / 256) * latRad -
      ((3 * e2) / 8 + (3 * e2 * e2) / 32 + (45 * e2 * e2 * e2) / 1024) *
        Math.sin(2 * latRad) +
      ((15 * e2 * e2) / 256 + (45 * e2 * e2 * e2) / 1024) *
        Math.sin(4 * latRad) -
      ((35 * e2 * e2 * e2) / 3072) * Math.sin(6 * latRad));

  const x =
    500000 +
    0.9996 *
      N *
      (A +
        ((1 - T + C) * A * A * A) / 6 +
        ((5 - 18 * T + T * T + 72 * C - 58 * e2) * A * A * A * A * A) / 120);
  const y =
    0.9996 *
    (M +
      N *
        Math.tan(latRad) *
        ((A * A) / 2 +
          ((5 - T + 9 * C + 4 * C * C) * A * A * A * A) / 24 +
          ((61 - 58 * T + T * T + 600 * C - 330 * e2) * A * A * A * A * A * A) /
            720));

  const northing = lat < 0 ? y + 10000000 : y;

  return {
    easting: Math.round(x),
    northing: Math.round(northing),
    zone: zone,
    hemisphere: lat >= 0 ? "N" : "S",
  };
}

function toGMS(lat, lng) {
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
