<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_Frontend {

    public function __construct() {
        add_shortcode('clear_map', array($this, 'render_map_shortcode'));
        // Backwards compatibility with old shortcode name
        add_shortcode('the_andrea_map', array($this, 'render_map_shortcode'));
    }
    
    public function render_map_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '60vh',
            'center_lat' => 40.7451,
            'center_lng' => -74.0011,
            'zoom' => 14
        ), $atts);
        
        // Check if required API keys are set
        if (empty(get_option('clear_map_mapbox_token'))) {
            return '<p>Map configuration required. Please set Mapbox token in admin.</p>';
        }
        
        wp_enqueue_script('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', array(), '2.15.0', true);
        wp_enqueue_style('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', array(), '2.15.0');
        
        $renderer = new Clear_Map_Renderer();
        return $renderer->render($atts);
    }
}
