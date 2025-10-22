jQuery(document).ready(function ($) {
  // Initialize color pickers
  $(".color-picker").wpColorPicker()

  // Copy to clipboard functionality
  $(".copy-shortcode").on("click", function () {
    const button = $(this)
    const shortcode = button.data("shortcode")

    if (navigator.clipboard) {
      navigator.clipboard.writeText(shortcode).then(function () {
        button.text("Copied!").addClass("copied")
        setTimeout(() => {
          button.text("Copy").removeClass("copied")
        }, 2000)
      })
    } else {
      // Fallback for older browsers
      const textarea = $("<textarea>").val(shortcode).appendTo("body").select()
      document.execCommand("copy")
      textarea.remove()
      button.text("Copied!").addClass("copied")
      setTimeout(() => {
        button.text("Copy").removeClass("copied")
      }, 2000)
    }
  })

  // Clear geocode cache
  $("#clear-geocode-cache").on("click", function () {
    if (confirm("Clear all cached geocoding data? This will force fresh API calls for all addresses.")) {
      const button = $(this)
      const originalText = button.text()
      button.prop("disabled", true).text("Clearing...")

      $.post(
        clearMapAdmin.ajaxurl,
        {
          action: "clear_map_geocode_cache",
          nonce: clearMapAdmin.clearCacheNonce,
        },
        function (response) {
          if (response.success) {
            alert("Geocode cache cleared successfully!")
          } else {
            alert("Error clearing cache. Please try again.")
          }
          button.prop("disabled", false).text(originalText)
        }
      ).fail(function () {
        alert("Error clearing cache. Please try again.")
        button.prop("disabled", false).text(originalText)
      })
    }
  })

  // Clear all POIs functionality
  $("#clear-all-pois-btn").on("click", function () {
    const confirmMessage = "Are you sure you want to clear ALL POIs and categories?\n\n" +
      "This will permanently delete all existing POIs and categories. " +
      "This action cannot be undone.\n\n" +
      "Type 'DELETE' to confirm:"
    
    const userInput = prompt(confirmMessage)
    
    if (userInput === "DELETE") {
      const button = $(this)
      const originalText = button.text()
      button.prop("disabled", true).text("Clearing...")

      $.post(
        clearMapAdmin.ajaxurl,
        {
          action: "clear_map_clear_all_pois",
          nonce: clearMapAdmin.clearAllPoisNonce,
        },
        function (response) {
          if (response.success) {
            alert("Successfully cleared " + response.data.cleared_pois + " POIs and " + response.data.cleared_categories + " categories!")
            // Refresh the page to show updated stats
            window.location.reload()
          } else {
            alert("Error clearing POIs: " + (response.data || "Unknown error"))
          }
          button.prop("disabled", false).text(originalText)
        }
      ).fail(function () {
        alert("Error clearing POIs. Please try again.")
        button.prop("disabled", false).text(originalText)
      })
    } else if (userInput !== null) {
      alert("Clear operation cancelled. You must type 'DELETE' to confirm.")
    }
  })

  // KML Import functionality
  $("#kml-import-form").on("submit", function (e) {
    e.preventDefault()

    const form = this
    const formData = new FormData(form)
    formData.append("action", "clear_map_import_kml_pois")
    formData.append("nonce", clearMapAdmin.importKmlNonce)

    const importBtn = $("#import-btn")
    const spinner = $(".spinner")
    const results = $("#import-results")

    importBtn.prop("disabled", true).text("Importing...")
    spinner.addClass("is-active")
    results.hide()

    $.ajax({
      url: clearMapAdmin.ajaxurl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          displayImportResults(response.data)
        } else {
          alert("Import failed: " + response.data)
        }
      },
      error: function () {
        alert("Import failed: Network error")
      },
      complete: function () {
        importBtn.prop("disabled", false).text("Import POIs")
        spinner.removeClass("is-active")
      },
    })
  })

  function displayImportResults(data) {
    const results = $("#import-results")
    const summary = $("#import-summary")

    let categoriesText = ""
    if (data.category_names && Object.keys(data.category_names).length > 0) {
      const categoryList = Object.values(data.category_names).join(", ")
      categoriesText = `<p>Detected categories: <strong>${categoryList}</strong></p>`
    }

    // Count POIs with coordinates vs without and analyze coordinate sources
    let poisWithCoords = 0
    let poisWithoutCoords = 0
    let coordinateSources = {
      kml: 0,
      geocoding_needed: 0,
      none: 0
    }

    if (data.pois) {
      data.pois.forEach((poi) => {
        if (poi.lat && poi.lng) {
          poisWithCoords++
        } else {
          poisWithoutCoords++
        }
        
        // Track coordinate sources
        const source = poi.coordinate_source || 'none'
        if (coordinateSources[source] !== undefined) {
          coordinateSources[source]++
        } else {
          coordinateSources[source] = 1
        }
      })
    }

    let coordinateStatus = ""
    if (poisWithoutCoords > 0) {
      coordinateStatus = `<div class="notice notice-warning"><p><strong>Warning:</strong> ${poisWithoutCoords} POIs are missing coordinates and will need geocoding.</p></div>`
    }
    
    // Add coordinate source breakdown
    let sourceBreakdown = ""
    if (coordinateSources.kml > 0 || coordinateSources.geocoding_needed > 0) {
      const sourceDetails = []
      if (coordinateSources.kml > 0) {
        sourceDetails.push(`${coordinateSources.kml} from KML coordinates`)
      }
      if (coordinateSources.geocoding_needed > 0) {
        sourceDetails.push(`${coordinateSources.geocoding_needed} will be geocoded`)
      }
      if (coordinateSources.none > 0) {
        sourceDetails.push(`${coordinateSources.none} without coordinates`)
      }
      sourceBreakdown = `<p><small>Coordinate sources: ${sourceDetails.join(', ')}</small></p>`
    }

    summary.html(`
            <div class="notice notice-success">
                <p><strong>Import successful!</strong> Found ${data.total} POIs.</p>
                <p>POIs with coordinates: <strong>${poisWithCoords}</strong></p>
                ${poisWithoutCoords > 0 ? `<p>POIs needing geocoding: <strong>${poisWithoutCoords}</strong></p>` : ""}
                ${categoriesText}
                ${sourceBreakdown}
            </div>
            ${coordinateStatus}
        `)

    // Add debug information if available
    if (data.debug_log && data.debug_log.length > 0) {
      summary.append(`
                <div class="import-debug" style="margin-top: 15px;">
                    <h4 style="cursor: pointer;" onclick="jQuery('.debug-log').toggle();">
                        Debug Information (click to toggle)
                    </h4>
                    <div class="debug-log" style="display: none; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: scroll;">
                        <pre style="font-size: 11px; margin: 0;">${data.debug_log.join("\n")}</pre>
                    </div>
                </div>
            `)
    }

    results.show()

    // If we have organized categories, auto-save them
    if (data.pois_by_category && Object.keys(data.pois_by_category).length > 0) {
      autoSaveCategorizedPois(data)
    } else {
      // Fall back to manual assignment
      showCategoryAssignment(data)
    }
  }

  function autoSaveCategorizedPois(data) {
    const assignment = $("#category-assignment")
    const summary = $("#import-summary")

    // Show saving status
    summary.append(`
            <div class="notice notice-info">
                <p>POIs are already organized by category. Saving automatically...</p>
            </div>
        `)

    const replaceExisting = $("#replace-existing").is(":checked")

    $.post(
      clearMapAdmin.ajaxurl,
      {
        action: "clear_map_save_imported_pois",
        nonce: clearMapAdmin.importKmlNonce,
        category_assignments: {}, // Empty since we're using auto-categorization
        replace_existing: replaceExisting,
      },
      function (response) {
        if (response.success) {
          let importStatsHtml = ""
          if (response.data.import_stats) {
            const stats = response.data.import_stats
            const statDetails = []
            
            if (stats.coordinate_sources) {
              for (const [source, count] of Object.entries(stats.coordinate_sources)) {
                statDetails.push(`${count} from ${source}`)
              }
            }
            
            if (stats.geocoding_stats) {
              const geoStats = stats.geocoding_stats
              statDetails.push(`${geoStats.successfully_geocoded} successfully geocoded`)
              if (geoStats.failed_geocoding > 0) {
                statDetails.push(`${geoStats.failed_geocoding} failed geocoding`)
              }
            }
            
            if (statDetails.length > 0) {
              importStatsHtml = `<p><small>Details: ${statDetails.join(', ')}</small></p>`
            }
          }
          
          summary.html(`
                    <div class="notice notice-success">
                        <p><strong>Import Complete!</strong> ${response.data.message}</p>
                        <p>Categories created: <strong>${response.data.categories.join(", ")}</strong></p>
                        ${importStatsHtml}
                    </div>
                    <div class="import-actions" style="margin-top: 15px;">
                        <a href="admin.php?page=andrea-map" class="button button-primary">View Dashboard</a>
                        <a href="admin.php?page=andrea-map-manage" class="button button-secondary">Manage POIs</a>
                    </div>
                `)

          // Hide the assignment section since we auto-saved
          assignment.hide()
        } else {
          alert("Auto-save failed: " + response.data)
          // Fall back to manual assignment
          showCategoryAssignment(data)
        }
      }
    ).fail(function () {
      alert("Auto-save failed: Network error")
      // Fall back to manual assignment
      showCategoryAssignment(data)
    })
  }

  function showCategoryAssignment(data) {
    const assignment = $("#category-assignment")
    const poiList = $("#poi-category-list")

    // Get available categories
    const availableCategories = ["restaurants", "shopping", "arts_culture", "fitness", "services", "general"]

    const categoryNames = {
      restaurants: "Restaurants",
      shopping: "Shopping",
      arts_culture: "Arts + Culture",
      fitness: "Fitness",
      services: "Services",
      general: "General",
    }

    let html = '<div class="poi-assignment-grid">'

    data.pois.forEach((poi, index) => {
      // Try to auto-assign category based on detected category
      let detectedCategory = "general"
      if (data.categories && data.categories.length > 0) {
        // Simple category mapping
        const catName = data.categories[0].toLowerCase()
        if (catName.includes("restaurant") || catName.includes("food")) {
          detectedCategory = "restaurants"
        } else if (catName.includes("shop") || catName.includes("store")) {
          detectedCategory = "shopping"
        } else if (catName.includes("art") || catName.includes("culture") || catName.includes("museum")) {
          detectedCategory = "arts_culture"
        } else if (catName.includes("fitness") || catName.includes("gym")) {
          detectedCategory = "fitness"
        } else if (catName.includes("service") || catName.includes("bank")) {
          detectedCategory = "services"
        }
      }

      const coordinateStatus = poi.lat && poi.lng ? "✓ Has coordinates" : "⚠️ Needs geocoding"
      const coordinateSource = poi.coordinate_source ? ` (${poi.coordinate_source})` : ""

      html += `
                <div class="poi-assignment-row">
                    <div class="poi-info">
                        <strong>${poi.name}</strong>
                        <br><small>${poi.address || "No address"}</small>
                        <br><small style="color: ${poi.lat && poi.lng ? "green" : "orange"};">${coordinateStatus}${coordinateSource}</small>
                    </div>
                    <div class="category-select">
                        <select name="category_assignments[${index}]" data-poi-index="${index}">
            `

      availableCategories.forEach((catKey) => {
        const selected = catKey === detectedCategory ? "selected" : ""
        html += `<option value="${catKey}" ${selected}>${categoryNames[catKey]}</option>`
      })

      html += `
                        </select>
                    </div>
                </div>
            `
    })

    html += "</div>"
    poiList.html(html)
    assignment.show()
  }

  // Save category assignments
  $("#save-assignments").on("click", function () {
    const button = $(this)
    const originalText = button.text()

    // Collect category assignments
    const assignments = {}
    $('select[name^="category_assignments"]').each(function () {
      const index = $(this).data("poi-index")
      assignments[index] = $(this).val()
    })

    const replaceExisting = $("#replace-existing").is(":checked")

    button.prop("disabled", true).text("Saving...")

    $.post(
      clearMapAdmin.ajaxurl,
      {
        action: "clear_map_save_imported_pois",
        nonce: clearMapAdmin.importKmlNonce,
        category_assignments: assignments,
        replace_existing: replaceExisting,
      },
      function (response) {
        if (response.success) {
          alert(`Successfully imported ${response.data.imported} POIs!`)
          window.location.href = "admin.php?page=andrea-map"
        } else {
          alert("Save failed: " + response.data)
        }
      }
    )
      .fail(function () {
        alert("Save failed: Network error")
      })
      .always(function () {
        button.prop("disabled", false).text(originalText)
      })
  })

  // Add category functionality
  $("#add-category").on("click", function () {
    const container = $("#categories-container")
    const newKey = "category_" + Date.now()

    const categoryHtml = `
            <div class="category-row" data-key="${newKey}">
                <input type="text" name="andrea_map_categories[${newKey}][name]" value="" placeholder="Category Name" />
                <input type="text" name="andrea_map_categories[${newKey}][color]" value="#D4A574" class="color-picker" />
                <button type="button" class="button remove-category">Remove</button>
            </div>
        `

    container.append(categoryHtml)
    container.find(".color-picker").last().wpColorPicker()
  })

  // Remove category functionality
  $(document).on("click", ".remove-category", function () {
    if (confirm("Are you sure you want to remove this category? This will also remove all POIs in this category.")) {
      $(this).closest(".category-row").remove()
    }
  })

  // Add POI functionality
  $(document).on("click", ".add-poi", function () {
    const category = $(this).data("category")
    const container = $(this).closest(".poi-category")
    const existingPois = container.find(".poi-row").length

    if (existingPois >= 30) {
      alert("Maximum 30 POIs per category allowed.")
      return
    }

    const poiHtml = `
            <div class="poi-row" data-category="${category}" data-index="${existingPois}">
                <input type="text" name="andrea_map_pois[${category}][${existingPois}][name]" value="" placeholder="POI Name" />
                <input type="text" name="andrea_map_pois[${category}][${existingPois}][address]" value="" placeholder="Address" />
                <textarea name="andrea_map_pois[${category}][${existingPois}][description]" placeholder="Description"></textarea>
                <input type="url" name="andrea_map_pois[${category}][${existingPois}][website]" value="" placeholder="Website URL" />
                <input type="hidden" name="andrea_map_pois[${category}][${existingPois}][photo]" value="" class="poi-photo-url" />
                <button type="button" class="button upload-photo">Upload Photo</button>
                <button type="button" class="button remove-poi">Remove</button>
            </div>
        `

    $(this).before(poiHtml)
    updatePoiIndices(container)
  })

  // Remove POI functionality
  $(document).on("click", ".remove-poi", function () {
    if (confirm("Are you sure you want to remove this POI?")) {
      const container = $(this).closest(".poi-category")
      $(this).closest(".poi-row").remove()
      updatePoiIndices(container)
    }
  })

  // Photo upload functionality
  $(document).on("click", ".upload-photo", function (e) {
    e.preventDefault()

    const button = $(this)
    const hiddenInput = button.siblings(".poi-photo-url")

    const mediaUploader = wp.media({
      title: "Select POI Photo",
      button: {
        text: "Use this photo",
      },
      multiple: false,
      library: {
        type: "image",
      },
    })

    mediaUploader.on("select", function () {
      const attachment = mediaUploader.state().get("selection").first().toJSON()
      hiddenInput.val(attachment.url)
      button.text("Change Photo").addClass("has-photo")
    })

    mediaUploader.open()
  })

  // SVG Media uploader for Building Icon
  $("#andrea_map_building_icon_svg_upload").on("click", function (e) {
    e.preventDefault()
    var custom_uploader = wp
      .media({
        title: "Select SVG Icon",
        button: { text: "Use this SVG" },
        library: { type: "image" },
        multiple: false,
      })
      .on("select", function () {
        var attachment = custom_uploader.state().get("selection").first().toJSON()
        if (attachment.url && attachment.url.endsWith(".svg")) {
          $("#andrea_map_building_icon_svg").val(attachment.url)
        } else {
          alert("Please select an SVG file.")
        }
      })
      .open()
  })

  // Building Icon PNG upload
  $("#andrea_map_building_icon_png_upload").on("click", function (e) {
    e.preventDefault()
    const input = $("#andrea_map_building_icon_png")
    const frame = wp.media({
      title: "Select PNG Icon",
      button: { text: "Use this PNG" },
      library: { type: "image" },
      multiple: false,
    })
    frame.on("select", function () {
      const attachment = frame.state().get("selection").first().toJSON()
      if (attachment.url && attachment.url.match(/\.png$/i)) {
        input.val(attachment.url)
      } else {
        alert("Please select a PNG file.")
      }
    })
    frame.open()
  })

  // Update POI indices after add/remove
  function updatePoiIndices(container) {
    container.find(".poi-row").each(function (index) {
      const row = $(this)
      const category = row.data("category")

      row.attr("data-index", index)

      row.find("input, textarea").each(function () {
        const input = $(this)
        const name = input.attr("name")
        if (name) {
          const newName = name.replace(/\[\d+\]/, `[${index}]`)
          input.attr("name", newName)
        }
      })
    })
  }

  // Form validation
  $("form").on("submit", function (e) {
    const form = $(this)

    // Skip validation for KML import form
    if (form.attr("id") === "kml-import-form") {
      return
    }

    // Only validate on the settings and manage pages
    if (!form.find('input[name="andrea_map_mapbox_token"], input[name="andrea_map_categories"]').length) {
      return
    }

    let hasErrors = false

    // Check required API keys on settings page
    if (form.find('input[name="andrea_map_mapbox_token"]').length) {
      const mapboxToken = $('input[name="andrea_map_mapbox_token"]').val()
      const googleApiKey = $('input[name="andrea_map_google_api_key"]').val()

      if (!mapboxToken.trim()) {
        alert("Mapbox access token is required.")
        hasErrors = true
      }

      if (!googleApiKey.trim()) {
        alert("Google Geocoding API key is required.")
        hasErrors = true
      }
    }

    // Check category names on manage page
    $('.category-row input[type="text"]').each(function () {
      if ($(this).attr("name") && $(this).attr("name").includes("[name]") && !$(this).val().trim()) {
        alert("All categories must have names.")
        hasErrors = true
        return false
      }
    })

    // Check POI required fields on manage page
    $(".poi-row").each(function () {
      const name = $(this).find('input[placeholder="POI Name"]').val()
      const address = $(this).find('input[placeholder="Address"]').val()

      if (name !== undefined && address !== undefined && (!name.trim() || !address.trim())) {
        alert("All POIs must have both name and address.")
        hasErrors = true
        return false
      }
    })

    if (hasErrors) {
      e.preventDefault()
    }
  })

  // Auto-save functionality (optional)
  let saveTimeout
  $("input, textarea, select").on("input change", function () {
    clearTimeout(saveTimeout)
    saveTimeout = setTimeout(function () {
      // Could add auto-save AJAX here if desired
    }, 2000)
  })

  // Help tooltips
  $(".form-table th").each(function () {
    const th = $(this)
    const description = th.next("td").find(".description")

    if (description.length) {
      th.append(' <span class="help-tip" title="' + description.text() + '">?</span>')
    }
  })

  // Initialize tooltips if available
  if ($.fn.tooltip) {
    $(".help-tip").tooltip()
  }

  // Dashboard stats animation (optional)
  if ($(".stat-number").length) {
    $(".stat-number").each(function () {
      const $this = $(this)
      const finalNumber = $this.text()

      if ($.isNumeric(finalNumber)) {
        $this.text("0")
        $({ value: 0 }).animate(
          { value: finalNumber },
          {
            duration: 1000,
            step: function () {
              $this.text(Math.floor(this.value))
            },
            complete: function () {
              $this.text(finalNumber)
            },
          }
        )
      }
    })
  }

  // Manual Geocoding Button
  $("#run-geocoding-btn").on("click", function () {
    if (!confirm("This will geocode all POIs that have coordinates but no addresses.\n\nThis may take a few minutes for large datasets. Continue?")) {
      return
    }

    const button = $(this)
    const originalHtml = button.html()
    const spinner = $("#geocoding-spinner")
    const statusDiv = $("#geocoding-status")

    // Show immediate visual feedback
    button.prop("disabled", true).html('<span class="dashicons dashicons-update-alt" style="margin-top: 3px; animation: rotation 2s infinite linear;"></span> Geocoding...')
    spinner.css('visibility', 'visible').addClass('is-active')
    statusDiv.html('<div class="notice notice-info inline"><p><strong>Running geocoding...</strong> Please wait, this may take a minute.</p></div>')

    $.post(
      clearMapAdmin.ajaxurl,
      {
        action: "clear_map_run_geocoding",
        nonce: clearMapAdmin.runGeocodingNonce,
      },
      function (response) {
        spinner.css('visibility', 'hidden').removeClass('is-active')

        if (response.success) {
          const stats = response.data.stats
          let statsHtml = `<div class="notice notice-success inline"><p><strong>✓ Reverse Geocoding Complete!</strong></p><ul style="margin: 10px 0;">`
          statsHtml += `<li><strong>Total POIs:</strong> ${stats.total_processed}</li>`
          statsHtml += `<li><strong>Already had addresses:</strong> ${stats.already_had_addresses}</li>`
          statsHtml += `<li><strong>Successfully reverse geocoded:</strong> ${stats.successfully_reverse_geocoded}</li>`
          if (stats.failed_reverse_geocoding > 0) {
            statsHtml += `<li style="color: #d63638;"><strong>Failed:</strong> ${stats.failed_reverse_geocoding}</li>`
          }
          statsHtml += `</ul><p><em>Page will refresh in 20 seconds...</em></p></div>`
          statusDiv.html(statsHtml)

          // Refresh the page after 20 seconds
          setTimeout(() => {
            window.location.reload()
          }, 20000)
        } else {
          statusDiv.html(`<div class="notice notice-error inline"><p><strong>Error:</strong> ${response.data || "Unknown error"}</p></div>`)
          button.prop("disabled", false).html(originalHtml)
        }
      }
    ).fail(function (jqXHR, textStatus, errorThrown) {
      spinner.css('visibility', 'hidden').removeClass('is-active')
      statusDiv.html(`<div class="notice notice-error inline"><p><strong>Network error:</strong> ${textStatus}. Please try again.</p></div>`)
      button.prop("disabled", false).html(originalHtml)
    })
  })

  // Responsive dashboard handling
  function handleResponsive() {
    if ($(window).width() < 768) {
      $(".dashboard-card").removeClass("large-card")
    }
  }

  $(window).on("resize", handleResponsive)
  handleResponsive()
})
