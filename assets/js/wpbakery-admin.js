/**
 * WPBakery Admin JavaScript for Clear Map
 *
 * Handles responsive device toggle functionality for custom param types.
 *
 * @package Clear_Map
 * @since   1.7.0
 */

(function ($) {
  "use strict"

  /**
   * Initialize responsive field functionality.
   */
  function initResponsiveFields() {
    // Use event delegation for dynamically loaded WPBakery modals
    $(document).on("click", ".clear-map-responsive-field .device-btn", function (e) {
      e.preventDefault()

      const $btn = $(this)
      const $container = $btn.closest(".clear-map-responsive-field")
      const device = $btn.data("device")

      // Update active button
      $container.find(".device-btn").removeClass("active")
      $btn.addClass("active")

      // Show/hide appropriate input
      $container.find(".device-input").hide()
      $container.find('.device-input[data-device="' + device + '"]').show()
    })

    // Update combined value when any device input changes
    $(document).on("input change", ".clear-map-responsive-field .device-input", function () {
      const $container = $(this).closest(".clear-map-responsive-field")
      updateCombinedValue($container)
    })

    // Mark buttons that have values set
    $(document).on("vc.display.template", function () {
      updateValueIndicators()
    })
  }

  /**
   * Update the combined hidden value from all device inputs.
   *
   * @param {jQuery} $container The responsive field container.
   */
  function updateCombinedValue($container) {
    const desktop = $container.find('.device-input[data-device="desktop"]').val() || ""
    const tablet = $container.find('.device-input[data-device="tablet"]').val() || ""
    const mobile = $container.find('.device-input[data-device="mobile"]').val() || ""

    // Combine values with pipe separator
    const combined = desktop + "|" + tablet + "|" + mobile

    // Update hidden input
    $container.find(".responsive-combined-value").val(combined)

    // Update value indicators
    updateValueIndicatorsForContainer($container)
  }

  /**
   * Update value indicators for all responsive fields.
   */
  function updateValueIndicators() {
    $(".clear-map-responsive-field").each(function () {
      updateValueIndicatorsForContainer($(this))
    })
  }

  /**
   * Update value indicators for a specific container.
   *
   * @param {jQuery} $container The responsive field container.
   */
  function updateValueIndicatorsForContainer($container) {
    $container.find(".device-btn").each(function () {
      const $btn = $(this)
      const device = $btn.data("device")
      const $input = $container.find('.device-input[data-device="' + device + '"]')
      const value = $input.val()

      if (value && value.trim() !== "") {
        $btn.addClass("has-value")
      } else {
        $btn.removeClass("has-value")
      }
    })
  }

  /**
   * Initialize when WPBakery modal opens.
   */
  function initOnModalOpen() {
    // WPBakery fires this event when modal content is ready
    $(document).on("vc.edit_form.render", function () {
      setTimeout(function () {
        updateValueIndicators()
      }, 100)
    })
  }

  // Initialize on document ready
  $(document).ready(function () {
    initResponsiveFields()
    initOnModalOpen()
  })
})(jQuery)
