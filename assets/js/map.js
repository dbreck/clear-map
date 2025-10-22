class ClearMap {
  constructor(containerId, data) {
    this.containerId = containerId
    this.data = data
    this.map = null
    this.markers = {}
    this.clusterSource = null
    this.activeCategories = new Set(Object.keys(data.categories || {}))
    this.activePois = new Set()

    this.init()
  }

  init() {
    if (!this.data.mapboxToken) {
      console.error("Mapbox token required")
      return
    }

    mapboxgl.accessToken = this.data.mapboxToken

    this.createMap()
    this.setupFilters()
    this.addBuildingMarker()
    this.setupMobileDrawer()
  }

  createMap() {
    // Always center on the building's geocoded coordinates if available
    const centerLng = this.data.buildingCoords && this.data.buildingCoords.lng ? this.data.buildingCoords.lng : this.data.centerLng
    const centerLat = this.data.buildingCoords && this.data.buildingCoords.lat ? this.data.buildingCoords.lat : this.data.centerLat

    this.map = new mapboxgl.Map({
      container: this.containerId,
      style: this.getCustomMapStyle(),
      center: [centerLng, centerLat],
      zoom: this.data.zoom,
      attributionControl: false,
      scrollZoom: false, // disables scroll zoom on map creation (for Mapbox GL >=2.0.0)
    })

    // For Mapbox GL <2.0.0, also call:
    if (this.map.scrollZoom) this.map.scrollZoom.disable()

    this.map.addControl(new mapboxgl.AttributionControl(), "bottom-left")
    this.map.addControl(new mapboxgl.NavigationControl(), "top-right")
    if (mapboxgl.FullscreenControl) {
      this.map.addControl(new mapboxgl.FullscreenControl(), "top-right")
    }

    this.map.on("load", () => {
      this.setupPoisSource()
      this.loadSubwayLines()
      this.animateMapLoad()
    })

    this.map.on("zoom", () => {
      this.updateNameVisibility()
      this.updateSubwayLabelsVisibility()
    })

    // Allow filter panel to scroll with mouse wheel
    const filtersEl = document.getElementById(this.containerId + "-filters")
    if (filtersEl) {
      filtersEl.addEventListener(
        "wheel",
        (e) => {
          e.stopPropagation() // Prevent map from zooming when scrolling filters
        },
        { passive: false }
      )
    }
  }

  getCustomMapStyle() {
    return {
      version: 8,
      sources: {
        mapbox: {
          type: "raster",
          tiles: ["https://api.mapbox.com/styles/v1/mapbox/light-v10/tiles/{z}/{x}/{y}?access_token=" + this.data.mapboxToken],
          tileSize: 512,
        },
      },
      layers: [
        {
          id: "mapbox-layer",
          type: "raster",
          source: "mapbox",
        },
      ],
      glyphs: "mapbox://fonts/mapbox/{fontstack}/{range}.pbf",
    }
  }

  setupPoisSource() {
    const geojsonData = this.poisToGeoJSON()

    // Check if we have any POIs to display
    if (!geojsonData.features || geojsonData.features.length === 0) {
      console.log("Clear Map: No POIs to display")
      console.log("POI data:", this.data.pois)
      console.log("Categories:", this.data.categories)
      return
    }

    console.log(`Clear Map: Setting up ${geojsonData.features.length} POI markers`)

    this.map.addSource("pois", {
      type: "geojson",
      data: geojsonData,
      cluster: true,
      clusterMaxZoom: 14,
      clusterRadius: parseInt(this.data.clusterDistance, 10),
      clusterMinPoints: parseInt(this.data.clusterMinPoints, 10),
    })

    const onSourceLoaded = () => {
      if (this.map.isSourceLoaded("pois")) {
        this.map.off("sourcedata", onSourceLoaded) // Prevent multiple executions
        this.addClusteringLayers()
        this.setupMapInteractions()
      }
    }

    this.map.on("sourcedata", onSourceLoaded)
  }

  addClusteringLayers() {
    // Cluster circles
    this.map.addLayer({
      id: "clusters",
      type: "circle",
      source: "pois",
      filter: ["has", "point_count"],
      paint: {
        "circle-color": "#D4A574",
        "circle-radius": ["step", ["get", "point_count"], 20, 100, 30, 750, 40],
        "circle-stroke-width": 2,
        "circle-stroke-color": "#fff",
      },
    })

    // Cluster labels
    this.map.addLayer({
      id: "cluster-count",
      type: "symbol",
      source: "pois",
      filter: ["has", "point_count"],
      layout: {
        "text-field": "{point_count_abbreviated}",
        "text-font": ["Open Sans Semibold", "Arial Unicode MS Bold"],
        "text-size": 12,
      },
      paint: {
        "text-color": "#fff",
      },
    })

    // Individual POI points
    this.map.addLayer({
      id: "unclustered-point",
      type: "circle",
      source: "pois",
      filter: ["!", ["has", "point_count"]],
      paint: {
        "circle-color": ["get", "color"],
        "circle-radius": 8,
        "circle-stroke-width": 2,
        "circle-stroke-color": "#fff",
      },
    })

    // POI labels (shown at zoom threshold)
    this.map.addLayer({
      id: "poi-labels",
      type: "symbol",
      source: "pois",
      filter: ["!", ["has", "point_count"]],
      layout: {
        "text-field": ["get", "name"],
        "text-font": ["Open Sans Semibold", "Arial Unicode MS Bold"],
        "text-size": 11,
        "text-offset": [0, 1.5],
        "text-anchor": "top",
      },
      paint: {
        "text-color": "#333",
        "text-halo-color": "#fff",
        "text-halo-width": 1,
      },
    })

    this.updateNameVisibility()
  }

  poisToGeoJSON() {
    const features = []
    const skippedPois = []

    // Safely check if we have POIs and categories
    if (!this.data.pois || !this.data.categories) {
      return {
        type: "FeatureCollection",
        features: [],
      }
    }

    Object.keys(this.data.pois).forEach((category) => {
      if (!this.activeCategories.has(category)) return

      const categoryPois = this.data.pois[category]
      if (!Array.isArray(categoryPois)) return

      categoryPois.forEach((poi, index) => {
        // Validate coordinates exist and are numbers
        // Skip POIs with false, null, undefined, or invalid coordinates
        if (!poi || poi.lat === false || poi.lng === false || !poi.lat || !poi.lng) {
          if (poi?.name) {
            skippedPois.push({
              name: poi.name,
              reason: poi.coordinate_source || 'unknown',
              error: poi.geocoding_error || 'none'
            })
          }
          return
        }

        const lat = parseFloat(poi.lat)
        const lng = parseFloat(poi.lng)

        if (isNaN(lat) || isNaN(lng)) {
          skippedPois.push({
            name: poi?.name || 'Unknown POI',
            reason: 'invalid_values',
            coords: {lat: poi?.lat, lng: poi?.lng}
          })
          return
        }

        const poiId = `${category}-${index}`
        if (!this.activePois.has(poiId) && this.activePois.size > 0) return

        // Safely get category color
        const categoryColor = this.data.categories[category]?.color || "#888888"

        features.push({
          type: "Feature",
          properties: {
            id: poiId,
            name: poi.name || "Unnamed POI",
            address: poi.address || "",
            description: poi.description || "",
            website: poi.website || "",
            category: category,
            color: categoryColor,
          },
          geometry: {
            type: "Point",
            coordinates: [lng, lat],
          },
        })
      })
    })

    // Log summary of skipped POIs
    if (skippedPois.length > 0) {
      console.warn(`Clear Map: Skipped ${skippedPois.length} POI(s) without valid coordinates:`)
      skippedPois.forEach(poi => {
        console.log(`  - ${poi.name} (${poi.reason}${poi.error && poi.error !== 'none' ? ': ' + poi.error : ''})`)
      })
    }

    return {
      type: "FeatureCollection",
      features: features,
    }
  }

  updateNameVisibility() {
    if (!this.map.getLayer("poi-labels")) return

    const currentZoom = this.map.getZoom()
    const visible = currentZoom >= this.data.zoomThreshold ? "visible" : "none"

    this.map.setLayoutProperty("poi-labels", "visibility", visible)
  }

  setupMapInteractions() {
    // Only setup interactions if we have POI layers
    if (!this.map.getLayer("unclustered-point")) return

    // Hover effects
    this.map.on("mouseenter", "unclustered-point", (e) => {
      this.map.getCanvas().style.cursor = "pointer"
      this.showPoiTooltip(e)
    })

    this.map.on("mouseleave", "unclustered-point", () => {
      this.map.getCanvas().style.cursor = ""
      this.hidePoiTooltip()
    })

    // Cluster click to zoom
    if (this.map.getLayer("clusters")) {
      this.map.on("click", "clusters", (e) => {
        const features = this.map.queryRenderedFeatures(e.point, { layers: ["clusters"] })
        const clusterId = features[0].properties.cluster_id

        this.map.getSource("pois").getClusterExpansionZoom(clusterId, (err, zoom) => {
          if (err) return

          this.map.easeTo({
            center: features[0].geometry.coordinates,
            zoom: zoom,
          })
        })
      })
    }
  }

  loadSubwayLines() {
    if (!this.data.showSubwayLines || !this.data.subwayDataUrl) {
      console.log("Clear Map: Subway lines disabled or no data URL provided")
      return
    }

    console.log("Clear Map: Loading subway lines from", this.data.subwayDataUrl)

    fetch(this.data.subwayDataUrl)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
        return response.json()
      })
      .then(data => {
        console.log("Clear Map: Subway data loaded successfully", data)
        this.addSubwayLayers(data)
      })
      .catch(error => {
        console.error("Clear Map: Failed to load subway lines", error)
      })
  }

  addSubwayLayers(geojsonData) {
    if (!geojsonData.features || geojsonData.features.length === 0) {
      console.log("Clear Map: No subway line features found")
      return
    }

    // Add subway lines source
    this.map.addSource("subway-lines", {
      type: "geojson",
      data: geojsonData
    })

    // Determine where to insert subway layers - before POI clusters if they exist
    const beforeLayer = this.map.getLayer("clusters") ? "clusters" : undefined

    // Add subway lines layer with MTA styling
    this.map.addLayer({
      id: "subway-lines",
      type: "line",
      source: "subway-lines",
      layout: {
        "line-join": "round",
        "line-cap": "round"
      },
      paint: {
        "line-color": ["get", "color"],
        "line-width": [
          "interpolate",
          ["linear"],
          ["zoom"],
          10, 2,
          15, 3,
          18, 4
        ],
        "line-opacity": 0.8
      }
    }, beforeLayer)

    // Add subway lines labels for higher zoom levels
    this.map.addLayer({
      id: "subway-lines-labels",
      type: "symbol",
      source: "subway-lines",
      layout: {
        "text-field": ["get", "line"],
        "text-font": ["Open Sans Bold", "Arial Unicode MS Bold"],
        "text-size": 10,
        "symbol-placement": "line",
        "text-rotation-alignment": "map"
      },
      paint: {
        "text-color": "#ffffff",
        "text-halo-color": ["get", "color"],
        "text-halo-width": 2
      }
    }, beforeLayer)

    // Show labels only at higher zoom levels
    this.updateSubwayLabelsVisibility()

    console.log(`Clear Map: Added ${geojsonData.features.length} subway lines to map`)
  }

  updateSubwayLabelsVisibility() {
    if (!this.map.getLayer("subway-lines-labels")) return

    const currentZoom = this.map.getZoom()
    const visible = currentZoom >= 14 ? "visible" : "none"

    this.map.setLayoutProperty("subway-lines-labels", "visibility", visible)
  }

  toggleSubwayLines(visible) {
    if (!this.map.getLayer("subway-lines")) return

    const visibility = visible ? "visible" : "none"
    this.map.setLayoutProperty("subway-lines", "visibility", visibility)
    
    if (this.map.getLayer("subway-lines-labels")) {
      this.map.setLayoutProperty("subway-lines-labels", "visibility", 
        visible && this.map.getZoom() >= 14 ? "visible" : "none")
    }
  }

  showPoiTooltip(e) {
    const poi = e.features[0].properties

    const tooltip = document.createElement("div")
    tooltip.className = "clear-map-tooltip"
    tooltip.innerHTML = `
            <div class="tooltip-name">${poi.name}</div>
            <div class="tooltip-address">${poi.address}</div>
        `

    document.body.appendChild(tooltip)

    const moveTooltip = (e) => {
      tooltip.style.left = e.pageX + 10 + "px"
      tooltip.style.top = e.pageY - 10 + "px"
    }

    this.map.on("mousemove", "unclustered-point", moveTooltip)
    moveTooltip(e.originalEvent)
  }

  hidePoiTooltip() {
    const tooltip = document.querySelector(".clear-map-tooltip")
    if (tooltip) tooltip.remove()
  }

  addBuildingMarker() {
    if (!this.data.buildingCoords) return

    const el = document.createElement("div")
    el.className = "building-marker"
    // Use custom PNG or SVG if provided, else fallback
    if (this.data.buildingIconPNG) {
      el.innerHTML = `<img src="${this.data.buildingIconPNG}" style="width:${this.data.buildingIconWidth};height:auto;display:block;" alt="Building Icon" />`
    } else if (this.data.buildingIconSVG) {
      el.innerHTML = `<img src="${this.data.buildingIconSVG}" style="width:${this.data.buildingIconWidth};height:auto;display:block;" alt="Building Icon" />`
    } else {
      el.innerHTML = this.getBuildingIconSVG()
    }

    const marker = new mapboxgl.Marker(el).setLngLat([this.data.buildingCoords.lng, this.data.buildingCoords.lat]).addTo(this.map)

    // Info popup content
    const info = `
      <div class="building-popup">
        <strong>${this.data.buildingAddress || ""}</strong><br />
        ${this.data.buildingDescription ? `<div>${this.data.buildingDescription}</div>` : ""}
        ${this.data.buildingPhone ? `<div><a href='tel:${this.data.buildingPhone}'>${this.data.buildingPhone}</a></div>` : ""}
        ${this.data.buildingEmail ? `<div><a href='mailto:${this.data.buildingEmail}'>${this.data.buildingEmail}</a></div>` : ""}
      </div>
    `
    const popup = new mapboxgl.Popup({ offset: 24, closeButton: true }).setHTML(info)
    el.addEventListener("mouseenter", () => popup.addTo(this.map).setLngLat([this.data.buildingCoords.lng, this.data.buildingCoords.lat]))
    el.addEventListener("mouseleave", () => popup.remove())
    el.addEventListener("click", () => popup.addTo(this.map).setLngLat([this.data.buildingCoords.lng, this.data.buildingCoords.lat]))
  }

  getBuildingIconSVG() {
    const width = this.data.buildingIconWidth
    return `
            <svg width="${width}" height="${width}" viewBox="0 0 40 40" class="building-icon">
                <circle cx="20" cy="20" r="18" fill="#D4A574" stroke="#fff" stroke-width="3"/>
                <circle cx="20" cy="20" r="8" fill="#fff"/>
            </svg>
        `
  }

  setupFilters() {
    const filtersEl = document.getElementById(this.containerId + "-filters")
    if (!filtersEl) return

    // Initialize all POIs as active
    this.initializeActivePois()

    // Toggle filters panel
    const toggleBtn = filtersEl.querySelector(".toggle-filters")
    if (toggleBtn) {
      toggleBtn.addEventListener("click", () => {
        filtersEl.classList.toggle("collapsed")
        this.animateFiltersToggle(filtersEl)
      })
    }

    // Category toggles
    filtersEl.querySelectorAll(".category-header").forEach((header) => {
      const expandBtn = header.querySelector(".category-expand")
      const categoryEl = header.closest(".filter-category")
      const poisEl = categoryEl.querySelector(".category-pois")
      const categoryToggle = header.querySelector(".category-toggle")

      // Expand/collapse POIs
      if (expandBtn && poisEl) {
        expandBtn.addEventListener("click", () => {
          const isExpanded = poisEl.style.display !== "none"
          poisEl.style.display = isExpanded ? "none" : "block"
          expandBtn.classList.toggle("expanded", !isExpanded)
          poisEl.setAttribute("aria-expanded", !isExpanded)
          this.animateAccordion(poisEl, !isExpanded)
        })
      }

      // Filter by category on click (toggle)
      if (categoryToggle) {
        categoryToggle.addEventListener("click", (e) => {
          const catKey = categoryEl.dataset.category
          const isOnlyThis = this.activeCategories.size === 1 && this.activeCategories.has(catKey)
          if (isOnlyThis) {
            // Show all categories
            this.activeCategories = new Set(Object.keys(this.data.categories || {}))
            this.activePois.clear()
            Object.keys(this.data.pois).forEach((category) => {
              this.data.pois[category].forEach((poi, idx) => {
                this.activePois.add(`${category}-${idx}`)
              })
            })
            // Mark UI
            filtersEl.querySelectorAll(".filter-category").forEach((fc) => fc.classList.remove("inactive"))
            filtersEl.querySelectorAll(".poi-item").forEach((poiEl) => poiEl.classList.remove("inactive"))
          } else {
            // Only show this category
            this.activeCategories = new Set([catKey])
            this.activePois.clear()
            if (this.data.pois[catKey]) {
              this.data.pois[catKey].forEach((poi, idx) => {
                this.activePois.add(`${catKey}-${idx}`)
              })
            }
            // Mark UI
            filtersEl.querySelectorAll(".filter-category").forEach((fc) => fc.classList.toggle("inactive", fc !== categoryEl))
            filtersEl.querySelectorAll(".poi-item").forEach((poiEl) => poiEl.classList.remove("inactive"))
          }
          this.updateMap()
        })
      }
    })

    // Individual POI toggles: show only that POI and center map
    filtersEl.querySelectorAll(".poi-item").forEach((poiEl) => {
      poiEl.addEventListener("click", (e) => {
        const poiId = poiEl.dataset.poi
        this.activePois = new Set([poiId])
        // Mark UI
        filtersEl.querySelectorAll(".poi-item").forEach((el) => el.classList.toggle("inactive", el !== poiEl))
        this.updateMap()
        // Center map on POI
        const [catKey, idx] = poiId.split("-")
        const poi = this.data.pois[catKey]?.[parseInt(idx, 10)]
        if (poi && poi.lng && poi.lat) {
          this.map.easeTo({ center: [poi.lng, poi.lat], zoom: Math.max(this.data.zoom, 16) })
        }
      })
    })

    // Subway lines toggle
    const subwayToggle = filtersEl.querySelector(".subway-toggle-checkbox")
    if (subwayToggle) {
      subwayToggle.addEventListener("change", (e) => {
        const isVisible = e.target.checked
        this.toggleSubwayLines(isVisible)
      })
    }
  }

  initializeActivePois() {
    if (!this.data.pois) return

    Object.keys(this.data.pois).forEach((category) => {
      const categoryPois = this.data.pois[category]
      if (Array.isArray(categoryPois)) {
        categoryPois.forEach((poi, index) => {
          this.activePois.add(`${category}-${index}`)
        })
      }
    })
  }

  toggleCategory(category, categoryEl) {
    if (this.activeCategories.has(category)) {
      this.activeCategories.delete(category)
      categoryEl.classList.add("inactive")
    } else {
      this.activeCategories.add(category)
      categoryEl.classList.remove("inactive")
    }

    this.updateMap()
  }

  togglePoi(poiId, poiEl) {
    if (this.activePois.has(poiId)) {
      this.activePois.delete(poiId)
      poiEl.classList.add("inactive")
    } else {
      this.activePois.add(poiId)
      poiEl.classList.remove("inactive")
    }

    this.updateMap()
  }

  updateMap() {
    if (this.map.getSource("pois")) {
      this.map.getSource("pois").setData(this.poisToGeoJSON())
    }
  }

  setupMobileDrawer() {
    if (window.innerWidth <= 768) {
      const filtersEl = document.getElementById(this.containerId + "-filters")
      if (filtersEl) {
        filtersEl.classList.add("mobile-drawer")
      }
    }
  }

  animateMapLoad() {
    if (typeof gsap !== "undefined") {
      gsap.fromTo(this.map.getContainer(), { opacity: 0, scale: 0.95 }, { opacity: 1, scale: 1, duration: 0.8, ease: "power2.out" })
    }
  }

  animateFiltersToggle(filtersEl) {
    if (typeof gsap !== "undefined") {
      const content = filtersEl.querySelector(".filters-content")
      const isCollapsed = filtersEl.classList.contains("collapsed")

      gsap.to(content, {
        height: isCollapsed ? 0 : "auto",
        opacity: isCollapsed ? 0 : 1,
        duration: 0.3,
        ease: "power2.inOut",
      })
    }
  }

  animateAccordion(element, isExpanding) {
    if (typeof gsap !== "undefined" && isExpanding) {
      gsap.fromTo(element, { height: 0, opacity: 0 }, { height: "auto", opacity: 1, duration: 0.3, ease: "power2.out" })
    }
  }
}

// Initialize maps when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".clear-map-container").forEach((container) => {
    const mapId = container.dataset.mapId
    const jsVarName = container.dataset.jsVar
    const dataVar = "clearMapData_" + jsVarName

    if (window[dataVar]) {
      new ClearMap(mapId, window[dataVar])
    } else {
      console.error("Clear Map: Data not found for", dataVar)
    }
  })
})
