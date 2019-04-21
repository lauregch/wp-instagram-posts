<?php
/*
Plugin Name: Social Posts
Version: 1.0
Author: Laure Guicherd
Author Email: laure.guicherd@gmail.com
*/

namespace SocialPosts;

use SocialPosts\Importer as Importer;
use WP_Query;

require_once( plugin_dir_path( __FILE__ ) . 'autoload.php' );


class Plugin {


    static protected $custom_field = 'social_url';
    protected $post_metas = [];


    public function __construct() {

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'admin_init', [ $this, 'admin_init' ], 9, 2 );
        add_action( 'social_posts/import', [ $this, 'import' ], 10, 2 );

    }


    public function activate() {
        // Nothing to do here yet.
    }


    public function deactivate() {
        // Nothing to do here yet.
    }


    public function admin_init() {

        if ( array_key_exists('action', $_REQUEST) && $_REQUEST['action']=='editpost' ) {

            if ( array_key_exists('post_ID', $_REQUEST) ) {

                $post_id = intval( $_REQUEST['post_ID'] );

                $meta_value = get_post_meta( $post_id, self::$custom_field, true );
                $this->post_metas[ self::$custom_field ] = $meta_value;
                add_action( 'save_post', [ $this, 'after_update_post' ], 100, 3 );

            }

        }

    }


    public function after_update_post( $post_id, $post, $update ) {

        $meta_value_before = $this->post_metas[ self::$custom_field ];

        $meta_value = get_post_meta( $post_id, self::$custom_field, true );

        if ( ! $meta_value && array_key_exists('meta', $_REQUEST) ) {
            $metas = wp_list_filter( $_REQUEST['meta'], ['key' => self::$custom_field] );
            $meta = reset( $metas );
            $meta_value = $meta['value'];
        }

        if ( $meta_value != $meta_value_before ) {
            remove_action( 'save_post', [ $this, 'after_update_post' ], 100, 3 );
            $this->import( $meta_value, $post_id );
            add_action( 'save_post', [ $this, 'after_update_post' ], 100, 3 );
        }

    }


    public function import( $social_url, $post_id ) {

        $importer = new Importer( $social_url, $post_id );
        $importer->import();

    }


}


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


new Plugin();