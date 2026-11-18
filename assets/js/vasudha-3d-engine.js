/**
 * ============================================================================
 * VASUDHA PHARMA CHEM LIMITED | Official 3D WebGL Simulation & Scroll Engine
 * Adapted from Reference 3D Choreography into Vasudha Brand Theme (#1DB88A / Emerald)
 * Features:
 * - Big 3D Interactive World Globe on the RIGHT with 90+ Elevated Partner Nations
 * - 3D Extruded Topography & Bevel Walls for Export Partner Countries & India HQ
 * - Dynamic Great-Circle Flight Arcs & Regulatory Beacons from Visakhapatnam HQ
 * - Interactive Mouse Hover & Tooltip Displaying Regulatory Accreditations
 * - Realistic Bi-Color Pharmaceutical Capsule (Vasudha Emerald & Clinical White)
 * - Micro-Bead Active Particle Cloud (120+ Floating Spheres)
 * - cGMP Container & Threaded Cap Elevation Kinematics
 * - Full-Page Scroll Choreography & Mouse Parallax
 * ============================================================================
 */

(function (window) {
    'use strict';

    // Three.js Core Objects
    let scene, camera, renderer;
    let canvasContainer;

    // 3D Geometry Groups
    let globeGroup, globeMesh, globeAtmosphere;
    let flightArcsGroup, beaconGroup, elevatedPartnersGroup;
    let bottleGroup, bottleMesh, capMesh;
    let mainCapsuleGroup;
    const pillHalves = [];
    const microBeads = [];
    const flightBeads = [];
    const pulsingRings = [];
    const elevatedPartnerMeshes = [];

    // State Variables
    let scrollProgress = 0;
    let mouseX = 0, mouseY = 0;
    let isDraggingGlobe = false;
    let lastMouseX = 0, lastMouseY = 0;
    let globeVelX = 0.002, globeVelY = 0;
    let hoveredPartner = null;

    // Kinematics Targets: 3D GLOBE POSITIONED ON THE RIGHT SIDE
    let targetCamPos = { x: 0.0, y: 1.5, z: 11.0 };
    let targetCamRot = { x: 0, y: 0, z: 0 };
    let targetGlobePos = { x: 2.3, y: -0.15, z: 0 };
    let targetGlobeScale = 0.95;
    let targetBottlePos = { x: 0, y: -14.0, z: -2.0 };
    let targetBottleScale = 1.0;
    let targetCapOffsetY = 0;
    let targetExplosion = 0;
    let targetBottleRotY = 0;

    // India Operations HQ (Visakhapatnam, Andhra Pradesh)
    const INDIA_HQ = {
        name: "India (Global Operations HQ)",
        shortName: "Vasudha India HQ",
        lat: 17.68,
        lng: 83.21,
        isHq: true,
        filings: "Global Manufacturing Operations HQ • USFDA, EDQM, WHO-GMP, PMDA",
        region: "asia-pacific"
    };

    // 90+ Partner Nations & Regulatory Accreditations
    const COUNTRIES_90 = [
        INDIA_HQ,
        // North America
        { name: "United States", shortName: "United States", lat: 37.09, lng: -95.71, filings: "USFDA Audited Facility • Active US DMF Filings", region: "north-america" },
        { name: "Canada", shortName: "Canada", lat: 56.13, lng: -106.35, filings: "Health Canada Certified • Active CEP Portfolio", region: "north-america" },
        { name: "Mexico", shortName: "Mexico", lat: 23.63, lng: -102.55, filings: "COFEPRIS Approved • Commercial cGMP APIs", region: "north-america" },

        // Latin America & Caribbean
        { name: "Brazil", shortName: "Brazil", lat: -14.23, lng: -51.92, filings: "ANVISA Inspected & Certified", region: "latin-america" },
        { name: "Argentina", shortName: "Argentina", lat: -38.41, lng: -63.61, filings: "ANMAT Compliant API Partner", region: "latin-america" },
        { name: "Colombia", shortName: "Colombia", lat: 4.57, lng: -74.29, filings: "INVIMA Authorized Regulatory Conduit", region: "latin-america" },
        { name: "Chile", shortName: "Chile", lat: -35.67, lng: -71.54, filings: "ISP Chile Certified", region: "latin-america" },
        { name: "Peru", shortName: "Peru", lat: -9.19, lng: -75.01, filings: "DIGEMID Approved", region: "latin-america" },
        { name: "Ecuador", shortName: "Ecuador", lat: -1.83, lng: -78.18, filings: "ARCSA Regulated APIs", region: "latin-america" },
        { name: "Uruguay", shortName: "Uruguay", lat: -32.52, lng: -55.76, filings: "MSP Authorized Import Hub", region: "latin-america" },
        { name: "Paraguay", shortName: "Paraguay", lat: -23.44, lng: -58.44, filings: "DINAVISA Registered", region: "latin-america" },
        { name: "Bolivia", shortName: "Bolivia", lat: -16.29, lng: -63.58, filings: "AGEMED Compliant", region: "latin-america" },
        { name: "Costa Rica", shortName: "Costa Rica", lat: 9.74, lng: -83.75, filings: "Ministerio de Salud Approved", region: "latin-america" },
        { name: "Panama", shortName: "Panama", lat: 8.53, lng: -80.78, filings: "MINSA Certified Logistics Conduit", region: "latin-america" },
        { name: "Guatemala", shortName: "Guatemala", lat: 15.78, lng: -90.23, filings: "DRCES Registered", region: "latin-america" },
        { name: "Dominican Republic", shortName: "Dominican Rep.", lat: 18.73, lng: -70.16, filings: "DIGEMAPS Approved", region: "latin-america" },
        { name: "Puerto Rico", shortName: "Puerto Rico", lat: 18.22, lng: -66.59, filings: "USFDA Regulated Territory", region: "latin-america" },
        { name: "Venezuela", shortName: "Venezuela", lat: 6.42, lng: -66.58, filings: "MPPS Certified", region: "latin-america" },
        { name: "El Salvador", shortName: "El Salvador", lat: 13.79, lng: -88.9, filings: "DNM Authorized", region: "latin-america" },
        { name: "Honduras", shortName: "Honduras", lat: 15.2, lng: -86.24, filings: "ARSA Compliant", region: "latin-america" },
        { name: "Nicaragua", shortName: "Nicaragua", lat: 12.87, lng: -85.21, filings: "MINSA Registered", region: "latin-america" },

        // Europe & United Kingdom
        { name: "Germany", shortName: "Germany", lat: 51.16, lng: 10.45, filings: "BfArM Validated • EDQM CEP Registered", region: "europe" },
        { name: "France", shortName: "France", lat: 46.22, lng: 2.21, filings: "ANSM Approved • European Pharmacopoeia (EP)", region: "europe" },
        { name: "United Kingdom", shortName: "United Kingdom", lat: 55.37, lng: -3.43, filings: "UK MHRA Compliant • Active Supply Conduits", region: "europe" },
        { name: "Italy", shortName: "Italy", lat: 41.87, lng: 12.56, filings: "AIFA Certified • EDQM Registered", region: "europe" },
        { name: "Spain", shortName: "Spain", lat: 40.46, lng: -3.74, filings: "AEMPS Authorized cGMP Consignee", region: "europe" },
        { name: "Netherlands", shortName: "Netherlands", lat: 52.13, lng: 5.29, filings: "IGJ & EDQM Approved API Supply", region: "europe" },
        { name: "Switzerland", shortName: "Switzerland", lat: 46.81, lng: 8.22, filings: "Swissmedic Validated Partner", region: "europe" },
        { name: "Belgium", shortName: "Belgium", lat: 50.5, lng: 4.46, filings: "FAMHP Certified European Hub", region: "europe" },
        { name: "Poland", shortName: "Poland", lat: 51.91, lng: 19.14, filings: "URPL EU GMP Certified", region: "europe" },
        { name: "Sweden", shortName: "Sweden", lat: 60.12, lng: 18.64, filings: "MPA Sweden Registered", region: "europe" },
        { name: "Austria", shortName: "Austria", lat: 47.51, lng: 14.55, filings: "AGES Certified Partner", region: "europe" },
        { name: "Ireland", shortName: "Ireland", lat: 53.14, lng: -7.69, filings: "HPRA Approved • Strategic EU Hub", region: "europe" },
        { name: "Portugal", shortName: "Portugal", lat: 39.39, lng: -8.22, filings: "INFARMED Authorized", region: "europe" },
        { name: "Greece", shortName: "Greece", lat: 39.07, lng: 21.82, filings: "EOF Certified", region: "europe" },
        { name: "Czech Republic", shortName: "Czechia", lat: 49.81, lng: 15.47, filings: "SUKL Compliant", region: "europe" },
        { name: "Hungary", shortName: "Hungary", lat: 47.16, lng: 19.5, filings: "OGYEI Registered", region: "europe" },
        { name: "Romania", shortName: "Romania", lat: 45.94, lng: 24.96, filings: "ANMDMR Approved", region: "europe" },
        { name: "Bulgaria", shortName: "Bulgaria", lat: 42.73, lng: 25.48, filings: "BDA Authorized", region: "europe" },
        { name: "Croatia", shortName: "Croatia", lat: 45.1, lng: 15.2, filings: "HALMED Approved", region: "europe" },
        { name: "Finland", shortName: "Finland", lat: 61.92, lng: 25.74, filings: "FIMEA Validated", region: "europe" },

        // Asia-Pacific
        { name: "Japan", shortName: "Japan", lat: 36.20, lng: 138.25, filings: "PMDA Tokyo Certified • Japanese Pharmacopoeia (JP)", region: "asia-pacific" },
        { name: "South Korea", shortName: "South Korea", lat: 35.90, lng: 127.76, filings: "MFDS Korea Audited & Approved", region: "asia-pacific" },
        { name: "Australia", shortName: "Australia", lat: -25.27, lng: 133.77, filings: "TGA Australia Certified Partner", region: "asia-pacific" },
        { name: "New Zealand", shortName: "New Zealand", lat: -40.9, lng: 174.88, filings: "Medsafe Certified", region: "asia-pacific" },
        { name: "Singapore", shortName: "Singapore", lat: 1.35, lng: 103.81, filings: "HSA Singapore Approved Hub", region: "asia-pacific" },
        { name: "Vietnam", shortName: "Vietnam", lat: 14.05, lng: 108.27, filings: "DAV Vietnam Approved Importer", region: "asia-pacific" },
        { name: "Thailand", shortName: "Thailand", lat: 15.87, lng: 100.99, filings: "Thai FDA Certified", region: "asia-pacific" },
        { name: "Malaysia", shortName: "Malaysia", lat: 4.21, lng: 101.97, filings: "NPRA Malaysia Accredited", region: "asia-pacific" },
        { name: "Indonesia", shortName: "Indonesia", lat: -0.78, lng: 113.92, filings: "BPOM Indonesia Authorized", region: "asia-pacific" },
        { name: "Philippines", shortName: "Philippines", lat: 12.87, lng: 121.77, filings: "FDA Philippines Approved", region: "asia-pacific" },
        { name: "Taiwan", shortName: "Taiwan", lat: 23.69, lng: 120.96, filings: "TFDA Authorized", region: "asia-pacific" },
        { name: "Sri Lanka", shortName: "Sri Lanka", lat: 7.87, lng: 80.77, filings: "NMRA Certified", region: "asia-pacific" },
        { name: "Bangladesh", shortName: "Bangladesh", lat: 23.68, lng: 90.35, filings: "DGDA Authorized", region: "asia-pacific" },
        { name: "Pakistan", shortName: "Pakistan", lat: 30.38, lng: 69.35, filings: "DRAP Approved", region: "asia-pacific" },
        { name: "Uzbekistan", shortName: "Uzbekistan", lat: 41.37, lng: 64.58, filings: "MoH Uzbekistan Certified", region: "asia-pacific" },

        // Middle East & Africa
        { name: "Saudi Arabia", shortName: "Saudi Arabia", lat: 23.88, lng: 45.07, filings: "Saudi FDA (SFDA) Certified Partner", region: "middle-east" },
        { name: "United Arab Emirates", shortName: "UAE", lat: 23.42, lng: 53.84, filings: "MOHAP UAE Registered Distribution", region: "middle-east" },
        { name: "Israel", shortName: "Israel", lat: 31.04, lng: 34.85, filings: "MOH Israel Certified", region: "middle-east" },
        { name: "Jordan", shortName: "Jordan", lat: 30.58, lng: 36.23, filings: "JFDA Approved", region: "middle-east" },
        { name: "Kuwait", shortName: "Kuwait", lat: 29.31, lng: 47.48, filings: "MOH Kuwait Certified", region: "middle-east" },
        { name: "Oman", shortName: "Oman", lat: 21.51, lng: 55.92, filings: "MOH Oman Approved", region: "middle-east" },
        { name: "Qatar", shortName: "Qatar", lat: 25.35, lng: 51.18, filings: "MOPH Qatar Validated", region: "middle-east" },
        { name: "Egypt", shortName: "Egypt", lat: 26.82, lng: 30.8, filings: "EDA Egypt Certified Partner", region: "africa" },
        { name: "South Africa", shortName: "South Africa", lat: -30.55, lng: 22.93, filings: "SAHPRA South Africa Certified", region: "africa" },
        { name: "Morocco", shortName: "Morocco", lat: 31.79, lng: -7.09, filings: "DMP Morocco Approved", region: "africa" },
        { name: "Algeria", shortName: "Algeria", lat: 28.03, lng: 1.65, filings: "ANPP Algeria Authorized", region: "africa" },
        { name: "Nigeria", shortName: "Nigeria", lat: 9.08, lng: 8.67, filings: "NAFDAC Nigeria Certified", region: "africa" },
        { name: "Kenya", shortName: "Kenya", lat: -1.29, lng: 36.82, filings: "PPB Kenya Validated", region: "africa" },
        { name: "Ghana", shortName: "Ghana", lat: 7.94, lng: -1.02, filings: "FDA Ghana Authorized", region: "africa" },
        { name: "Tunisia", shortName: "Tunisia", lat: 33.88, lng: 9.53, filings: "DPM Tunisia Approved", region: "africa" }
    ];

    // Name Normalization Mapping for window.VP_GLOBE_WORLD
    const NAME_ALIAS = {
        'united states': 'united states of america',
        'czech republic': 'czechia',
        'dominican republic': 'dominican rep.',
        'uae': 'united arab emirates',
        'russian federation': 'russia',
        'türkiye': 'turkey',
        'turkiye': 'turkey',
        'serbia / montenegro': 'serbia',
        'ivory coast': "côte d'ivoire",
        'dr congo': 'dem. rep. congo',
        'republic of the congo': 'congo',
        'bosnia and herzegovina': 'bosnia and herz.',
        'north macedonia': 'macedonia'
    };

    // Build Fast Partner Lookup Dictionary
    const PARTNER_LOOKUP = {};
    COUNTRIES_90.forEach(c => {
        const key = c.name.toLowerCase().trim();
        PARTNER_LOOKUP[key] = c;
        if (NAME_ALIAS[key]) {
            PARTNER_LOOKUP[NAME_ALIAS[key]] = c;
        }
        if (c.shortName) {
            PARTNER_LOOKUP[c.shortName.toLowerCase().trim()] = c;
        }
    });

    /**
     * Converts Latitude and Longitude to 3D Cartesian Coordinates on a Sphere
     */
    function latLngToVector3(lat, lng, radius) {
        const phi = (90 - lat) * (Math.PI / 180);
        const theta = (lng + 180) * (Math.PI / 180);
        return new THREE.Vector3(
            -radius * Math.sin(phi) * Math.cos(theta),
            radius * Math.cos(phi),
            radius * Math.sin(phi) * Math.sin(theta)
        );
    }

    /**
     * Initializes the Three.js WebGL Scene
     */
    function init(containerId) {
        canvasContainer = document.getElementById(containerId);
        if (!canvasContainer) {
            canvasContainer = document.createElement('div');
            canvasContainer.id = containerId || 'canvas-container';
            document.body.insertBefore(canvasContainer, document.body.firstChild);
        }

        // Apply fixed background styling
        canvasContainer.style.position = 'fixed';
        canvasContainer.style.inset = '0';
        canvasContainer.style.width = '100vw';
        canvasContainer.style.height = '100vh';
        canvasContainer.style.zIndex = '0';
        canvasContainer.style.pointerEvents = 'none';

        const width = window.innerWidth;
        const height = window.innerHeight;

        // 1. Scene Setup
        scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0x041a13, 0.025);

        // 2. Camera Configuration: Framed for Right-Hand Globe & Left-Hand KPI Cards
        camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
        camera.position.set(targetCamPos.x, targetCamPos.y, targetCamPos.z);

        // 3. WebGL Renderer with Alpha transparency & Anti-aliasing
        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.25;
        canvasContainer.appendChild(renderer.domElement);

        // 4. Lighting Rig tailored for Vasudha Emerald & Cleanroom White
        const ambientLight = new THREE.AmbientLight(0xffffff, 1.1);
        scene.add(ambientLight);

        const keyLight = new THREE.DirectionalLight(0xffffff, 2.5);
        keyLight.position.set(6, 12, 8);
        scene.add(keyLight);

        const emeraldRimLight = new THREE.DirectionalLight(0x1DB88A, 1.6);
        emeraldRimLight.position.set(-6, -4, -6);
        scene.add(emeraldRimLight);

        const pointLight = new THREE.PointLight(0x34d399, 1.4, 20);
        pointLight.position.set(2, 4, 3);
        scene.add(pointLight);

        // 5. Construct 3D Models
        // buildBigGlobe(); // Dedicated authentic 3D globe is rendered by supply-globe.js
        buildPharmaContainer();
        buildExplodingCapsule();
        buildMicroBeadCloud();
        buildOrbitingSatellites();

        // 6. Event Listeners
        window.addEventListener('resize', onWindowResize);
        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('scroll', onScroll, { passive: true });

        // Bind interactive drag and hover on globe stage
        bindGlobeInteractions();

        // Start Animation Loop
        animate(0);
    }

    /**
     * Constructs the Big 3D World Globe with Elevated Partner Nations
     */
    function buildBigGlobe() {
        globeGroup = new THREE.Group();
        globeGroup.position.set(targetGlobePos.x, targetGlobePos.y, targetGlobePos.z);
        scene.add(globeGroup);

        const radius = 2.65;

        // 1. Base Ocean Sphere (Deep Pharmaceutical Emerald Glass)
        const sphereGeo = new THREE.SphereGeometry(radius, 48, 36);
        const sphereMat = new THREE.MeshStandardMaterial({
            color: 0x05261d,
            roughness: 0.22,
            metalness: 0.18,
            transparent: true,
            opacity: 0.88
        });
        globeMesh = new THREE.Mesh(sphereGeo, sphereMat);
        globeGroup.add(globeMesh);

        // 2. Subtle Latitude & Longitude Wireframe Grid
        const wireGeo = new THREE.SphereGeometry(radius * 1.001, 36, 18);
        const wireMat = new THREE.MeshBasicMaterial({
            color: 0x1DB88A,
            wireframe: true,
            transparent: true,
            opacity: 0.16
        });
        const wireMesh = new THREE.Mesh(wireGeo, wireMat);
        globeGroup.add(wireMesh);

        // 3. Atmospheric Outer Glow Shell
        const atmoGeo = new THREE.SphereGeometry(radius * 1.06, 32, 20);
        const atmoMat = new THREE.MeshBasicMaterial({
            color: 0x1DB88A,
            transparent: true,
            opacity: 0.08,
            side: THREE.BackSide
        });
        globeAtmosphere = new THREE.Mesh(atmoGeo, atmoMat);
        globeGroup.add(globeAtmosphere);

        // 4. Elevated Partner Group for 3D relief
        elevatedPartnersGroup = new THREE.Group();
        globeGroup.add(elevatedPartnersGroup);

        // Shared materials
        const nonPartnerLineMat = new THREE.LineBasicMaterial({
            color: 0x0c3d31,
            transparent: true,
            opacity: 0.35,
            linewidth: 1
        });

        const partnerLineMat = new THREE.LineBasicMaterial({
            color: 0x4FE3B0,
            transparent: true,
            opacity: 0.95,
            linewidth: 2
        });

        const hqLineMat = new THREE.LineBasicMaterial({
            color: 0x60efff,
            transparent: true,
            opacity: 1.0,
            linewidth: 2.2
        });

        const wallMat = new THREE.MeshStandardMaterial({
            color: 0x074e3c,
            roughness: 0.35,
            metalness: 0.25,
            side: THREE.DoubleSide
        });

        const hqWallMat = new THREE.MeshStandardMaterial({
            color: 0x0088aa,
            roughness: 0.3,
            metalness: 0.3,
            side: THREE.DoubleSide
        });

        const plateauMat = new THREE.MeshBasicMaterial({
            color: 0x1DB88A,
            transparent: true,
            opacity: 0.38,
            side: THREE.DoubleSide
        });

        const hqPlateauMat = new THREE.MeshBasicMaterial({
            color: 0x38ef7d,
            transparent: true,
            opacity: 0.65,
            side: THREE.DoubleSide
        });

        // 5. Continents from window.VP_GLOBE_WORLD with Elevated Partner Topography
        if (window.VP_GLOBE_WORLD && Array.isArray(window.VP_GLOBE_WORLD)) {
            window.VP_GLOBE_WORLD.forEach(country => {
                if (!country.p || !Array.isArray(country.p)) return;

                const rawName = (country.n || '').toLowerCase().trim();
                const normName = NAME_ALIAS[rawName] || rawName;
                const partnerData = PARTNER_LOOKUP[normName] || PARTNER_LOOKUP[rawName];
                const isPartner = !!partnerData;
                const isHq = partnerData && partnerData.isHq;

                // Elevated radius: India HQ is highest, partner countries elevated 3.6% above ocean
                const elevatedRadius = isHq ? (radius * 1.048) : (isPartner ? (radius * 1.036) : (radius * 1.002));

                country.p.forEach(poly => {
                    const coords = poly[0];
                    if (!coords || coords.length < 4) return;

                    const basePoints = [];
                    const topPoints = [];
                    const wallVertices = [];
                    let centerLat = 0, centerLng = 0;
                    const numPts = coords.length / 2;

                    for (let i = 0; i < coords.length; i += 2) {
                        const lng = coords[i];
                        const lat = coords[i + 1];
                        centerLng += lng;
                        centerLat += lat;

                        const pTop = latLngToVector3(lat, lng, elevatedRadius);
                        topPoints.push(pTop);

                        if (isPartner) {
                            const pBase = latLngToVector3(lat, lng, radius * 1.002);
                            basePoints.push(pBase);
                        }
                    }

                    centerLng /= numPts;
                    centerLat /= numPts;

                    // A. Top Contour Line
                    const lineGeo = new THREE.BufferGeometry().setFromPoints(topPoints);
                    const lineMat = isHq ? hqLineMat : (isPartner ? partnerLineMat : nonPartnerLineMat);
                    const line = new THREE.Line(lineGeo, lineMat);
                    globeGroup.add(line);

                    // B. For Partner Countries: Create 3D Extruded Bevel Walls & Illuminated Plateau Cap
                    if (isPartner) {
                        // Extruded vertical walls connecting base to top
                        for (let j = 0; j < topPoints.length - 1; j++) {
                            const b1 = basePoints[j];
                            const t1 = topPoints[j];
                            const b2 = basePoints[j + 1];
                            const t2 = topPoints[j + 1];

                            // Triangle 1: b1, t1, t2
                            wallVertices.push(b1.x, b1.y, b1.z);
                            wallVertices.push(t1.x, t1.y, t1.z);
                            wallVertices.push(t2.x, t2.y, t2.z);

                            // Triangle 2: b1, t2, b2
                            wallVertices.push(b1.x, b1.y, b1.z);
                            wallVertices.push(t2.x, t2.y, t2.z);
                            wallVertices.push(b2.x, b2.y, b2.z);
                        }

                        const wallGeo = new THREE.BufferGeometry();
                        wallGeo.setAttribute('position', new THREE.Float32BufferAttribute(wallVertices, 3));
                        wallGeo.computeVertexNormals();
                        const wallMesh = new THREE.Mesh(wallGeo, isHq ? hqWallMat : wallMat);
                        elevatedPartnersGroup.add(wallMesh);

                        // Elevated plateau surface cap (triangle fan from centroid)
                        const centerTop = latLngToVector3(centerLat, centerLng, elevatedRadius);
                        const capVertices = [];
                        for (let j = 0; j < topPoints.length - 1; j++) {
                            const t1 = topPoints[j];
                            const t2 = topPoints[j + 1];
                            capVertices.push(centerTop.x, centerTop.y, centerTop.z);
                            capVertices.push(t1.x, t1.y, t1.z);
                            capVertices.push(t2.x, t2.y, t2.z);
                        }
                        const capGeo = new THREE.BufferGeometry();
                        capGeo.setAttribute('position', new THREE.Float32BufferAttribute(capVertices, 3));
                        capGeo.computeVertexNormals();
                        const capMesh = new THREE.Mesh(capGeo, isHq ? hqPlateauMat : plateauMat);
                        elevatedPartnersGroup.add(capMesh);

                        // Save mesh reference for interactive raycast
                        elevatedPartnerMeshes.push({
                            mesh: capMesh,
                            name: partnerData.name,
                            partner: partnerData,
                            center: centerTop
                        });
                    }
                });
            });
        }

        // 6. Flight Arcs & Beacons Group
        flightArcsGroup = new THREE.Group();
        globeGroup.add(flightArcsGroup);

        beaconGroup = new THREE.Group();
        globeGroup.add(beaconGroup);

        // India HQ Golden & Cyan Spire
        const hqPos = latLngToVector3(INDIA_HQ.lat, INDIA_HQ.lng, radius * 1.05);
        createHqBeaconSpire(hqPos);

        // Create Beacons & Great-Circle 3D Supply Arcs to Partner Nations
        COUNTRIES_90.forEach((partner, idx) => {
            if (partner.isHq) return;
            const destPos = latLngToVector3(partner.lat, partner.lng, radius * 1.036);
            createPartnerBeacon(destPos, partner);

            // Create supply routes for major international hubs
            if (idx % 2 === 0 || partner.region === 'north-america' || partner.region === 'europe') {
                createFlightArc(hqPos, destPos, radius, idx);
            }
        });
    }

    /**
     * Constructs India HQ 3D Spire with Pulsing Rings
     */
    function createHqBeaconSpire(pos) {
        const spireGeo = new THREE.CylinderGeometry(0.012, 0.045, 0.32, 12);
        const spireMat = new THREE.MeshStandardMaterial({
            color: 0x60efff,
            emissive: 0x00d2ff,
            emissiveIntensity: 0.8,
            roughness: 0.2
        });
        const spire = new THREE.Mesh(spireGeo, spireMat);
        spire.position.copy(pos);
        spire.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), pos.clone().normalize());
        beaconGroup.add(spire);

        // Concentric Expanding Pulse Rings
        for (let r = 0; r < 2; r++) {
            const ringGeo = new THREE.RingGeometry(0.05, 0.14, 24);
            const ringMat = new THREE.MeshBasicMaterial({
                color: 0x38ef7d,
                side: THREE.DoubleSide,
                transparent: true,
                opacity: 0.85
            });
            const ring = new THREE.Mesh(ringGeo, ringMat);
            ring.position.copy(pos);
            ring.lookAt(new THREE.Vector3(0, 0, 0));
            beaconGroup.add(ring);
            pulsingRings.push({ mesh: ring, offset: r * Math.PI, baseScale: 1.0 });
        }
    }

    /**
     * Constructs 3D Beacon for a Partner Nation
     */
    function createPartnerBeacon(pos, partner) {
        const pinGeo = new THREE.SphereGeometry(0.026, 10, 10);
        const pinMat = new THREE.MeshBasicMaterial({
            color: partner.region === 'north-america' ? 0x5CF2C3 : 0x1DB88A
        });
        const pin = new THREE.Mesh(pinGeo, pinMat);
        pin.position.copy(pos);
        beaconGroup.add(pin);

        const ringGeo = new THREE.RingGeometry(0.035, 0.08, 16);
        const ringMat = new THREE.MeshBasicMaterial({
            color: 0x4FE3B0,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.65
        });
        const ring = new THREE.Mesh(ringGeo, ringMat);
        ring.position.copy(pos);
        ring.lookAt(new THREE.Vector3(0, 0, 0));
        beaconGroup.add(ring);
    }

    /**
     * Great-Circle 3D Parabolic Arc with Kinetic Traveling Light Packets
     */
    function createFlightArc(startVec, endVec, radius, index) {
        const midVec = new THREE.Vector3().addVectors(startVec, endVec).multiplyScalar(0.5);
        const dist = startVec.distanceTo(endVec);
        const elevation = radius + Math.min(dist * 0.42, 1.45);
        midVec.setLength(elevation);

        const curve = new THREE.QuadraticBezierCurve3(startVec, midVec, endVec);
        const curvePoints = curve.getPoints(42);
        const curveGeo = new THREE.BufferGeometry().setFromPoints(curvePoints);

        const curveMat = new THREE.LineBasicMaterial({
            color: 0x34d399,
            transparent: true,
            opacity: 0.55
        });
        const arcLine = new THREE.Line(curveGeo, curveMat);
        flightArcsGroup.add(arcLine);

        // Traveling Light Packet
        const beadGeo = new THREE.SphereGeometry(0.045, 8, 8);
        const beadMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
        const beadMesh = new THREE.Mesh(beadGeo, beadMat);
        flightArcsGroup.add(beadMesh);

        flightBeads.push({
            mesh: beadMesh,
            curve: curve,
            speed: 0.0035 + (index % 4) * 0.0012,
            progress: (index * 0.12) % 1.0
        });
    }

    /**
     * Constructs cGMP Manufacturing Container & Threaded Cap
     */
    function buildPharmaContainer() {
        bottleGroup = new THREE.Group();
        bottleGroup.position.set(targetBottlePos.x, targetBottlePos.y, targetBottlePos.z);
        scene.add(bottleGroup);

        // A. Outer High-Tech Wireframe Cage (Distinctly visible high-contrast Emerald / Mint)
        const wireframeMat = new THREE.MeshBasicMaterial({
            color: 0x4FE3B0,
            wireframe: true,
            transparent: true,
            opacity: 0.65
        });

        // B. Inner Translucent Bio-Pharma Glass Core
        const glassMat = new THREE.MeshStandardMaterial({
            color: 0x064e3b,
            roughness: 0.15,
            metalness: 0.25,
            transparent: true,
            opacity: 0.45,
            side: THREE.DoubleSide
        });

        // Bottle Body Cylinder
        const bottleGeo = new THREE.CylinderGeometry(1.7, 1.7, 3.8, 36, 20);
        bottleMesh = new THREE.Mesh(bottleGeo, glassMat);
        bottleMesh.position.y = -0.5;
        bottleGroup.add(bottleMesh);

        // Wireframe Overlay on Cylinder Body
        const bottleWire = new THREE.Mesh(bottleGeo, wireframeMat);
        bottleWire.position.y = -0.5;
        bottleGroup.add(bottleWire);

        // Threaded Cleanroom White & Emerald Cap
        const capGeo = new THREE.CylinderGeometry(1.8, 1.8, 0.85, 48, 8);
        const capMat = new THREE.MeshStandardMaterial({
            color: 0xf8fafc,
            roughness: 0.2,
            metalness: 0.15
        });
        capMesh = new THREE.Mesh(capGeo, capMat);
        capMesh.position.y = 1.9;
        bottleGroup.add(capMesh);

        // Cap Wireframe Detail Ring
        const capWireMat = new THREE.MeshBasicMaterial({
            color: 0x34d399,
            wireframe: true,
            transparent: true,
            opacity: 0.55
        });
        const capWire = new THREE.Mesh(capGeo, capWireMat);
        capWire.position.y = 1.9;
        bottleGroup.add(capWire);
    }

    function buildExplodingCapsule() {
        mainCapsuleGroup = new THREE.Group();
        bottleGroup.add(mainCapsuleGroup);
        mainCapsuleGroup.position.set(0, 0.4, 0);

        function createCapsuleHalf(colorHex) {
            const group = new THREE.Group();
            const radius = 0.46;
            const length = 0.65;

            const mat = new THREE.MeshStandardMaterial({
                color: colorHex,
                roughness: 0.22,
                metalness: 0.12
            });

            // Hemispherical Cap
            const domeGeo = new THREE.SphereGeometry(radius, 24, 16, 0, Math.PI * 2, 0, Math.PI / 2);
            const dome = new THREE.Mesh(domeGeo, mat);
            dome.position.y = length;

            // Cylindrical Shell Body
            const cylGeo = new THREE.CylinderGeometry(radius, radius, length, 24, 6);
            const cyl = new THREE.Mesh(cylGeo, mat);
            cyl.position.y = length / 2;

            group.add(dome, cyl);
            return group;
        }

        // Cleanroom White Top Shell
        const topHalf = createCapsuleHalf(0xffffff);
        topHalf.rotation.z = -Math.PI / 4;
        mainCapsuleGroup.add(topHalf);
        pillHalves.push({ mesh: topHalf, baseDir: new THREE.Vector3(-1.4, 1.2, 0.5) });

        // Vasudha Emerald Bottom Shell
        const botHalf = createCapsuleHalf(0x1DB88A);
        botHalf.rotation.z = (Math.PI * 3) / 4;
        mainCapsuleGroup.add(botHalf);
        pillHalves.push({ mesh: botHalf, baseDir: new THREE.Vector3(1.4, -1.2, -0.5) });

        // Orbiting Satellite Mini-Capsules (from reference)
        for (let i = 0; i < 6; i++) {
            const sideCapsule = createCapsuleHalf(i % 2 === 0 ? 0xffffff : 0x1DB88A);
            sideCapsule.scale.set(0.45, 0.45, 0.45);
            const angle = (i / 6) * Math.PI * 2;
            const dist = 2.6 + (i % 3) * 0.3;
            sideCapsule.position.set(
                Math.cos(angle) * dist,
                (i - 3) * 0.45,
                Math.sin(angle) * dist
            );
            sideCapsule.rotation.set(angle, angle * 0.5, 0.4);
            bottleGroup.add(sideCapsule);
        }
    }

    function buildMicroBeadCloud() {
        const beadGeo = new THREE.SphereGeometry(0.048, 10, 10);
        const colors = [0x1DB88A, 0x34d399, 0xffffff, 0x6ee7b7, 0x059669];

        for (let i = 0; i < 125; i++) {
            const beadMat = new THREE.MeshStandardMaterial({
                color: colors[i % colors.length],
                roughness: 0.3,
                metalness: 0.1
            });
            const bead = new THREE.Mesh(beadGeo, beadMat);

            const x = (Math.random() - 0.5) * 1.5;
            const y = (Math.random() - 0.5) * 2.8;
            const z = (Math.random() - 0.5) * 1.5;

            bead.position.set(x, y, z);
            mainCapsuleGroup.add(bead);

            microBeads.push({
                mesh: bead,
                basePos: new THREE.Vector3(x, y, z),
                speed: 0.004 + Math.random() * 0.008,
                offset: Math.random() * Math.PI * 2
            });
        }
    }

    /**
     * Orbiting Micro-Satellites
     */
    function buildOrbitingSatellites() {
        if (!globeGroup) return;
        const satGroup = new THREE.Group();
        globeGroup.add(satGroup);

        for (let i = 0; i < 4; i++) {
            const satGeo = new THREE.SphereGeometry(0.06, 12, 12);
            const satMat = new THREE.MeshBasicMaterial({ color: 0x34d399 });
            const sat = new THREE.Mesh(satGeo, satMat);
            const angle = (i / 4) * Math.PI * 2;
            sat.position.set(Math.cos(angle) * 3.4, Math.sin(angle) * 0.8, Math.sin(angle) * 3.4);
            satGroup.add(sat);
        }
    }

    /**
     * Binds Interactive Drag, Parallax & Partner Country Hover on Globe Stage
     */
    function bindGlobeInteractions() {
        if (!globeGroup) return;
        const stage = document.getElementById('vpGlobeStage') || canvasContainer;
        const tooltip = document.getElementById('vpGlobePartnerTooltip');
        if (!stage) return;

        stage.addEventListener('mousedown', (e) => {
            if (scrollProgress > 0.15) return;
            isDraggingGlobe = true;
            lastMouseX = e.clientX;
            lastMouseY = e.clientY;
        });

        window.addEventListener('mouseup', () => { isDraggingGlobe = false; });

        window.addEventListener('mousemove', (e) => {
            if (isDraggingGlobe && globeGroup) {
                const deltaX = e.clientX - lastMouseX;
                const deltaY = e.clientY - lastMouseY;
                lastMouseX = e.clientX;
                lastMouseY = e.clientY;

                globeGroup.rotation.y += deltaX * 0.005;
                globeGroup.rotation.x += deltaY * 0.005;
            }

            // Partner Country Hover Raycasting over Globe Stage
            if (stage && tooltip && scrollProgress < 0.15) {
                const rect = stage.getBoundingClientRect();
                if (e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom) {
                    const stageX = e.clientX - rect.left;
                    const stageY = e.clientY - rect.top;

                    // Detect closest partner nation to mouse ray
                    let closest = null;
                    let minD = 999999;

                    elevatedPartnerMeshes.forEach(p => {
                        const worldVec = p.center.clone().applyEuler(globeGroup.rotation).add(globeGroup.position);
                        worldVec.project(camera);

                        const screenX = ((worldVec.x + 1) * window.innerWidth) / 2;
                        const screenY = ((-worldVec.y + 1) * window.innerHeight) / 2;
                        const d = Math.hypot(e.clientX - screenX, e.clientY - screenY);

                        if (worldVec.z < 1.0 && d < minD && d < 65) {
                            minD = d;
                            closest = p;
                        }
                    });

                    if (closest && closest.partner) {
                        hoveredPartner = closest.partner;
                        tooltip.innerHTML = `
                            <span class="vp-tt-kicker">★ cGMP Export Partner</span>
                            <h4 class="vp-tt-name">${closest.partner.name}</h4>
                            <p class="vp-tt-desc">${closest.partner.filings || 'Commercial cGMP Supply Partner'}</p>
                        `;
                        tooltip.style.left = `${stageX}px`;
                        tooltip.style.top = `${stageY - 12}px`;
                        tooltip.classList.add('is-active');
                    } else {
                        hoveredPartner = null;
                        tooltip.classList.remove('is-active');
                    }
                } else {
                    hoveredPartner = null;
                    tooltip.classList.remove('is-active');
                }
            }
        });

        stage.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1 && scrollProgress <= 0.15) {
                isDraggingGlobe = true;
                lastMouseX = e.touches[0].clientX;
                lastMouseY = e.touches[0].clientY;
            }
        }, { passive: true });

        window.addEventListener('touchend', () => { isDraggingGlobe = false; });

        stage.addEventListener('touchmove', (e) => {
            if (!isDraggingGlobe || !globeGroup || e.touches.length !== 1) return;
            const deltaX = e.touches[0].clientX - lastMouseX;
            const deltaY = e.touches[0].clientY - lastMouseY;
            lastMouseX = e.touches[0].clientX;
            lastMouseY = e.touches[0].clientY;

            globeGroup.rotation.y += deltaX * 0.005;
            globeGroup.rotation.x += deltaY * 0.005;
        }, { passive: true });
    }

    function onMouseMove(e) {
        mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
        mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
    }

    /**
     * Scroll Choreography across Vasudha Homepage Sections
     */
    function onScroll() {
        const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
        const docHeight = (document.documentElement.scrollHeight || document.body.scrollHeight) - window.innerHeight;
        scrollProgress = Math.max(0, Math.min(1, scrollTop / (docHeight || 1)));

        // Update Top Kinetic Progress Bar if present
        const pBar = document.getElementById('vp3dProgressBar');
        if (pBar) {
            pBar.style.width = `${scrollProgress * 100}%`;
        }

        const isDesktop = window.innerWidth >= 992;

        if (scrollProgress < 0.10) {
            // PHASE 0: Hero / Global Reach — Grand 860px Globe is in spotlight on the right.
            // Bottle & capsule remain safely below the viewport.
            targetCamPos = { x: 0, y: 1.5, z: 11.0 };
            targetCamRot = { x: 0, y: 0, z: 0 };
            targetGlobePos = { x: 2.3, y: -0.15, z: 0 };
            targetGlobeScale = 0.95;
            targetBottlePos = { x: 0, y: -14.0, z: -2.0 };
            targetBottleScale = 1.0;
            targetCapOffsetY = 0;
            targetExplosion = 0;
            targetBottleRotY = 0;

        } else if (scrollProgress >= 0.10 && scrollProgress < 0.18) {
            // TRANSITION: Bottle enters and rises to SCREEN CENTRE right under the globe section!
            // Globe smoothly ascends away with hero scroll
            const tEntry = (scrollProgress - 0.10) / 0.08;
            const easeEntry = Math.sin(tEntry * Math.PI / 2);

            targetGlobePos = {
                x: 2.3 + easeEntry * 0.5,
                y: -0.15 + easeEntry * 12.0, // Ascends off screen
                z: 0 - easeEntry * 4.0
            };
            targetGlobeScale = 0.95 * (1 - easeEntry * 0.4);

            targetBottlePos = {
                x: 0,
                y: -14.0 + easeEntry * 14.0, // Rises to exactly y: 0.0 (screen center)
                z: -2.0 + easeEntry * 2.0   // Rises to exactly z: 0.0
            };
            targetBottleScale = 1.0;
            targetBottleRotY = 0;
            targetCapOffsetY = 0;
            targetExplosion = 0;
            targetCamPos = { x: 0, y: 1.5, z: 11.0 };
            targetCamRot = { x: 0, y: 0, z: 0 };

        } else {
            // REPLICATE EXACT REFERENCE ANIMATION STARTING RIGHT UNDER THE GLOBE (PRODUCTS SECTION):
            targetGlobePos = { x: 2.8, y: 12.0, z: -4.0 }; // Globe safely off screen
            targetGlobeScale = 0.5;

            // Full scroll span from Products (0.18) through the rest of the website
            const normP = Math.max(0, Math.min(1, (scrollProgress - 0.18) / 0.66));
            targetBottlePos = { x: 0, y: 0, z: 0 };
            targetBottleScale = 1.0;

            if (normP < 0.28) {
                // Phase 1: Products Section -> Closed upright bottle, rotating & zooming in
                const p1 = normP / 0.28;
                targetCamPos = { x: 0, y: 1.5, z: 11.0 - p1 * 2.0 };
                targetCamRot = { x: 0, y: 0, z: 0 };
                targetBottleRotY = p1 * Math.PI * 0.5;
                targetCapOffsetY = 0;
                targetExplosion = 0;

            } else if (normP >= 0.28 && normP < 0.62) {
                // Phase 2: Events & Manufacturing -> Camera pans to dynamic side angle, zoom in & Trigger Capsule Burst!
                const p2 = (normP - 0.28) / 0.34;
                targetCamPos = { x: 2.2 - p2 * 1.5, y: 0.5, z: 6.5 - p2 * 2.0 };
                targetCamRot = { x: 0.1, y: -0.4, z: 0 };
                targetBottleRotY = Math.PI * 0.5 + p2 * Math.PI;
                targetCapOffsetY = p2 * 1.2;
                targetExplosion = p2 * 1.4;

            } else {
                // Phase 3: Packaging & Technical Specs -> Elevated Camera & Floating Cap
                const p3 = (normP - 0.62) / 0.38;
                if (normP > 0.82) {
                    // Smoothly descend 3D bottle away when entering Foundation Moments, CTA & Footer
                    const exitT = Math.min(1, (normP - 0.82) / 0.18);
                    targetBottlePos = { x: 0, y: -exitT * 18.0, z: -exitT * 4.0 };
                    targetCapOffsetY = 1.8 * (1 - exitT);
                    targetExplosion = 0;
                    targetCamPos = { x: 0, y: 1.0, z: 9.0 };
                } else {
                    targetCamPos = { x: 0, y: 1.0 + p3 * 0.5, z: 8.5 + p3 * 2.5 };
                    targetCamRot = { x: -0.1, y: 0, z: 0 };
                    targetBottleRotY = Math.PI * 1.5 + p3 * Math.PI;
                    targetCapOffsetY = 1.8 + p3 * 0.8;
                    targetExplosion = (1 - p3) * 0.5;
                }
            }
        }
    }

    /**
     * Rotates the 3D globe to face any country coordinates
     */
    function focusCountry(lat, lng) {
        if (!globeGroup) return;
        const targetRotY = -(lng * Math.PI) / 180 - Math.PI / 2;
        const targetRotX = (lat * Math.PI) / 180;
        globeGroup.rotation.y = targetRotY;
        globeGroup.rotation.x = targetRotX * 0.4;
    }

    function onWindowResize() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
    }

    /**
     * Main Render Loop with Damped Lerp Interpolation
     */
    function animate(time) {
        requestAnimationFrame(animate);

        // Camera Smooth Lerp
        camera.position.x += (targetCamPos.x + mouseX * 0.35 - camera.position.x) * 0.05;
        camera.position.y += (targetCamPos.y - mouseY * 0.35 - camera.position.y) * 0.05;
        camera.position.z += (targetCamPos.z - camera.position.z) * 0.05;

        camera.rotation.x += (targetCamRot.x - camera.rotation.x) * 0.05;
        camera.rotation.y += (targetCamRot.y - camera.rotation.y) * 0.05;

        // Globe Kinematics & Auto-Rotation
        if (globeGroup) {
            globeGroup.position.x += (targetGlobePos.x - globeGroup.position.x) * 0.06;
            globeGroup.position.y += (targetGlobePos.y - globeGroup.position.y) * 0.06;
            globeGroup.position.z += (targetGlobePos.z - globeGroup.position.z) * 0.06;

            const gScale = globeGroup.scale.x;
            const ngScale = gScale + (targetGlobeScale - gScale) * 0.06;
            globeGroup.scale.set(ngScale, ngScale, ngScale);

            if (!isDraggingGlobe && scrollProgress < 0.25) {
                globeGroup.rotation.y += 0.0022;
            }
        }

        // Concentric Expanding Radar Waves on India HQ
        pulsingRings.forEach(p => {
            const scale = 1.0 + ((time * 0.0018 + p.offset) % 1.0) * 1.25;
            p.mesh.scale.set(scale, scale, scale);
            p.mesh.material.opacity = Math.max(0, 0.85 - (scale - 1.0) * 0.7);
        });

        // Flight Arc Traveling Light Packets
        flightBeads.forEach(b => {
            b.progress += b.speed;
            if (b.progress > 1.0) b.progress = 0;
            const pt = b.curve.getPoint(b.progress);
            b.mesh.position.copy(pt);
        });

        // Bottle Container & Threaded Cap
        if (bottleGroup) {
            bottleGroup.position.x += (targetBottlePos.x - bottleGroup.position.x) * 0.06;
            bottleGroup.position.y += (targetBottlePos.y - bottleGroup.position.y) * 0.06;
            bottleGroup.position.z += (targetBottlePos.z - bottleGroup.position.z) * 0.06;

            const bScale = bottleGroup.scale.x;
            const nbScale = bScale + (targetBottleScale - bScale) * 0.06;
            bottleGroup.scale.set(nbScale, nbScale, nbScale);

            bottleGroup.rotation.y += (targetBottleRotY - bottleGroup.rotation.y) * 0.05;
        }

        if (capMesh) {
            capMesh.position.y += ((1.9 + targetCapOffsetY) - capMesh.position.y) * 0.08;
        }

        // Capsule Half-Shells Explosion
        pillHalves.forEach(p => {
            p.mesh.position.x += (p.baseDir.x * targetExplosion - p.mesh.position.x) * 0.08;
            p.mesh.position.y += (p.baseDir.y * targetExplosion - p.mesh.position.y) * 0.08;
            p.mesh.position.z += (p.baseDir.z * targetExplosion - p.mesh.position.z) * 0.08;
        });

        // Micro-Bead Dynamics (Falling + Sinusoidal Drift + Scroll Scatter)
        microBeads.forEach(b => {
            b.mesh.position.y -= b.speed;
            b.mesh.position.x += Math.sin(time * 0.002 + b.offset) * 0.001;

            if (b.mesh.position.y < -2.2) {
                b.mesh.position.y = 1.3;
            }

            if (targetExplosion > 0) {
                b.mesh.position.x += b.basePos.x * targetExplosion * 0.02;
                b.mesh.position.z += b.basePos.z * targetExplosion * 0.02;
            }
        });

        renderer.render(scene, camera);
    }

    // Expose Public API
    window.Vasudha3DEngine = {
        init: init,
        focusCountry: focusCountry
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init('canvas-container'));
    } else {
        init('canvas-container');
    }

})(window);
