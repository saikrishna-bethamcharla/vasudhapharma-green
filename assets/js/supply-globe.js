/* Vasudha Pharma - Interactive 3D Global Supply & Regulatory Globe
   High-performance Canvas 3D World Globe with Real Continents & Regulatory HUD
*/
(function () {
  'use strict';

    // Complete Global Regulatory Footprint: 90+ Export Partner Nations + India HQ
  var COUNTRIES_90 = [
    // Global Operations HQ
    { name: 'India (Global Operations HQ)', shortName: '★ Vasudha India HQ', lat: 17.68, lng: 83.21, isHq: true, color: '#ffffff', region: 'asia-pacific' },

    // North America
    { name: "Canada", shortName: "Canada", lat: 56.13, lng: -106.35, color: '#1DB88A', region: 'north-america' },
    { name: "Mexico", shortName: "Mexico", lat: 23.63, lng: -102.55, color: '#1DB88A', region: 'north-america' },
    { name: "United States", shortName: "United States", lat: 37.09, lng: -95.71, color: '#1DB88A', region: 'north-america' },

    // Latin America & Caribbean
    { name: "Argentina", shortName: "Argentina", lat: -38.41, lng: -63.61, color: '#1DB88A', region: 'latin-america' },
    { name: "Bolivia", shortName: "Bolivia", lat: -16.29, lng: -63.58, color: '#1DB88A', region: 'latin-america' },
    { name: "Brazil", shortName: "Brazil", lat: -14.23, lng: -51.92, color: '#1DB88A', region: 'latin-america' },
    { name: "Chile", shortName: "Chile", lat: -35.67, lng: -71.54, color: '#1DB88A', region: 'latin-america' },
    { name: "Colombia", shortName: "Colombia", lat: 4.57, lng: -74.29, color: '#1DB88A', region: 'latin-america' },
    { name: "Costa Rica", shortName: "Costa Rica", lat: 9.74, lng: -83.75, color: '#1DB88A', region: 'latin-america' },
    { name: "Dominica", shortName: "Dominica", lat: 15.41, lng: -61.37, color: '#1DB88A', region: 'latin-america' },
    { name: "Dominican Republic", shortName: "Dominican Rep.", lat: 18.73, lng: -70.16, color: '#1DB88A', region: 'latin-america' },
    { name: "Ecuador", shortName: "Ecuador", lat: -1.83, lng: -78.18, color: '#1DB88A', region: 'latin-america' },
    { name: "El Salvador", shortName: "El Salvador", lat: 13.79, lng: -88.9, color: '#1DB88A', region: 'latin-america' },
    { name: "Guatemala", shortName: "Guatemala", lat: 15.78, lng: -90.23, color: '#1DB88A', region: 'latin-america' },
    { name: "Honduras", shortName: "Honduras", lat: 15.2, lng: -86.24, color: '#1DB88A', region: 'latin-america' },
    { name: "Nicaragua", shortName: "Nicaragua", lat: 12.87, lng: -85.21, color: '#1DB88A', region: 'latin-america' },
    { name: "Panama", shortName: "Panama", lat: 8.53, lng: -80.78, color: '#1DB88A', region: 'latin-america' },
    { name: "Paraguay", shortName: "Paraguay", lat: -23.44, lng: -58.44, color: '#1DB88A', region: 'latin-america' },
    { name: "Peru", shortName: "Peru", lat: -9.19, lng: -75.01, color: '#1DB88A', region: 'latin-america' },
    { name: "Puerto Rico", shortName: "Puerto Rico", lat: 18.22, lng: -66.59, color: '#1DB88A', region: 'latin-america' },
    { name: "Uruguay", shortName: "Uruguay", lat: -32.52, lng: -55.76, color: '#1DB88A', region: 'latin-america' },
    { name: "Venezuela", shortName: "Venezuela", lat: 6.42, lng: -66.58, color: '#1DB88A', region: 'latin-america' },

    // Europe
    { name: "Austria", shortName: "Austria", lat: 47.51, lng: 14.55, color: '#1DB88A', region: 'europe' },
    { name: "Belarus", shortName: "Belarus", lat: 53.71, lng: 27.95, color: '#1DB88A', region: 'europe' },
    { name: "Belgium", shortName: "Belgium", lat: 50.5, lng: 4.46, color: '#1DB88A', region: 'europe' },
    { name: "Bulgaria", shortName: "Bulgaria", lat: 42.73, lng: 25.48, color: '#1DB88A', region: 'europe' },
    { name: "Croatia", shortName: "Croatia", lat: 45.1, lng: 15.2, color: '#1DB88A', region: 'europe' },
    { name: "Cyprus", shortName: "Cyprus", lat: 35.12, lng: 33.42, color: '#1DB88A', region: 'europe' },
    { name: "Czech Republic", shortName: "Czechia", lat: 49.81, lng: 15.47, color: '#1DB88A', region: 'europe' },
    { name: "Finland", shortName: "Finland", lat: 61.92, lng: 25.74, color: '#1DB88A', region: 'europe' },
    { name: "France", shortName: "France", lat: 46.22, lng: 2.21, color: '#1DB88A', region: 'europe' },
    { name: "Germany", shortName: "Germany", lat: 51.16, lng: 10.45, color: '#1DB88A', region: 'europe' },
    { name: "Greece", shortName: "Greece", lat: 39.07, lng: 21.82, color: '#1DB88A', region: 'europe' },
    { name: "Hungary", shortName: "Hungary", lat: 47.16, lng: 19.5, color: '#1DB88A', region: 'europe' },
    { name: "Ireland", shortName: "Ireland", lat: 53.14, lng: -7.69, color: '#1DB88A', region: 'europe' },
    { name: "Italy", shortName: "Italy", lat: 41.87, lng: 12.56, color: '#1DB88A', region: 'europe' },
    { name: "Kosovo", shortName: "Kosovo", lat: 42.6, lng: 20.9, color: '#1DB88A', region: 'europe' },
    { name: "Latvia", shortName: "Latvia", lat: 56.87, lng: 24.6, color: '#1DB88A', region: 'europe' },
    { name: "Malta", shortName: "Malta", lat: 35.93, lng: 14.37, color: '#1DB88A', region: 'europe' },
    { name: "Moldova", shortName: "Moldova", lat: 47.41, lng: 28.37, color: '#1DB88A', region: 'europe' },
    { name: "Netherlands", shortName: "Netherlands", lat: 52.13, lng: 5.29, color: '#1DB88A', region: 'europe' },
    { name: "Poland", shortName: "Poland", lat: 51.91, lng: 19.14, color: '#1DB88A', region: 'europe' },
    { name: "Portugal", shortName: "Portugal", lat: 39.39, lng: -8.22, color: '#1DB88A', region: 'europe' },
    { name: "Romania", shortName: "Romania", lat: 45.94, lng: 24.96, color: '#1DB88A', region: 'europe' },
    { name: "Russian Federation", shortName: "Russia", lat: 61.52, lng: 105.32, color: '#1DB88A', region: 'europe' },
    { name: "Serbia / Montenegro", shortName: "Serbia / Montenegro", lat: 44.01, lng: 20.9, color: '#1DB88A', region: 'europe' },
    { name: "Slovakia", shortName: "Slovakia", lat: 48.66, lng: 19.69, color: '#1DB88A', region: 'europe' },
    { name: "Slovenia", shortName: "Slovenia", lat: 46.15, lng: 14.99, color: '#1DB88A', region: 'europe' },
    { name: "Spain", shortName: "Spain", lat: 40.46, lng: -3.74, color: '#1DB88A', region: 'europe' },
    { name: "Sweden", shortName: "Sweden", lat: 60.12, lng: 18.64, color: '#1DB88A', region: 'europe' },
    { name: "Switzerland", shortName: "Switzerland", lat: 46.81, lng: 8.22, color: '#1DB88A', region: 'europe' },
    { name: "Türkiye", shortName: "Türkiye", lat: 38.96, lng: 35.24, color: '#1DB88A', region: 'europe' },
    { name: "Ukraine", shortName: "Ukraine", lat: 48.37, lng: 31.16, color: '#1DB88A', region: 'europe' },
    { name: "United Kingdom", shortName: "United Kingdom", lat: 55.37, lng: -3.43, color: '#1DB88A', region: 'europe' },

    // Asia-Pacific
    { name: "Australia", shortName: "Australia", lat: -25.27, lng: 133.77, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Bangladesh", shortName: "Bangladesh", lat: 23.68, lng: 90.35, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Cambodia", shortName: "Cambodia", lat: 12.57, lng: 104.99, color: '#1DB88A', region: 'asia-pacific' },
    { name: "China", shortName: "China", lat: 35.86, lng: 104.19, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Hong Kong", shortName: "Hong Kong", lat: 22.31, lng: 114.16, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Indonesia", shortName: "Indonesia", lat: -0.78, lng: 113.92, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Japan", shortName: "Japan", lat: 36.2, lng: 138.25, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Kazakhstan", shortName: "Kazakhstan", lat: 48.01, lng: 66.92, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Malaysia", shortName: "Malaysia", lat: 4.21, lng: 101.97, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Nepal", shortName: "Nepal", lat: 28.39, lng: 84.12, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Pakistan", shortName: "Pakistan", lat: 30.38, lng: 69.35, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Philippines", shortName: "Philippines", lat: 12.87, lng: 121.77, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Singapore", shortName: "Singapore", lat: 1.35, lng: 103.81, color: '#1DB88A', region: 'asia-pacific' },
    { name: "South Korea", shortName: "South Korea", lat: 35.9, lng: 127.76, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Sri Lanka", shortName: "Sri Lanka", lat: 7.87, lng: 80.77, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Taiwan", shortName: "Taiwan", lat: 23.69, lng: 120.96, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Thailand", shortName: "Thailand", lat: 15.87, lng: 100.99, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Uzbekistan", shortName: "Uzbekistan", lat: 41.37, lng: 64.58, color: '#1DB88A', region: 'asia-pacific' },
    { name: "Vietnam", shortName: "Vietnam", lat: 14.05, lng: 108.27, color: '#1DB88A', region: 'asia-pacific' },

    // Middle East
    { name: "Iraq", shortName: "Iraq", lat: 33.22, lng: 43.68, color: '#1DB88A', region: 'middle-east' },
    { name: "Israel", shortName: "Israel", lat: 31.04, lng: 34.85, color: '#1DB88A', region: 'middle-east' },
    { name: "Jordan", shortName: "Jordan", lat: 30.58, lng: 36.23, color: '#1DB88A', region: 'middle-east' },
    { name: "Kuwait", shortName: "Kuwait", lat: 29.31, lng: 47.48, color: '#1DB88A', region: 'middle-east' },
    { name: "Lebanon", shortName: "Lebanon", lat: 33.85, lng: 35.86, color: '#1DB88A', region: 'middle-east' },
    { name: "Oman", shortName: "Oman", lat: 21.51, lng: 55.92, color: '#1DB88A', region: 'middle-east' },
    { name: "Palestine", shortName: "Palestine", lat: 31.95, lng: 35.23, color: '#1DB88A', region: 'middle-east' },
    { name: "Qatar", shortName: "Qatar", lat: 25.35, lng: 51.18, color: '#1DB88A', region: 'middle-east' },
    { name: "Saudi Arabia", shortName: "Saudi Arabia", lat: 23.88, lng: 45.07, color: '#1DB88A', region: 'middle-east' },
    { name: "United Arab Emirates", shortName: "UAE", lat: 23.42, lng: 53.84, color: '#1DB88A', region: 'middle-east' },
    { name: "Yemen", shortName: "Yemen", lat: 15.55, lng: 48.52, color: '#1DB88A', region: 'middle-east' },

    // Africa
    { name: "Algeria", shortName: "Algeria", lat: 28.03, lng: 1.65, color: '#1DB88A', region: 'africa' },
    { name: "Egypt", shortName: "Egypt", lat: 26.82, lng: 30.8, color: '#1DB88A', region: 'africa' },
    { name: "Ghana", shortName: "Ghana", lat: 7.94, lng: -1.02, color: '#1DB88A', region: 'africa' },
    { name: "Kenya", shortName: "Kenya", lat: -1.29, lng: 36.82, color: '#1DB88A', region: 'africa' },
    { name: "Morocco", shortName: "Morocco", lat: 31.79, lng: -7.09, color: '#1DB88A', region: 'africa' },
    { name: "Nigeria", shortName: "Nigeria", lat: 9.08, lng: 8.67, color: '#1DB88A', region: 'africa' },
    { name: "Senegal", shortName: "Senegal", lat: 14.49, lng: -14.45, color: '#1DB88A', region: 'africa' },
    { name: "South Africa", shortName: "South Africa", lat: -30.55, lng: 22.93, color: '#1DB88A', region: 'africa' },
    { name: "Tunisia", shortName: "Tunisia", lat: 33.88, lng: 9.53, color: '#1DB88A', region: 'africa' },
    { name: "Zimbabwe", shortName: "Zimbabwe", lat: -19.02, lng: 29.15, color: '#1DB88A', region: 'africa' }
  ];
  var REGIONS = COUNTRIES_90;

  // ---------------------------------------------------------------------------
  // Globe state
  // ---------------------------------------------------------------------------
  var canvas, ctx, container, tipEl;
  var rotX = 0.22;
  var rotY = 2.45;
  var targetRotX = 0.22;
  var targetRotY = 2.45;
  var tiltX = 0, tiltY = 0;            // small parallax toward the pointer
  var speedFactor = 1;                 // auto-rotation slows while the pointer is on the globe
  var isDragging = false;
  var dragDistance = 0;
  var lastMouseX = 0;
  var lastMouseY = 0;
  var autoRotate = true;
  var activeRegion = REGIONS[0];
  var selectedCountry = null;          // set by a click on the globe or a country pill
  var hoveredCountry = null;           // partner under the pointer (or pill under the mouse)
  var hoveredShape = null;             // any country shape under the pointer
  var pointer = { x: 0, y: 0, inside: false, touch: false };
  var view = { cx: 0, cy: 0, w: 0, h: 0 };
  var sphereRadius = 165;
  var animFrameId = null;
  var lastTipKey = '';
  var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var world = [];          // processed country shapes
  var smallStates = [];    // partners too small for an outline: drawn as glowing markers
  var graticule = [];      // faint latitude / longitude lines
  var HQ = COUNTRIES_90[0];

  // Names in our list that differ from the map data
  var NAME_ALIAS = {
    'united states': 'United States of America', 'czech republic': 'Czechia',
    'dominican republic': 'Dominican Rep.', 'uae': 'United Arab Emirates',
    'russian federation': 'Russia', 'türkiye': 'Turkey', 'turkiye': 'Turkey', 'serbia / montenegro': 'Serbia',
    'ivory coast': "Côte d'Ivoire", "cote d'ivoire": "Côte d'Ivoire",
    'dr congo': 'Dem. Rep. Congo', 'democratic republic of the congo': 'Dem. Rep. Congo',
    'republic of the congo': 'Congo', 'bosnia and herzegovina': 'Bosnia and Herz.',
    'bosnia & herzegovina': 'Bosnia and Herz.', 'north macedonia': 'Macedonia',
    'eswatini': 'eSwatini', 'south sudan': 'S. Sudan', 'central african republic': 'Central African Rep.',
    'western sahara': 'W. Sahara', 'equatorial guinea': 'Eq. Guinea', 'falkland islands': 'Falkland Is.'
  };
  var REGION_LABEL = {
    'north-america': 'North America', 'latin-america': 'Latin America', 'europe': 'Europe & UK',
    'asia-pacific': 'Asia-Pacific', 'middle-east': 'Middle East', 'africa': 'Africa'
  };

  var D2R = Math.PI / 180;

  function latLngTo3D(lat, lng) {
    var phi = (90 - lat) * D2R;
    var theta = (lng + 180) * D2R;
    return {
      x: -Math.sin(phi) * Math.cos(theta),
      y: -Math.cos(phi),
      z: Math.sin(phi) * Math.sin(theta)
    };
  }

  function rotateVector(vec, rx, ry) {
    var cosY = Math.cos(ry), sinY = Math.sin(ry);
    var x1 = vec.x * cosY + vec.z * sinY;
    var z1 = -vec.x * sinY + vec.z * cosY;
    var y1 = vec.y;
    var cosX = Math.cos(rx), sinX = Math.sin(rx);
    return { x: x1, y: y1 * cosX - z1 * sinX, z: y1 * sinX + z1 * cosX };
  }

  // Screen point -> latitude / longitude on the visible hemisphere (null if off the globe)
  function screenToLatLng(mx, my, rx, ry) {
    var x = (mx - view.cx) / sphereRadius;
    var y = (my - view.cy) / sphereRadius;
    var d = x * x + y * y;
    if (d > 1) return null;
    var z = Math.sqrt(1 - d);
    var cosX = Math.cos(rx), sinX = Math.sin(rx);
    var y1 = y * cosX + z * sinX;
    var z1 = -y * sinX + z * cosX;
    var cosY = Math.cos(ry), sinY = Math.sin(ry);
    var X = x * cosY - z1 * sinY;
    var Z = x * sinY + z1 * cosY;
    var lat = Math.asin(Math.max(-1, Math.min(1, -y1))) / D2R;
    var lng = Math.atan2(Z, -X) / D2R - 180;
    while (lng < -180) lng += 360;
    while (lng > 180) lng -= 360;
    return { lat: lat, lng: lng };
  }

  function pointInRing(flat, lng, lat) {
    var inside = false;
    var n = flat.length / 2;
    for (var i = 0, j = n - 1; i < n; j = i++) {
      var xi = flat[2 * i], yi = flat[2 * i + 1], xj = flat[2 * j], yj = flat[2 * j + 1];
      if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) inside = !inside;
    }
    return inside;
  }

  function shapeContains(shape, lat, lng) {
    for (var p = 0; p < shape.polys.length; p++) {
      var poly = shape.polys[p];
      if (lng < poly.minLng || lng > poly.maxLng || lat < poly.minLat || lat > poly.maxLat) continue;
      if (!pointInRing(poly.rings[0].flat, lng, lat)) continue;
      var inHole = false;
      for (var h = 1; h < poly.rings.length; h++) {
        if (pointInRing(poly.rings[h].flat, lng, lat)) { inHole = true; break; }
      }
      if (!inHole) return true;
    }
    return false;
  }

  function init3DData() {
    var src = window.VP_GLOBE_WORLD || [];
    var byName = {};

    world = src.map(function (f) {
      var shape = { name: f.n, polys: [], partner: null, amt: 0, isHq: false };
      f.p.forEach(function (rings) {
        var poly = { rings: [], minLng: 999, maxLng: -999, minLat: 999, maxLat: -999 };
        rings.forEach(function (flat, ri) {
          var n = flat.length / 2;
          var v = new Float32Array(n * 3);
          for (var i = 0; i < n; i++) {
            var p = latLngTo3D(flat[2 * i + 1], flat[2 * i]);
            v[3 * i] = p.x; v[3 * i + 1] = p.y; v[3 * i + 2] = p.z;
            if (ri === 0) {
              if (flat[2 * i] < poly.minLng) poly.minLng = flat[2 * i];
              if (flat[2 * i] > poly.maxLng) poly.maxLng = flat[2 * i];
              if (flat[2 * i + 1] < poly.minLat) poly.minLat = flat[2 * i + 1];
              if (flat[2 * i + 1] > poly.maxLat) poly.maxLat = flat[2 * i + 1];
            }
          }
          poly.rings.push({ flat: flat, v: v, r: new Float32Array(n * 3), n: n });
        });
        shape.polys.push(poly);
      });
      byName[f.n.toLowerCase()] = shape;
      return shape;
    });

    // Link our partner list to the outlines
    smallStates = [];
    COUNTRIES_90.forEach(function (c) {
      var base = (c.name || '').replace(/\s*\(.*\)/, '');
      var key = (NAME_ALIAS[base.toLowerCase()] || base).toLowerCase();
      var shape = byName[key];
      c.v3 = latLngTo3D(c.lat, c.lng);
      c.amt = 0;
      if (shape) {
        shape.partner = c;
        shape.isHq = !!c.isHq;
        c.shape = shape;
        // One consignee entry that covers two outlines (Serbia / Montenegro)
        if (key === 'serbia' && byName['montenegro']) byName['montenegro'].partner = c;
      } else {
        smallStates.push(c);
      }
    });

    // Graticule every 30 degrees
    graticule = [];
    var lat, lng, line;
    for (lat = -60; lat <= 60; lat += 30) {
      line = [];
      for (lng = -180; lng <= 180; lng += 6) line.push(latLngTo3D(lat, lng));
      graticule.push(line);
    }
    for (lng = -180; lng < 180; lng += 30) {
      line = [];
      for (lat = -84; lat <= 84; lat += 6) line.push(latLngTo3D(lat, lng));
      graticule.push(line);
    }
  }

  // ---------------------------------------------------------------------------
  // Drawing helpers
  // ---------------------------------------------------------------------------
  var ROT = { cX: 1, sX: 0, cY: 1, sY: 0 };

  function project(shape, lift) {
    var s = 1 + lift;
    for (var p = 0; p < shape.polys.length; p++) {
      var rings = shape.polys[p].rings;
      for (var q = 0; q < rings.length; q++) {
        var ring = rings[q], v = ring.v, r = ring.r;
        for (var i = 0, n = ring.n * 3; i < n; i += 3) {
          var x = v[i] * s, y = v[i + 1] * s, z = v[i + 2] * s;
          var x1 = x * ROT.cY + z * ROT.sY;
          var z1 = -x * ROT.sY + z * ROT.cY;
          r[i] = x1;
          r[i + 1] = y * ROT.cX - z1 * ROT.sX;
          r[i + 2] = y * ROT.sX + z1 * ROT.cX;
        }
      }
    }
  }

  // Add the visible part of a country to the current path.
  // Points behind the globe are pushed to the rim so the shape closes cleanly.
  function pathShape(shape, offX, offY, rimScale) {
    var any = false;
    var R = sphereRadius, cx = view.cx + (offX || 0), cy = view.cy + (offY || 0);
    for (var p = 0; p < shape.polys.length; p++) {
      var rings = shape.polys[p].rings;
      for (var q = 0; q < rings.length; q++) {
        var ring = rings[q], r = ring.r, n = ring.n;
        var vis = 0, i;
        for (i = 0; i < n; i++) if (r[3 * i + 2] > 0) { vis++; }
        if (!vis) continue;
        any = true;
        for (i = 0; i < n; i++) {
          var rx = r[3 * i], ry = r[3 * i + 1], rz = r[3 * i + 2];
          var px, py;
          if (rz > 0) {
            px = cx + rx * R; py = cy + ry * R;
          } else {
            var l = Math.sqrt(rx * rx + ry * ry) || 1;
            px = cx + (rx / l) * R * rimScale; py = cy + (ry / l) * R * rimScale;
          }
          if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
        }
        ctx.closePath();
      }
    }
    return any;
  }

  // Faint outline of land on the far side, seen through the glass
  function strokeBackSide(shape) {
    var R = sphereRadius, cx = view.cx, cy = view.cy;
    for (var p = 0; p < shape.polys.length; p++) {
      var rings = shape.polys[p].rings;
      var ring = rings[0], r = ring.r, n = ring.n, pen = false;
      for (var i = 0; i < n; i++) {
        if (r[3 * i + 2] < 0) {
          var px = cx + r[3 * i] * R, py = cy + r[3 * i + 1] * R;
          if (!pen) { ctx.moveTo(px, py); pen = true; } else ctx.lineTo(px, py);
        } else pen = false;
      }
    }
  }

  function toScreen(vec3, lift) {
    var s = 1 + (lift || 0);
    var v = rotateVector({ x: vec3.x * s, y: vec3.y * s, z: vec3.z * s }, ROT.rx, ROT.ry);
    return { x: view.cx + v.x * sphereRadius, y: view.cy + v.y * sphereRadius, z: v.z };
  }

  // Supply route from India to a country, lifted above the surface
  function drawArc(a, b, height, color, width, pulseT, dashed) {
    var dot = Math.max(-1, Math.min(1, a.x * b.x + a.y * b.y + a.z * b.z));
    var ang = Math.acos(dot);
    if (ang < 0.02) return;
    var sinA = Math.sin(ang);
    var steps = 36, prev = null, pulse = null;
    ctx.beginPath();
    for (var i = 0; i <= steps; i++) {
      var t = i / steps;
      var k1 = Math.sin((1 - t) * ang) / sinA, k2 = Math.sin(t * ang) / sinA;
      var lift = height * Math.sin(Math.PI * t);
      var p = toScreen({ x: a.x * k1 + b.x * k2, y: a.y * k1 + b.y * k2, z: a.z * k1 + b.z * k2 }, lift);
      var ok = p.z > -0.02;
      if (ok && prev) ctx.lineTo(p.x, p.y);
      else if (ok) ctx.moveTo(p.x, p.y);
      prev = ok ? p : null;
      if (pulseT != null && Math.abs(t - pulseT) < 0.5 / steps && ok) pulse = p;
    }
    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    if (dashed) ctx.setLineDash([5, 5]);
    ctx.stroke();
    if (dashed) ctx.setLineDash([]);
    if (pulse) {
      ctx.fillStyle = '#ffffff';
      ctx.shadowColor = '#5cf2c3';
      ctx.shadowBlur = 10;
      ctx.beginPath();
      ctx.arc(pulse.x, pulse.y, dashed ? 2.6 : 1.8, 0, Math.PI * 2);
      ctx.fill();
      ctx.shadowBlur = 0;
    }
  }

  function badge(text, x, y, kind) {
    var fs = kind === 'hq' ? 12 : 11;
    ctx.font = '600 ' + fs + 'px Inter, -apple-system, "Segoe UI", Roboto, sans-serif';
    var tw = ctx.measureText(text).width;
    var w = tw + 18, h = fs + 10;
    var bx = x + 12, by = y - h / 2;
    if (bx + w > view.w - 6) bx = x - w - 12;
    if (by < 4) by = 4;
    ctx.fillStyle = kind === 'hq' ? 'rgba(8, 70, 90, 0.82)' : 'rgba(6, 28, 26, 0.80)';
    ctx.strokeStyle = kind === 'hq' ? 'rgba(125, 222, 255, 0.9)' : 'rgba(92, 242, 195, 0.8)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(bx, by, w, h, 7); else ctx.rect(bx, by, w, h);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, bx + 9, by + h / 2 + 0.5);
  }

  // ---------------------------------------------------------------------------
  // Main draw loop
  // ---------------------------------------------------------------------------
  function drawGlobe() {
    if (!canvas || !ctx || !container) return;

    var dpr = window.devicePixelRatio || 1;
    var w = container.clientWidth || 500;
    var h = container.clientHeight || 480;
    var targetW = Math.round(w * dpr), targetH = Math.round(h * dpr);
    if (canvas.width !== targetW || canvas.height !== targetH) {
      canvas.width = targetW;
      canvas.height = targetH;
    }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    view.w = w; view.h = h; view.cx = w / 2; view.cy = h / 2;
    // Keep globe size locked to exact original desktop size (192px), responsive on small screens
    sphereRadius = w < 480 ? Math.min(w, h) * 0.40 : 192;
    if (sphereRadius < 110) sphereRadius = 110;
    var R = sphereRadius, cx = view.cx, cy = view.cy;
    var time = Date.now() * 0.001;

    // Stable rotation & locked parallax (eliminates glitchy side-wobble/tilt on hover or touch)
    var wantSpeed = (autoRotate && !isDragging && !reduceMotion) ? (hoveredShape ? 0 : (pointer.inside ? 0.25 : 1)) : 0;
    speedFactor += (wantSpeed - speedFactor) * 0.08;
    if (!hoveredShape) {
      targetRotY += 0.0035 * speedFactor;
    }
    rotX += (targetRotX - rotX) * 0.08;
    rotY += (targetRotY - rotY) * 0.08;
    tiltX = 0;
    tiltY = 0;
    var rx = rotX, ry = rotY;
    ROT.rx = rx; ROT.ry = ry;
    ROT.cX = Math.cos(rx); ROT.sX = Math.sin(rx); ROT.cY = Math.cos(ry); ROT.sY = Math.sin(ry);

    // Which country is under the pointer?
    if (pointer.inside && !isDragging) {
      var ll = screenToLatLng(pointer.x, pointer.y, rx, ry);
      var found = null;
      if (ll) {
        for (var wi = 0; wi < world.length; wi++) {
          if (shapeContains(world[wi], ll.lat, ll.lng)) { found = world[wi]; break; }
        }
      }
      var foundSmall = null;
      if (!found) {
        for (var si = 0; si < smallStates.length; si++) {
          var sc = smallStates[si], sp = toScreen(sc.v3, 0);
          if (sp.z > 0 && Math.hypot(sp.x - pointer.x, sp.y - pointer.y) < 13) { foundSmall = sc; break; }
        }
      }
      hoveredShape = found;
      if (!pointer.fromPill) hoveredCountry = found ? found.partner : foundSmall;
    } else if (!pointer.fromPill) {
      hoveredShape = null;
      hoveredCountry = null;
    }
    if (pointer.fromPill && hoveredCountry) hoveredShape = hoveredCountry.shape || null;
    var tipKey = (hoveredCountry ? hoveredCountry.name : '') + '|' + (hoveredShape ? hoveredShape.name : '') + '|' + (pointer.touch ? 't' : 'm');
    if (tipKey !== lastTipKey) { lastTipKey = tipKey; updateTooltip(); }

    ctx.clearRect(0, 0, w, h);

    // 1. Atmosphere: soft green-cyan glow outside the glass
    var halo = ctx.createRadialGradient(cx, cy, R * 0.96, cx, cy, R * 1.34);
    halo.addColorStop(0, 'rgba(92, 242, 195, 0.28)');
    halo.addColorStop(0.35, 'rgba(60, 190, 200, 0.10)');
    halo.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = halo;
    ctx.beginPath(); ctx.arc(cx, cy, R * 1.34, 0, Math.PI * 2); ctx.fill();

    // 2. Glass sphere: almost clear, so the plant video shows through
    var glass = ctx.createRadialGradient(cx - R * 0.35, cy - R * 0.4, R * 0.05, cx, cy, R);
    glass.addColorStop(0, 'rgba(255, 255, 255, 0.10)');
    glass.addColorStop(0.55, 'rgba(120, 220, 205, 0.018)');
    glass.addColorStop(1, 'rgba(60, 190, 170, 0.08)');
    ctx.fillStyle = glass;
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fill();

    // Project every country once for this frame
    for (var wp = 0; wp < world.length; wp++) {
      var sh = world[wp];
      var targetAmt = (sh === hoveredShape && (sh.partner || sh.isHq)) ? 1 : 0;
      if (sh.partner && selectedCountry === sh.partner) targetAmt = Math.max(targetAmt, 0.55);
      sh.amt += (targetAmt - sh.amt) * 0.16;
      if (Math.abs(sh.amt - targetAmt) < 0.003) sh.amt = targetAmt;
      project(sh, 0.05 * sh.amt);
    }

    // 3. Far side of the globe, faint, seen through the glass
    ctx.beginPath();
    for (var bw = 0; bw < world.length; bw++) strokeBackSide(world[bw]);
    ctx.strokeStyle = 'rgba(140, 235, 215, 0.10)';
    ctx.lineWidth = 0.8;
    ctx.stroke();

    // Everything on the near side is clipped to the sphere
    ctx.save();
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.clip();

    // 4. Graticule
    ctx.beginPath();
    for (var g = 0; g < graticule.length; g++) {
      var gl = graticule[g], pen = false;
      for (var gi = 0; gi < gl.length; gi++) {
        var gp = toScreen(gl[gi], 0);
        if (gp.z > 0) { if (!pen) { ctx.moveTo(gp.x, gp.y); pen = true; } else ctx.lineTo(gp.x, gp.y); } else pen = false;
      }
    }
    ctx.strokeStyle = 'rgba(190, 255, 240, 0.075)';
    ctx.lineWidth = 0.7;
    ctx.stroke();

    // 5. Land: other countries first, then partner countries
    var wi2, shp;
    ctx.beginPath();
    for (wi2 = 0; wi2 < world.length; wi2++) { shp = world[wi2]; if (!shp.partner) pathShape(shp, 0, 0, 1); }
    ctx.fillStyle = 'rgba(214, 240, 233, 0.11)';
    ctx.fill('evenodd');
    ctx.strokeStyle = 'rgba(214, 240, 233, 0.30)';
    ctx.lineWidth = 0.7;
    ctx.stroke();

    for (wi2 = 0; wi2 < world.length; wi2++) {
      shp = world[wi2];
      if (!shp.partner || shp === hoveredShape || shp.isHq) continue;
      var sel = selectedCountry === shp.partner;
      ctx.beginPath();
      if (!pathShape(shp, 0, 0, 1)) continue;
      ctx.fillStyle = sel ? 'rgba(92, 242, 195, 0.55)' : 'rgba(39, 199, 155, 0.42)';
      ctx.fill('evenodd');
      ctx.strokeStyle = sel ? 'rgba(180, 255, 232, 0.95)' : 'rgba(92, 242, 195, 0.80)';
      ctx.lineWidth = sel ? 1.5 : 0.9;
      ctx.stroke();
    }

    // India (headquarters)
    for (wi2 = 0; wi2 < world.length; wi2++) {
      shp = world[wi2];
      if (!shp.isHq || shp === hoveredShape) continue;
      ctx.beginPath();
      if (pathShape(shp, 0, 0, 1)) {
        ctx.fillStyle = 'rgba(125, 222, 255, 0.34)';
        ctx.fill('evenodd');
        ctx.strokeStyle = 'rgba(190, 240, 255, 0.95)';
        ctx.lineWidth = 1.3;
        ctx.stroke();
      }
    }

    // 6. Depth: darken toward the rim, add a soft highlight
    var shade = ctx.createRadialGradient(cx - R * 0.15, cy - R * 0.2, R * 0.35, cx, cy, R);
    shade.addColorStop(0, 'rgba(0, 20, 18, 0)');
    shade.addColorStop(0.75, 'rgba(0, 20, 18, 0.06)');
    shade.addColorStop(1, 'rgba(0, 20, 18, 0.34)');
    ctx.fillStyle = shade;
    ctx.fillRect(cx - R, cy - R, R * 2, R * 2);
    var spec = ctx.createRadialGradient(cx - R * 0.42, cy - R * 0.5, 0, cx - R * 0.42, cy - R * 0.5, R * 0.55);
    spec.addColorStop(0, 'rgba(255, 255, 255, 0.20)');
    spec.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.fillStyle = spec;
    ctx.fillRect(cx - R, cy - R, R * 2, R * 2);

    ctx.restore();

    // 7. Supply routes: gentle ambient set, strong for hovered / selected country
    var hqv = HQ.v3;
    for (var ai = 1; ai < COUNTRIES_90.length; ai += 5) {
      var ac = COUNTRIES_90[ai];
      drawArc(hqv, ac.v3, 0.22, 'rgba(120, 240, 205, 0.20)', 0.8, ((time * 0.16) + ai * 0.137) % 1, false);
    }
    var focus = hoveredCountry || selectedCountry;
    if (focus && !focus.isHq) {
      drawArc(hqv, focus.v3, 0.28, 'rgba(200, 255, 240, 0.95)', 1.8, (time * 0.55) % 1, true);
    }

    // 8. Rim light on the glass edge
    var rim = ctx.createRadialGradient(cx, cy, R * 0.9, cx, cy, R);
    rim.addColorStop(0, 'rgba(120, 240, 215, 0)');
    rim.addColorStop(1, 'rgba(140, 250, 225, 0.32)');
    ctx.fillStyle = rim;
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = 'rgba(170, 255, 235, 0.55)';
    ctx.lineWidth = 1.2;
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.stroke();

    // 9. The hovered country rises off the surface (drawn last, not clipped)
    if (hoveredShape && hoveredShape.amt > 0.01) {
      var hs = hoveredShape, a = hs.amt;
      var layers = 5;
      for (var ly = 0; ly <= layers; ly++) {
        var tt = ly / layers;
        project(hs, 0.05 * a * tt);
        ctx.beginPath();
        var okL = pathShape(hs, -0.6 * a * ly, -1.5 * a * ly, 1 + 0.05 * a * tt);
        if (!okL) break;
        if (ly < layers) {
          ctx.fillStyle = 'rgba(6, 70, 58, ' + (0.55 * a).toFixed(2) + ')';
          ctx.fill('evenodd');
        } else {
          ctx.shadowColor = hs.isHq ? 'rgba(125, 222, 255, 0.9)' : 'rgba(92, 242, 195, 0.95)';
          ctx.shadowBlur = 22 * a;
          ctx.fillStyle = hs.isHq ? 'rgba(160, 232, 255, 0.62)' : 'rgba(110, 250, 205, 0.62)';
          ctx.fill('evenodd');
          ctx.shadowBlur = 0;
          ctx.strokeStyle = 'rgba(235, 255, 250, 0.98)';
          ctx.lineWidth = 1.6;
          ctx.stroke();
        }
      }
    } else if (hoveredShape && !hoveredShape.partner) {
      // Non-partner country: quiet outline only
      ctx.beginPath();
      if (pathShape(hoveredShape, 0, 0, 1)) {
        ctx.fillStyle = 'rgba(255, 255, 255, 0.10)';
        ctx.fill('evenodd');
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.6)';
        ctx.lineWidth = 1;
        ctx.stroke();
      }
    }

    // 10. Markers for partners too small to outline, and the HQ pin
    for (var mi = 0; mi < smallStates.length; mi++) {
      var sm = smallStates[mi], mp = toScreen(sm.v3, 0.01);
      if (mp.z <= 0.02) continue;
      var hot = hoveredCountry === sm || selectedCountry === sm;
      var pr = hot ? 7 + (Math.sin(time * 4) + 1) * 2 : 4.5;
      ctx.strokeStyle = hot ? 'rgba(235, 255, 250, 0.95)' : 'rgba(92, 242, 195, 0.6)';
      ctx.lineWidth = hot ? 1.8 : 1;
      ctx.beginPath(); ctx.arc(mp.x, mp.y, pr, 0, Math.PI * 2); ctx.stroke();
      ctx.fillStyle = hot ? '#eafff8' : '#5cf2c3';
      ctx.beginPath(); ctx.arc(mp.x, mp.y, hot ? 3.8 : 2.8, 0, Math.PI * 2); ctx.fill();
      sm.screenX = mp.x; sm.screenY = mp.y; sm.visible = true;
      if (hot) badge(sm.name, mp.x, mp.y, 'partner');
    }
    for (var si2 = 0; si2 < smallStates.length; si2++) {
      var s2 = smallStates[si2];
      if (toScreen(s2.v3, 0).z <= 0.02) s2.visible = false;
    }

    var hp = toScreen(hqv, 0.012);
    HQ.visible = hp.z > 0;
    if (HQ.visible) {
      var pulse = (Math.sin(time * 2.4) + 1) * 0.5;
      ctx.strokeStyle = 'rgba(160, 235, 255, ' + (0.85 - pulse * 0.6).toFixed(2) + ')';
      ctx.lineWidth = 1.6;
      ctx.beginPath(); ctx.arc(hp.x, hp.y, 6 + pulse * 10, 0, Math.PI * 2); ctx.stroke();
      ctx.fillStyle = '#ffffff';
      ctx.shadowColor = '#7ddeff'; ctx.shadowBlur = 12;
      ctx.beginPath(); ctx.arc(hp.x, hp.y, 4.2, 0, Math.PI * 2); ctx.fill();
      ctx.shadowBlur = 0;
      HQ.screenX = hp.x; HQ.screenY = hp.y;
      if (hoveredShape !== HQ.shape) badge(HQ.shortName, hp.x, hp.y, 'hq');
    }

    // Selected country keeps a name badge
    if (selectedCountry && !selectedCountry.isHq) {
      var scn = selectedCountry, scp = toScreen(scn.v3, 0.05 * (scn.shape ? scn.shape.amt : 0));
      if (scp.z > 0.05 && scn !== hoveredCountry) {
        // keep clear of the HQ badge when both sit close together
        var nudge = (HQ.visible && Math.abs(scp.y - HQ.screenY) < 24 && Math.abs(scp.x - HQ.screenX) < 140) ? -28 : 0;
        badge(scn.shortName || scn.name, scp.x, scp.y + nudge, 'partner');
      }
    }

    positionTooltip();

    if (isGlobeActive) {
      animFrameId = requestAnimationFrame(drawGlobe);
    }
  }

  // ---------------------------------------------------------------------------
  // Tooltip that follows the pointer
  // ---------------------------------------------------------------------------
  function updateTooltip() {
    if (!tipEl) return;
    var c = hoveredCountry, s = hoveredShape;
    if (!c && !s) { tipEl.classList.remove('show'); return; }
    var name = c ? (c.isHq ? 'India' : c.name) : s.name;
    var meta = '';
    if (c && c.isHq) meta = '<span class="vp-tip-dot hq"></span>Global operations headquarters';
    else if (c) meta = '<span class="vp-tip-dot"></span>Export partner' + (REGION_LABEL[c.region] ? ' · ' + REGION_LABEL[c.region] : '');
    var hint = c ? '<span class="vp-tip-hint">' + (pointer.touch ? 'Tap again to focus' : 'Click to focus') + '</span>' : '';
    tipEl.innerHTML = '<strong>' + name + '</strong>' + (meta ? '<span class="vp-tip-meta">' + meta + '</span>' : '') + hint;
    tipEl.classList.add('show');
  }

  function positionTooltip() {
    if (!tipEl || !tipEl.classList.contains('show')) return;
    var x = pointer.x, y = pointer.y;
    if (pointer.fromPill && hoveredCountry) {
      var hp = toScreen(hoveredCountry.v3, 0);
      x = hp.x; y = hp.y;
    }
    var tw = tipEl.offsetWidth, th = tipEl.offsetHeight;
    var left = x + 16, top = y - th - 14;
    if (left + tw > view.w - 4) left = x - tw - 16;
    if (top < 4) top = y + 18;
    tipEl.style.transform = 'translate(' + Math.round(left) + 'px,' + Math.round(top) + 'px)';
  }

  function updateHUD(reg) {
    activeRegion = reg;
  }

  function syncPills() {
    var pills = document.querySelectorAll('.vp-country-pill');
    for (var i = 0; i < pills.length; i++) {
      var nm = pills[i].getAttribute('data-name');
      pills[i].classList.toggle('is-hover', !!hoveredCountry && !pointer.fromPill && (hoveredCountry.name || '').toLowerCase() === nm);
    }
  }

  function setOrbitButton(on) {
    var btn = document.getElementById('vpGlobeToggleRotate');
    if (!btn) return;
    btn.classList.toggle('active', on);
    btn.innerHTML = on ? '<i class="fas fa-pause"></i> Pause Orbit' : '<i class="fas fa-play"></i> Resume Orbit';
  }

  function rotateToRegion(reg) {
    autoRotate = false;
    setOrbitButton(false);

    var targetY = -((reg.lng + 90) * Math.PI) / 180;
    var targetX = -(reg.lat * Math.PI) / 180 * 0.85;

    var currentY = targetRotY;
    var diff = (targetY - currentY) % (Math.PI * 2);
    if (diff > Math.PI) diff -= Math.PI * 2;
    if (diff < -Math.PI) diff += Math.PI * 2;

    targetRotY = currentY + diff;
    targetRotX = Math.max(-0.75, Math.min(0.75, targetX));
    selectedCountry = reg.isHq ? null : reg;
    updateHUD(reg);
  }

  function setupEvents() {
    function localPoint(e) {
      var rect = canvas.getBoundingClientRect();
      var t = e.touches && e.touches[0] ? e.touches[0] : (e.changedTouches && e.changedTouches[0] ? e.changedTouches[0] : e);
      return { x: t.clientX - rect.left, y: t.clientY - rect.top, cx: t.clientX, cy: t.clientY };
    }

    function onPointerDown(e) {
      isDragging = true;
      dragDistance = 0;
      var p = localPoint(e);
      lastMouseX = p.cx;
      lastMouseY = p.cy;
      canvas.style.cursor = 'grabbing';
    }

    function onPointerMove(e) {
      if (!isDragging) return;
      var p = localPoint(e);
      var dx = p.cx - lastMouseX;
      var dy = p.cy - lastMouseY;
      dragDistance += Math.abs(dx) + Math.abs(dy);
      if (dragDistance > 4 && autoRotate) { autoRotate = false; setOrbitButton(false); }
      targetRotY += dx * 0.008;
      targetRotX += dy * 0.008;
      targetRotX = Math.max(-0.85, Math.min(0.85, targetRotX));
      lastMouseX = p.cx;
      lastMouseY = p.cy;
    }

    function onPointerUp() {
      isDragging = false;
      canvas.style.cursor = hoveredCountry ? 'pointer' : 'grab';
    }

    canvas.addEventListener('mousemove', function (e) {
      var p = localPoint(e);
      pointer.x = p.x; pointer.y = p.y; pointer.inside = true; pointer.touch = false; pointer.fromPill = false;
      if (!isDragging) canvas.style.cursor = hoveredCountry ? 'pointer' : 'grab';
      syncPills();
    });
    canvas.addEventListener('mouseleave', function () {
      pointer.inside = false;
      if (!pointer.fromPill) { hoveredShape = null; hoveredCountry = null; updateTooltip(); }
      syncPills();
    });

    canvas.addEventListener('mousedown', onPointerDown);
    window.addEventListener('mousemove', onPointerMove);
    window.addEventListener('mouseup', onPointerUp);

    canvas.addEventListener('touchstart', function (e) {
      var p = localPoint(e);
      pointer.x = p.x; pointer.y = p.y; pointer.touch = true; pointer.fromPill = false;
      onPointerDown(e);
    }, { passive: true });
    window.addEventListener('touchmove', onPointerMove, { passive: true });
    window.addEventListener('touchend', onPointerUp);

    canvas.addEventListener('click', function (e) {
      if (dragDistance > 6) return;           // that was a drag, not a click
      var p = localPoint(e);
      pointer.x = p.x; pointer.y = p.y;
      // Resolve the country at the click point (also works for touch, which has no hover)
      var ll = screenToLatLng(p.x, p.y, rotX + tiltX, rotY + tiltY);
      var hit = null;
      if (ll) {
        for (var i = 0; i < world.length; i++) {
          if (shapeContains(world[i], ll.lat, ll.lng)) { hit = world[i].partner; break; }
        }
      }
      if (!hit) {
        for (var s = 0; s < smallStates.length; s++) {
          var sc = smallStates[s];
          if (sc.visible && Math.hypot(sc.screenX - p.x, sc.screenY - p.y) < 16) { hit = sc; break; }
        }
      }
      if (hit) {
        rotateToRegion(hit);
        document.querySelectorAll('.vp-country-pill').forEach(function (pill) {
          pill.classList.toggle('active', pill.getAttribute('data-name') === (hit.name || '').toLowerCase());
        });
        var act = document.querySelector('.vp-country-pill.active');
        if (act && act.scrollIntoView) act.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
    });

    var toggleBtn = document.getElementById('vpGlobeToggleRotate');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        autoRotate = !autoRotate;
        setOrbitButton(autoRotate);
      });
    }
  }


  function setupCountriesRoster() {
    var grid = document.getElementById('vpCountriesPillsGrid');
    if (!grid) return;

    grid.innerHTML = '';
    var activeFilter = 'all';
    var searchQuery = '';

    // Wrap grid in horizontal scroll container with navigation arrows if not already wrapped
    var parent = grid.parentElement;
    if (parent && !parent.classList.contains('vp-countries-hscroll-wrap')) {
      var hwrap = document.createElement('div');
      hwrap.className = 'vp-countries-hscroll-wrap';

      var leftBtn = document.createElement('button');
      leftBtn.type = 'button';
      leftBtn.className = 'vp-countries-scroll-btn vp-countries-scroll-left';
      leftBtn.innerHTML = '&lsaquo;';
      leftBtn.title = 'Scroll left';
      leftBtn.setAttribute('aria-label', 'Scroll countries left');
      leftBtn.addEventListener('click', function () {
        grid.scrollBy({ left: -300, behavior: 'smooth' });
      });

      var rightBtn = document.createElement('button');
      rightBtn.type = 'button';
      rightBtn.className = 'vp-countries-scroll-btn vp-countries-scroll-right';
      rightBtn.innerHTML = '&rsaquo;';
      rightBtn.title = 'Scroll right';
      rightBtn.setAttribute('aria-label', 'Scroll countries right');
      rightBtn.addEventListener('click', function () {
        grid.scrollBy({ left: 300, behavior: 'smooth' });
      });

      parent.insertBefore(hwrap, grid);
      hwrap.appendChild(leftBtn);
      hwrap.appendChild(grid);
      hwrap.appendChild(rightBtn);
    }

    // Horizontal wheel scroll support
    grid.addEventListener('wheel', function (e) {
      if (e.deltaY !== 0) {
        e.preventDefault();
        grid.scrollLeft += e.deltaY;
      }
    }, { passive: false });

    // Render all country pills
    var pills = [];
    COUNTRIES_90.forEach(function (c) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'vp-country-pill' + (c.isHq ? ' is-hq' : '');
      btn.setAttribute('data-name', (c.name || '').toLowerCase());
      btn.setAttribute('data-short', (c.shortName || '').toLowerCase());
      btn.setAttribute('data-region', c.region || 'other');
      btn.setAttribute('title', c.isHq ? 'Vasudha Pharma Global HQ' : 'Export Partner: ' + c.name);

      btn.innerHTML = [
        '<span class="vp-country-pill-dot"></span>',
        '<span class="vp-country-pill-name">' + (c.isHq ? '★ ' : '') + c.name + '</span>'
      ].join('');

      btn.addEventListener('mouseenter', function () { pointer.fromPill = true; hoveredCountry = c; });
      btn.addEventListener('mouseleave', function () { pointer.fromPill = false; hoveredCountry = null; hoveredShape = null; });
      btn.addEventListener('focus', function () { pointer.fromPill = true; hoveredCountry = c; });
      btn.addEventListener('blur', function () { pointer.fromPill = false; hoveredCountry = null; hoveredShape = null; });
      btn.addEventListener('click', function () {
        rotateToRegion(c);
        // Highlight active pill
        document.querySelectorAll('.vp-country-pill').forEach(function (p) {
          p.classList.remove('active');
        });
        btn.classList.add('active');
        btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        scrollGlobeIntoViewIfNeeded();
      });

      grid.appendChild(btn);
      pills.push({ el: btn, country: c });
    });

    // Create empty state element
    var emptyState = document.createElement('div');
    emptyState.className = 'vp-countries-empty-state';
    emptyState.style.display = 'none';
    emptyState.innerHTML = '<i class="fas fa-search" style="font-size:1.5rem; margin-bottom:8px; display:block; opacity:0.6;"></i>No matching countries found. Try searching another nation or regulatory region.';
    grid.appendChild(emptyState);

    function applyFilters() {
      var visibleCount = 0;
      var q = searchQuery.toLowerCase().trim();

      pills.forEach(function (p) {
        var matchesRegion = (activeFilter === 'all') || (p.country.region === activeFilter);
        var matchesSearch = !q || 
          p.country.name.toLowerCase().indexOf(q) !== -1 || 
          (p.country.shortName && p.country.shortName.toLowerCase().indexOf(q) !== -1);

        if (matchesRegion && matchesSearch) {
          p.el.classList.remove('hidden-by-filter');
          visibleCount++;
        } else {
          p.el.classList.add('hidden-by-filter');
        }
      });

      emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
      grid.scrollTo({ left: 0, behavior: 'smooth' });
    }

    // Filter tabs
    var tabBtns = document.querySelectorAll('.vp-country-tab');
    tabBtns.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabBtns.forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        activeFilter = tab.getAttribute('data-region') || 'all';
        applyFilters();
      });
    });

    // Search input
    var searchInput = document.getElementById('vpCountrySearchInput');
    var clearBtn = document.getElementById('vpCountrySearchClear');

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        searchQuery = searchInput.value;
        if (clearBtn) {
          clearBtn.style.display = searchQuery ? 'block' : 'none';
        }
        applyFilters();
      });

      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          var firstVisible = grid.querySelector('.vp-country-pill:not(.hidden-by-filter)');
          if (firstVisible) {
            firstVisible.click();
          }
        }
      });
    }

    if (clearBtn && searchInput) {
      clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchQuery = '';
        clearBtn.style.display = 'none';
        searchInput.focus();
        applyFilters();
      });
    }

    // Connect KPI Card "90+ Countries Served" to scroll down to this roster
    var kpiCard = document.querySelector('a[href*="about-us.html#international-presence"].vp-globe-kpi-card');
    if (kpiCard) {
      kpiCard.addEventListener('click', function (e) {
        e.preventDefault();
        var roster = document.getElementById('vpGlobeCountriesRoster');
        if (roster) {
          roster.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          if (searchInput) searchInput.focus();
        }
      });
    }
  }

  var isGlobeActive = false;
  function startGlobe() {
    if (isGlobeActive) return;
    isGlobeActive = true;
    if (animFrameId) cancelAnimationFrame(animFrameId);
    animFrameId = requestAnimationFrame(drawGlobe);
  }

  function stopGlobe() {
    isGlobeActive = false;
    if (animFrameId) {
      cancelAnimationFrame(animFrameId);
      animFrameId = null;
    }
  }

  function init() {
    container = document.getElementById('vpGlobeCanvasContainer');
    canvas = document.getElementById('vpGlobeCanvas');
    if (!container || !canvas) return;

    ctx = canvas.getContext('2d');
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'Interactive 3D globe of Vasudha Pharma export markets. Use the country list below the globe to pick a country with the keyboard.');
    tipEl = document.createElement('div');
    tipEl.className = 'vp-globe-tip';
    tipEl.setAttribute('aria-hidden', 'true');
    container.appendChild(tipEl);
    if (reduceMotion) { autoRotate = false; setOrbitButton(false); }
    init3DData();
    setupEvents();
    setupCountriesRoster();

    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            startGlobe();
          } else {
            stopGlobe();
          }
        });
      }, { rootMargin: '120px 0px' });
      observer.observe(container);
    } else {
      startGlobe();
    }

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stopGlobe();
      } else if (container && container.getBoundingClientRect().top < window.innerHeight && container.getBoundingClientRect().bottom > 0) {
        startGlobe();
      }
    });
  }

  function scrollGlobeIntoViewIfNeeded() {
    var grid = document.querySelector('.vp-globe-grid');
    if (!grid) return;
    var rect = grid.getBoundingClientRect();
    var fullyVisible = rect.top >= 0 && rect.bottom <= window.innerHeight;
    if (!fullyVisible) {
      grid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  window.VP_COUNTRIES_90 = COUNTRIES_90;

  window.VP_SelectGlobeRegion = function (regionOrName) {
    if (!regionOrName) return;
    var match = COUNTRIES_90.filter(function (r) {
      return (r.name && r.name.toLowerCase().indexOf(regionOrName.toLowerCase()) !== -1) ||
             (r.shortName && r.shortName.toLowerCase().indexOf(regionOrName.toLowerCase()) !== -1);
    })[0];
    if (match) {
      rotateToRegion(match);
      scrollGlobeIntoViewIfNeeded();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
