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
  var rotX = 0;
  var rotY = 2.45;
  var targetRotX = 0;
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
  var smallStates = [];
  var landDots = [];    // United Carriers-style illuminated dot matrix    // partners too small for an outline: drawn as glowing markers
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
    // Precompute United Carriers-style illuminated land dots
    landDots = [];
    var step = 3.6;
    for (var dlat = -65; dlat <= 75; dlat += step) {
      var cosL = Math.cos(dlat * D2R);
      var dlngStep = step / Math.max(0.25, cosL);
      for (var dlng = -180; dlng < 180; dlng += dlngStep) {
        for (var wdi = 0; wdi < world.length; wdi++) {
          if (shapeContains(world[wdi], dlat, dlng)) {
            var lp = latLngTo3D(dlat, dlng);
            landDots.push({ x: lp.x, y: lp.y, z: lp.z, isPartner: !!world[wdi].partner });
            break;
          }
        }
      }
    }

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

  // Cinematic shooting star / meteor with layered shadow tail and celestial plume
  function drawShootingStar(a, b, height, progress, isFocus) {
    if (progress <= 0 || progress >= 1) return;

    var dot = Math.max(-1, Math.min(1, a.x * b.x + a.y * b.y + a.z * b.z));
    var ang = Math.acos(dot);
    if (ang < 0.02) return;
    var sinA = Math.sin(ang);

    // Cinematic long sweeping tail: 26% to 38% of Great-Circle arc
    var tailLen = Math.min(0.38, Math.max(0.24, 0.30 * (ang / Math.PI)));
    var headT = progress;
    var tailT = Math.max(0, headT - tailLen);
    if (headT <= tailT) return;

    var steps = 18;
    var pts = [];
    for (var i = 0; i <= steps; i++) {
      var t = tailT + (headT - tailT) * (i / steps);
      var k1 = Math.sin((1 - t) * ang) / sinA;
      var k2 = Math.sin(t * ang) / sinA;
      var lift = height * Math.sin(Math.PI * t);
      var p = toScreen({ x: a.x * k1 + b.x * k2, y: a.y * k1 + b.y * k2, z: a.z * k1 + b.z * k2 }, lift);
      if (p.z > -0.02) {
        pts.push({ x: p.x, y: p.y, z: p.z, t: t });
      }
    }

    if (pts.length < 2) return;

    var pTail = pts[0];
    var pHead = pts[pts.length - 1];
    var dx = pHead.x - pTail.x, dy = pHead.y - pTail.y;
    if (Math.hypot(dx, dy) < 1.5) return;

    // 1. Layer 1: Diffuse Atmospheric Shadow Tail / Cosmic Emerald Plume (wider, soft haze)
    var shadowGrad = ctx.createLinearGradient(pTail.x, pTail.y, pHead.x, pHead.y);
    if (isFocus) {
      shadowGrad.addColorStop(0, 'rgba(6, 75, 62, 0)');
      shadowGrad.addColorStop(0.35, 'rgba(16, 125, 95, 0.25)');
      shadowGrad.addColorStop(0.75, 'rgba(56, 239, 125, 0.50)');
      shadowGrad.addColorStop(1, 'rgba(160, 255, 220, 0.85)');
    } else {
      shadowGrad.addColorStop(0, 'rgba(4, 45, 36, 0)');
      shadowGrad.addColorStop(0.35, 'rgba(12, 95, 72, 0.20)');
      shadowGrad.addColorStop(0.75, 'rgba(79, 227, 176, 0.40)');
      shadowGrad.addColorStop(1, 'rgba(160, 245, 215, 0.75)');
    }

    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    for (var j = 1; j < pts.length; j++) {
      ctx.lineTo(pts[j].x, pts[j].y);
    }
    ctx.strokeStyle = shadowGrad;
    ctx.lineWidth = isFocus ? 4.8 : 3.6;
    ctx.lineCap = 'round';
    ctx.stroke();

    // 2. Layer 2: Radiant Focused Core Trail
    var coreGrad = ctx.createLinearGradient(pTail.x, pTail.y, pHead.x, pHead.y);
    if (isFocus) {
      coreGrad.addColorStop(0, 'rgba(56, 239, 125, 0)');
      coreGrad.addColorStop(0.55, 'rgba(56, 239, 125, 0.70)');
      coreGrad.addColorStop(1, '#ffffff');
    } else {
      coreGrad.addColorStop(0, 'rgba(79, 227, 176, 0)');
      coreGrad.addColorStop(0.55, 'rgba(79, 227, 176, 0.60)');
      coreGrad.addColorStop(1, '#ffffff');
    }

    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    for (var k = 1; k < pts.length; k++) {
      ctx.lineTo(pts[k].x, pts[k].y);
    }
    ctx.strokeStyle = coreGrad;
    ctx.lineWidth = isFocus ? 2.2 : 1.6;
    ctx.stroke();

    // 3. Layer 3: High-Energy White Photon Filament (front 35% of tail)
    var filamentStartIdx = Math.floor(pts.length * 0.65);
    if (filamentStartIdx < pts.length - 1) {
      var pFilStart = pts[filamentStartIdx];
      var filGrad = ctx.createLinearGradient(pFilStart.x, pFilStart.y, pHead.x, pHead.y);
      filGrad.addColorStop(0, 'rgba(255, 255, 255, 0)');
      filGrad.addColorStop(1, 'rgba(255, 255, 255, 0.95)');

      ctx.beginPath();
      ctx.moveTo(pFilStart.x, pFilStart.y);
      for (var f = filamentStartIdx + 1; f < pts.length; f++) {
        ctx.lineTo(pts[f].x, pts[f].y);
      }
      ctx.strokeStyle = filGrad;
      ctx.lineWidth = isFocus ? 1.4 : 1.0;
      ctx.stroke();
    }

    // 4. Stardust Particles trailing behind the comet
    if (pts.length >= 8) {
      var dustIndices = [Math.floor(pts.length * 0.25), Math.floor(pts.length * 0.50)];
      for (var di = 0; di < dustIndices.length; di++) {
        var dpt = pts[dustIndices[di]];
        if (dpt.z > 0.01) {
          ctx.fillStyle = isFocus ? 'rgba(160, 255, 220, 0.45)' : 'rgba(79, 227, 176, 0.35)';
          ctx.beginPath();
          ctx.arc(dpt.x, dpt.y, 1.0, 0, Math.PI * 2);
          ctx.fill();
        }
      }
    }

    // 5. Radiant Shooting Star Head (Celestial Flare)
    if (pHead.z > 0.005) {
      // Outer lens flare corona
      ctx.fillStyle = isFocus ? 'rgba(56, 239, 125, 0.35)' : 'rgba(79, 227, 176, 0.28)';
      ctx.beginPath();
      ctx.arc(pHead.x, pHead.y, isFocus ? 5.2 : 3.8, 0, Math.PI * 2);
      ctx.fill();

      // Middle mint halo
      ctx.fillStyle = isFocus ? 'rgba(160, 255, 220, 0.85)' : 'rgba(125, 245, 205, 0.75)';
      ctx.beginPath();
      ctx.arc(pHead.x, pHead.y, isFocus ? 2.8 : 2.1, 0, Math.PI * 2);
      ctx.fill();

      // Brilliant pure white spark core
      ctx.fillStyle = '#ffffff';
      ctx.beginPath();
      ctx.arc(pHead.x, pHead.y, isFocus ? 1.6 : 1.15, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  // Cinematic dual-ring arrival ripple when shooting star touches down
  function drawArrivalPing(vec3, pingT, isFocus) {
    var p = toScreen(vec3, 0.015);
    if (p.z <= 0.01) return;

    // Primary ripple ring
    var r1 = 2.0 + 12.0 * pingT;
    var a1 = Math.sin((1 - pingT) * Math.PI * 0.5) * (isFocus ? 0.9 : 0.65);
    ctx.strokeStyle = isFocus ? 'rgba(56, 239, 125, ' + a1.toFixed(2) + ')' : 'rgba(79, 227, 176, ' + a1.toFixed(2) + ')';
    ctx.lineWidth = isFocus ? 1.5 : 1.0;
    ctx.beginPath();
    ctx.arc(p.x, p.y, r1, 0, Math.PI * 2);
    ctx.stroke();

    // Secondary delayed echo ring (starts when pingT > 0.25)
    if (pingT > 0.25) {
      var pingT2 = (pingT - 0.25) / 0.75;
      var r2 = 1.5 + 8.0 * pingT2;
      var a2 = Math.sin((1 - pingT2) * Math.PI * 0.5) * (isFocus ? 0.55 : 0.4);
      ctx.strokeStyle = isFocus ? 'rgba(160, 255, 220, ' + a2.toFixed(2) + ')' : 'rgba(125, 245, 205, ' + a2.toFixed(2) + ')';
      ctx.lineWidth = 0.8;
      ctx.beginPath();
      ctx.arc(p.x, p.y, r2, 0, Math.PI * 2);
      ctx.stroke();
    }

    // Soft beacon glow at touchdown point that fades
    var centerAlpha = Math.max(0, 1 - pingT * 1.8) * (isFocus ? 0.9 : 0.7);
    if (centerAlpha > 0.02) {
      ctx.fillStyle = 'rgba(255, 255, 255, ' + centerAlpha.toFixed(2) + ')';
      ctx.beginPath();
      ctx.arc(p.x, p.y, 1.8, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  // Trajectory guide line (only drawn for focused / selected routes)
  function drawArc(a, b, height, color, width, pulseT, dashed) {
    var dot = Math.max(-1, Math.min(1, a.x * b.x + a.y * b.y + a.z * b.z));
    var ang = Math.acos(dot);
    if (ang < 0.02) return;
    var sinA = Math.sin(ang);
    var steps = 36, prev = null;
    ctx.beginPath();
    for (var i = 0; i <= steps; i++) {
      var t = i / steps;
      var k1 = Math.sin((1 - t) * ang) / sinA, k2 = Math.sin(t * ang) / sinA;
      var lift = height * Math.sin(Math.PI * t);
      var p = toScreen({ x: a.x * k1 + b.x * k2, y: a.y * k1 + b.y * k2, z: a.z * k1 + b.z * k2 }, lift);
      var ok = p.z > -0.04;
      if (ok && prev) ctx.lineTo(p.x, p.y);
      else if (ok) ctx.moveTo(p.x, p.y);
      prev = ok ? p : null;
    }
    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    if (dashed) ctx.setLineDash([4, 4]);
    ctx.stroke();
    if (dashed) ctx.setLineDash([]);
  }

  function badge(text, x, y, kind) {
    var fs = (kind === 'hq' || kind === 'india-active') ? 12 : 11;
    ctx.font = '600 ' + fs + 'px Inter, -apple-system, "Segoe UI", Roboto, sans-serif';
    var tw = ctx.measureText(text).width;
    var w = tw + 22, h = fs + 12;
    var bx = x + 12, by = y - h / 2;
    if (bx + w > view.w - 6) bx = x - w - 12;
    if (by < 4) by = 4;

    if (kind === 'india-active' || kind === 'hq') {
      HQ.badgeRect = { x: bx, y: by, w: w, h: h };
    }

    if (kind === 'india-active') {
      ctx.fillStyle = 'rgba(6, 24, 20, 0.94)';
      ctx.strokeStyle = '#38ef7d';
      ctx.lineWidth = 1.8;
      ctx.shadowColor = '#1DB88A';
      ctx.shadowBlur = 12;
    } else if (kind === 'hq') {
      ctx.fillStyle = 'rgba(8, 70, 90, 0.85)';
      ctx.strokeStyle = 'rgba(125, 222, 255, 0.95)';
      ctx.lineWidth = 1.2;
      ctx.shadowColor = 'transparent';
      ctx.shadowBlur = 0;
    } else {
      ctx.fillStyle = 'rgba(6, 28, 26, 0.80)';
      ctx.strokeStyle = 'rgba(92, 242, 195, 0.8)';
      ctx.lineWidth = 1;
      ctx.shadowColor = 'transparent';
      ctx.shadowBlur = 0;
    }

    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(bx, by, w, h, 8); else ctx.rect(bx, by, w, h);
    ctx.fill();
    ctx.stroke();
    ctx.shadowBlur = 0;

    ctx.fillStyle = kind === 'india-active' ? '#38ef7d' : '#ffffff';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, bx + 10, by + h / 2 + 0.5);
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
    // Dynamic cinematic scaling (inspired by United Carriers):
    // Scales to fill the container proportionally with safe headroom for 3D partner elevation
    var minDim = Math.min(w, h);
    if (w < 480) {
      sphereRadius = Math.max(140, Math.floor(minDim * 0.46));
    } else if (w < 768) {
      sphereRadius = Math.max(200, Math.floor(minDim * 0.47));
    } else {
      // Large cinematic scale filling the container cleanly
      sphereRadius = Math.max(300, Math.floor(minDim * 0.485));
    }
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

    ctx.clearRect(0, 0, w, h);

    // Planetary Ocean: solid, deep cosmic obsidian-emerald base (no halos, no bubbles)
    var ocean = ctx.createRadialGradient(cx - R * 0.2, cy - R * 0.25, R * 0.05, cx, cy, R);
    ocean.addColorStop(0, '#0c2e25');
    ocean.addColorStop(0.50, '#071f19');
    ocean.addColorStop(0.85, '#041310');
    ocean.addColorStop(1, '#020b09');
    ctx.fillStyle = ocean;
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
    ctx.strokeStyle = 'rgba(78, 227, 176, 0.15)';
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
    ctx.strokeStyle = 'rgba(78, 227, 176, 0.18)';
    ctx.lineWidth = 0.8;
    ctx.stroke();

    // 5. Land: United Carriers Illuminated Dot-Matrix + Real Country Boundaries
    var wi2, shp;
    ctx.beginPath();
    for (wi2 = 0; wi2 < world.length; wi2++) { shp = world[wi2]; if (!shp.partner) pathShape(shp, 0, 0, 1); }
    ctx.fillStyle = 'rgba(10, 32, 26, 0.70)';
    ctx.fill('evenodd');
    ctx.strokeStyle = 'rgba(38, 115, 90, 0.35)';
    ctx.lineWidth = 0.75;
    ctx.stroke();

    for (wi2 = 0; wi2 < world.length; wi2++) {
      shp = world[wi2];
      if (!shp.partner || shp === hoveredShape || shp.isHq) continue;
      var sel = selectedCountry === shp.partner;
      ctx.beginPath();
      if (!pathShape(shp, 0, 0, 1)) continue;
      ctx.fillStyle = sel ? 'rgba(79, 227, 176, 0.50)' : 'rgba(29, 184, 138, 0.28)';
      ctx.fill('evenodd');
      ctx.strokeStyle = sel ? '#ffffff' : 'rgba(52, 211, 153, 0.85)';
      ctx.lineWidth = sel ? 1.8 : 1.1;
      ctx.stroke();
    }

    // United Carriers illuminated stippled dot matrix over landmasses (shines directly on top of atmospheric halo)
    if (landDots && landDots.length) {
      ctx.beginPath();
      var dotRadius = R > 320 ? 1.35 : 1.15;
      for (var di = 0; di < landDots.length; di++) {
        var ld = landDots[di];
        var ldp = toScreen(ld, 0);
        if (ldp.z > 0.005) {
          ctx.moveTo(ldp.x + dotRadius, ldp.y);
          ctx.arc(ldp.x, ldp.y, dotRadius, 0, Math.PI * 2);
        }
      }
      ctx.fillStyle = 'rgba(255, 255, 255, 0.88)';
      ctx.fill();
    }

    // India (headquarters)
    for (wi2 = 0; wi2 < world.length; wi2++) {
      shp = world[wi2];
      if (!shp.isHq || shp === hoveredShape) continue;
      ctx.beginPath();
      if (pathShape(shp, 0, 0, 1)) {
        ctx.fillStyle = 'rgba(56, 239, 125, 0.92)';
        ctx.fill('evenodd');
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 1.6;
        ctx.stroke();
      }
    }

    // 6. Depth: darken toward the rim, add a soft emerald sheen
    var shade = ctx.createRadialGradient(cx - R * 0.15, cy - R * 0.2, R * 0.35, cx, cy, R);
    shade.addColorStop(0, 'rgba(0, 20, 18, 0)');
    shade.addColorStop(0.75, 'rgba(0, 20, 18, 0.15)');
    shade.addColorStop(1, 'rgba(0, 10, 8, 0.65)');
    ctx.fillStyle = shade;
    ctx.fillRect(cx - R, cy - R, R * 2, R * 2);
    var spec = ctx.createRadialGradient(cx - R * 0.35, cy - R * 0.35, 0, cx - R * 0.35, cy - R * 0.35, R * 0.55);
    spec.addColorStop(0, 'rgba(79, 227, 176, 0.10)');
    spec.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = spec;
    ctx.fillRect(cx - R, cy - R, R * 2, R * 2);

    ctx.restore();

    // 7. Supply routes: slow, majestic cinematic shooting stars to all 90+ partner countries (shadow tail + cosmic plume)
    var hqv = HQ.v3;
    for (var ai = 1; ai < COUNTRIES_90.length; ai++) {
      var ac = COUNTRIES_90[ai];
      if (!ac || !ac.v3) continue;
      var isFocus = (hoveredCountry === ac || selectedCountry === ac);
      if (isFocus) continue; // Focus route is rendered below with prominent spotlight

      var dot = Math.max(-1, Math.min(1, hqv.x * ac.v3.x + hqv.y * ac.v3.y + hqv.z * ac.v3.z));
      var ang = Math.acos(dot);
      var arcHeight = Math.min(0.125, Math.max(0.045, 0.085 * (ang / Math.PI)));

      // Slow, majestic cinematic flight: 3.4s (regional) to 5.6s (trans-oceanic)
      var flightDuration = 3.4 + 2.2 * (ang / Math.PI);
      // Breathing dark pause between shooting stars (2.6s to 4.2s)
      var restDuration = 2.6 + ((ai * 13) % 7) * 0.25;
      var cyclePeriod = flightDuration + restDuration;
      var phaseOffset = ((ai * 0.6180339887) % 1) * cyclePeriod;
      var localT = (time + phaseOffset) % cyclePeriod;

      if (localT < flightDuration) {
        var rawT = localT / flightDuration;
        // Smoothstep acceleration/deceleration for regal cinematic flight
        var progress = rawT * rawT * (3 - 2 * rawT);
        drawShootingStar(hqv, ac.v3, arcHeight, progress, false);
      } else if (localT < flightDuration + 0.85) {
        var pingT = (localT - flightDuration) / 0.85;
        drawArrivalPing(ac.v3, pingT, false);
      }
    }

    var focus = hoveredCountry || selectedCountry;
    if (focus && !focus.isHq && focus.v3) {
      var fDot = Math.max(-1, Math.min(1, hqv.x * focus.v3.x + hqv.y * focus.v3.y + hqv.z * focus.v3.z));
      var fAng = Math.acos(fDot);
      var fArcHeight = Math.min(0.135, Math.max(0.05, 0.095 * (fAng / Math.PI)));
      // Faint elegant dashed guide trajectory for the focused country
      drawArc(hqv, focus.v3, fArcHeight, 'rgba(79, 227, 176, 0.22)', 1.0, null, true);
      // Slower, majestic focused shooting star (smooth looping glide ~3.6s)
      var fRawT = (time * 0.28) % 1;
      var fProgress = fRawT * fRawT * (3 - 2 * fRawT);
      drawShootingStar(hqv, focus.v3, fArcHeight, fProgress, true);
      if (fRawT > 0.88) {
        drawArrivalPing(focus.v3, (fRawT - 0.88) / 0.12, true);
      }
    }

    // 8. Hovered partner country rises off the surface (elevated 3D extrusion, drawn last, not clipped)
    if (hoveredShape && hoveredShape.amt > 0.01) {
      var hs = hoveredShape, a = hs.amt;
      var layers = 6;
      for (var ly = 0; ly <= layers; ly++) {
        var tt = ly / layers;
        project(hs, 0.062 * a * tt);
        ctx.beginPath();
        var okL = pathShape(hs, -0.7 * a * ly, -1.6 * a * ly, 1 + 0.062 * a * tt);
        if (!okL) break;
        if (ly < layers) {
          // Extruded side bevel walls with depth lighting
          ctx.fillStyle = 'rgba(6, 75, 62, ' + (0.62 * a * (1 - tt * 0.35)).toFixed(2) + ')';
          ctx.fill('evenodd');
        } else {
          // Top illuminated face with crisp mint glow
          ctx.shadowColor = hs.isHq ? 'rgba(125, 222, 255, 0.95)' : 'rgba(92, 242, 195, 1)';
          ctx.shadowBlur = 25 * a;
          ctx.fillStyle = hs.isHq ? 'rgba(165, 235, 255, 0.72)' : 'rgba(118, 255, 210, 0.70)';
          ctx.fill('evenodd');
          ctx.shadowBlur = 0;
          ctx.strokeStyle = 'rgba(240, 255, 252, 0.98)';
          ctx.lineWidth = 1.8;
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
      HQ.screenX = hp.x; HQ.screenY = hp.y; window.VP_GLOBE_HQ = HQ; window.VP_GLOBE_CANVAS = canvas;
      var isIndiaHovered = (hoveredShape === HQ.shape) || (hoveredShape && hoveredShape.name === 'India') || (hoveredCountry && hoveredCountry.isHq);
      if (isIndiaHovered) {
        badge('★ India HQ — Click to view Manufacturing Facilities ➔', hp.x, hp.y, 'india-active');
      } else {
        badge(HQ.shortName, hp.x, hp.y, 'hq');
      }
    }

    // Badge for hovered export partner nation
    if (hoveredCountry && !hoveredCountry.isHq) {
      var cp = toScreen(hoveredCountry.v3, 0.08);
      if (cp.z > 0) {
        badge('✦ ' + (hoveredCountry.shortName || hoveredCountry.name) + ' (Export Partner)', cp.x, cp.y, 'partner');
      }
    }

    if (isGlobeActive) {
      animFrameId = requestAnimationFrame(drawGlobe);
    }
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
    targetRotX = 0; // Pure central vertical axis: North pole top, South pole bottom
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
      targetRotX = 0; // Maintain vertical central axis without tilt
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
      var isOverIndia = (hoveredShape && (hoveredShape.isHq || hoveredShape.name === 'India')) || 
                        (hoveredCountry && hoveredCountry.isHq) ||
                        (HQ.visible && Math.hypot(HQ.screenX - p.x, HQ.screenY - p.y) < 28) ||
                        (HQ.badgeRect && p.x >= HQ.badgeRect.x && p.x <= HQ.badgeRect.x + HQ.badgeRect.w && p.y >= HQ.badgeRect.y && p.y <= HQ.badgeRect.y + HQ.badgeRect.h);
      if (!isDragging) canvas.style.cursor = (hoveredCountry || isOverIndia) ? 'pointer' : 'grab';
      syncPills();
    });
    canvas.addEventListener('mouseleave', function () {
      pointer.inside = false;
      if (!pointer.fromPill) { hoveredShape = null; hoveredCountry = null; }
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

      // 1. Check if clicked near the India HQ pin marker or its hover badge
      if (HQ.visible) {
        if (Math.hypot(HQ.screenX - p.x, HQ.screenY - p.y) < 28) {
          window.location.href = 'manufacturing.html#manufacturing-footprint';
          return;
        }
        if (HQ.badgeRect && p.x >= HQ.badgeRect.x && p.x <= HQ.badgeRect.x + HQ.badgeRect.w &&
            p.y >= HQ.badgeRect.y && p.y <= HQ.badgeRect.y + HQ.badgeRect.h) {
          window.location.href = 'manufacturing.html#manufacturing-footprint';
          return;
        }
      }

      // Resolve the country at the click point (also works for touch, which has no hover)
      var ll = screenToLatLng(p.x, p.y, rotX + tiltX, rotY + tiltY);
      var hit = null;
      var hitShape = null;
      if (ll) {
        for (var i = 0; i < world.length; i++) {
          if (shapeContains(world[i], ll.lat, ll.lng)) { 
            hit = world[i].partner; 
            hitShape = world[i];
            break; 
          }
        }
      }
      if (!hit) {
        for (var s = 0; s < smallStates.length; s++) {
          var sc = smallStates[s];
          if (sc.visible && Math.hypot(sc.screenX - p.x, sc.screenY - p.y) < 16) { hit = sc; break; }
        }
      }

      // 2. If clicked on India map / outline polygon -> Navigate directly to Manufacturing Footprint map!
      if ((hitShape && (hitShape.isHq || (hitShape.name && hitShape.name.toLowerCase() === 'india'))) ||
          (hit && (hit.isHq || (hit.name && hit.name.toLowerCase().indexOf('india') !== -1)))) {
        window.location.href = 'manufacturing.html#manufacturing-footprint';
        return;
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


  
  // Smooth Scroll Scrub: Globe stays 100% visible in Hero and ONLY fades out once entering Products section
  function setupScrollTransition() {
    var globeContainer = document.getElementById('vpGlobeCanvasContainer');
    var productsEl = document.getElementById('products');
    var starBg = document.querySelector('.vp-hero-bg-star');
    if (!globeContainer) return;

    function onScrollScrub() {
      var vh = window.innerHeight;
      var progress = 0;

      if (productsEl) {
        var rect = productsEl.getBoundingClientRect();
        // While user is in Hero, rect.top is large (e.g. > vh * 0.5) -> progress is 0 (100% visible)
        // Fade out ONLY once the user is in the products section (from vh*0.45 down to 50px)
        var fadeStart = vh * 0.45;
        var fadeEnd = 50;
        if (rect.top <= fadeStart) {
          progress = Math.min(1, Math.max(0, (fadeStart - rect.top) / (fadeStart - fadeEnd)));
        } else {
          progress = 0; // 100% visible in Hero
        }
      } else {
        var scrollY = window.pageYOffset || document.documentElement.scrollTop;
        var threshold = vh * 0.85;
        if (scrollY > threshold) {
          progress = Math.min(1, (scrollY - threshold) / (vh * 0.35));
        }
      }

      var brightness = Math.max(0.1, 1 - progress * 0.9);
      var opacity = Math.max(0, 1 - progress);
      var translateY = progress * 40;

      globeContainer.style.filter = 'brightness(' + brightness.toFixed(3) + ')';
      globeContainer.style.opacity = opacity.toFixed(3);
      globeContainer.style.transform = 'translate3d(0, ' + translateY.toFixed(1) + 'px, 0)';

      if (starBg) {
        starBg.style.opacity = (0.22 * (1 - progress)).toFixed(3);
        starBg.style.transform = 'scale(' + (1 + progress * 0.08).toFixed(3) + ')';
      }
    }

    window.addEventListener('scroll', onScrollScrub, { passive: true });
    onScrollScrub();
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
      btn.setAttribute('title', c.isHq ? 'Vasudha Pharma Global HQ — Click to view Manufacturing Facilities in India' : 'Export Partner: ' + c.name);

      btn.innerHTML = [
        '<span class="vp-country-pill-dot"></span>',
        '<span class="vp-country-pill-name">' + (c.isHq ? '★ ' : '') + c.name + '</span>'
      ].join('');

      btn.addEventListener('mouseenter', function () { pointer.fromPill = true; hoveredCountry = c; });
      btn.addEventListener('mouseleave', function () { pointer.fromPill = false; hoveredCountry = null; hoveredShape = null; });
      btn.addEventListener('focus', function () { pointer.fromPill = true; hoveredCountry = c; });
      btn.addEventListener('blur', function () { pointer.fromPill = false; hoveredCountry = null; hoveredShape = null; });
      btn.addEventListener('click', function () {
        if (c.isHq) {
          window.location.href = 'manufacturing.html#manufacturing-footprint';
          return;
        }
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

    // Connect KPI Card "90+ Countries Served" to scroll to roster if present
    var kpiCard = document.querySelector('a[href*="about-us.html#international-presence"].vp-globe-kpi-card');
    if (kpiCard) {
      kpiCard.addEventListener('click', function (e) {
        var roster = document.getElementById('vpGlobeCountriesRoster');
        if (roster) {
          e.preventDefault();
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
    canvas.setAttribute('aria-label', 'Interactive 3D globe of Vasudha Pharma export markets.');
    if (reduceMotion) { autoRotate = false; setOrbitButton(false); }
    init3DData();
    setupEvents();
    setupCountriesRoster();
    setupScrollTransition();

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
