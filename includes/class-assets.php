<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clear_Map_Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_gsap'));
    }
    
    public function enqueue_gsap() {
        // Enqueue GSAP for smooth animations
        wp_enqueue_script(
            'gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
            array(),
            '3.12.2',
            true
        );
        
        wp_enqueue_script(
            'gsap-scroll-trigger',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js',
            array('gsap'),
            '3.12.2',
            true
        );
    }
}
