<?php
/*
Plugin Name: Instagram Posts
Version: 1.0
Author: Laure Guicherd
Author Email: laure.guicherd@gmail.com
*/

namespace InstagramPosts;
use \WP_Query;

require_once( plugin_dir_path( __FILE__ ) . 'autoload.php' );


class Plugin {


    protected $option_slug = 'igm_posts';
    protected $custom_field = 'social_url';

    protected $importers = [];


    public function __construct() {

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        $this->importers = apply_filters( 'igm_posts/importers', [
            new Importers\Instagram(),
            new Importers\Twitter()
        ]);

        add_action( 'admin_init', [ $this, 'admin_init' ], 9, 2 );

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

                $meta_value = get_post_meta( $post_id, $this->custom_field, true );
                $this->post_metas[ $this->custom_field ] = $meta_value;
                add_action( 'save_post', [ $this, 'after_update_post' ], 11, 3 );


            }

        }

    }


    public function after_update_post( $post_id, $post, $update ) {

        $meta_value = get_post_meta( $post_id, $this->custom_field, true );
        $meta_value_before = $this->post_metas[ $this->custom_field ];

        if ( $meta_value != $meta_value_before ) {
            remove_action( 'save_post', [ $this, 'after_update_post' ], 11, 3 );
            $this->update( $post_id, $this->custom_field, $meta_value );
        }

    }


    protected function update( $post_id, $meta_key, $meta_value ) {

        _log( 'UPDATE' );

        foreach ( $this->importers as $importer ) {
            $importer->import( $meta_value, $post_id );
        }

    }   


}


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


new Plugin();
