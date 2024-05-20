<?php

/*
 * Plugin Name:       Random Banner ads
 * Plugin URI:        https://wordpress.org/plugins/random-banner-ads/
 * Description:       Display random banner ads anywhere easily using shortcode. You can set interval time to change your banner randomly
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Abdur Rahman
 * Author URI:        https://profiles.wordpress.org/mdranaabs12123/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/mdranaabs
 * Text Domain:       srba
 * Tags:              ads, advertisement, banner, banner ads, widget, random ads, random banner
 */


// Register Custom Post Type
function rid_register_custom_post_type() {
    $args = array(
        'public' => true,
        'label'  => __('Random Ads', 'srba'),
        'supports' => array('title', 'thumbnail'), // Add support
        'menu_icon'           => 'dashicons-format-image'
    );
    register_post_type( 'random_image', $args );
}

// Register Custom Taxonomy
function rid_register_taxonomy() {
    $args = array(
        'hierarchical' => true,
        'labels' => array(
            'name' => __('Ads Categories', 'srba'),
            'singular_name' => __('Ads Category', 'srba')
        ),
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array( 'slug' => 'image-category' ),
    );
    register_taxonomy( 'image_category', array( 'random_image' ), $args );
}
add_action( 'init', 'rid_register_taxonomy' );

add_action( 'init', 'rid_register_custom_post_type' );


// Add meta boxes for image link and interval time
function rid_add_meta_boxes() {
    add_meta_box('rid_image_link', __('Image Link', 'srba'), 'rid_image_link_callback', 'random_image', 'normal', 'default');
    add_meta_box('rid_interval_time', __('Interval Time (in milliseconds)', 'srba'), 'rid_interval_time_callback', 'random_image', 'normal', 'default');
}
add_action('add_meta_boxes', 'rid_add_meta_boxes');

// Callback function for image link meta box
function rid_image_link_callback($post) {
    $image_link = get_post_meta($post->ID, 'rid_image_link', true);
    echo '<input type="text" name="rid_image_link" value="' . esc_attr($image_link) . '" style="width: 100%;" />';
}

// Callback function for interval time meta box
function rid_interval_time_callback($post) {
    $interval_time = get_post_meta($post->ID, 'rid_interval_time', true);
    echo '<input type="number" name="rid_interval_time" value="' . esc_attr($interval_time) . '" style="width: 100%;" />';
}


// Save meta fields
function rid_save_meta_fields($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['rid_image_link'])) {
        update_post_meta($post_id, 'rid_image_link', sanitize_text_field($_POST['rid_image_link']));
    }

    if (isset($_POST['rid_interval_time'])) {
        update_post_meta($post_id, 'rid_interval_time', intval($_POST['rid_interval_time']));
    }
}
add_action('save_post', 'rid_save_meta_fields');


// Function of the shortcode
function rid_random_image_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '', // Default to empty category
    ), $atts);

    $args = array(
        'post_type' => 'random_image',
        'posts_per_page' => 1,
        'orderby' => 'rand',
        'tax_query' => array(),
    );

    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'image_category',
                'field'    => 'slug',
                'terms'    => $atts['category'],
            ),
        );
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $image = get_the_post_thumbnail();
            $image_link = get_post_meta(get_the_ID(), 'rid_image_link', true);
            $interval_time = get_post_meta(get_the_ID(), 'rid_interval_time', true);

            ob_start();
            ?>
            <div id="random-image">
                <a href="<?php echo esc_url($image_link); ?>" target="_blank">
                    <?php echo $image; ?>
                </a>
            </div>
            <script>
                jQuery(document).ready(function($) {
                    function updateRandomImage() {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'rid_get_random_image',
                                category: '<?php echo $atts['category']; ?>'
                            },
                            success: function(response) {
                                $('#random-image').html(response);
                            }
                        });
                    }

                    setInterval(updateRandomImage, <?php echo intval($interval_time); ?>);
                });
            </script>
            <?php
            return ob_get_clean();
        }
        wp_reset_postdata();
    } else {
        return __('No images found', 'srba');
    }
}
add_shortcode('random_image', 'rid_random_image_shortcode');

// Enqueue the script
function rid_enqueue_scripts() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'rid_enqueue_scripts');




// AJAX Action to Get Random Image
function rid_get_random_image() {
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

    $args = array(
        'post_type' => 'random_image',
        'posts_per_page' => 1,
        'orderby' => 'rand',
        'tax_query' => array(),
    );

    if (!empty($category)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'image_category',
                'field'    => 'slug',
                'terms'    => $category,
            ),
        );
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $image = get_the_post_thumbnail();
            $image_link = get_post_meta(get_the_ID(), 'rid_image_link', true);
        }
        wp_reset_postdata();
        // Return formatted HTML including image and link
        echo '<a href="' . esc_url($image_link) . '">' . $image . '</a>';
    } else {
        echo __('No images found', 'srba');
    }
    wp_die();
}
add_action('wp_ajax_rid_get_random_image', 'rid_get_random_image');
add_action('wp_ajax_nopriv_rid_get_random_image', 'rid_get_random_image');
