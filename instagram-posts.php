<?php
/*
Plugin Name: Instagram Posts
Version: 1.0
Author: Laure Guicherd
Author Email: laure.guicherd@gmail.com
*/

namespace InstagramPosts;
use \WP_Query;


class Plugin {


    protected $option_slug = 'igm_posts';
    protected $custom_field = 'instagram_url';

    protected $post_metas = [];


    public function __construct() {

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

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

        $matches = [];
        if ( preg_match( "/^https?:\/\/www\.instagram\.com\/p\/((?:[0-9]|[A-zA-Z]|_|-)+)\/?/", $meta_value, $matches ) ) {

            $res = wp_remote_get( $meta_value );

            if ( $res && ! is_wp_error($res) && $res['response']['code']==200 ) {
                
                $html = $res['body'];
                $matches = [];
                
                if ( preg_match( "/window\._sharedData\s=\s({.*});\s?<\/script>/", $html, $matches ) ) {

                    $jsdata = json_decode( $matches[1] );
                    @$igmdata = $jsdata->entry_data->PostPage[0]->media;
                    
                    if ( $igmdata ) {

                        $caption = isset( $igmdata->caption ) ? $igmdata->caption : '';

                        $data = [
                            'ID' => $post_id,
                            'post_title' =>  $caption,
                            'post_content' => $caption,
                            'post_date' => date( 'Y-m-d H:i:s', $igmdata->date )
                        ];

                        wp_update_post( $data );

                       // DOWNLOAD IMAGE AND ASSIGN AS THUMBNAIL
                       $attach_id = $this->download_media( $igmdata, $post_id );

                       if ( $attach_id ) {
                            update_post_meta( $attach_id, '_igm_id', $igmdata->id );
                            set_post_thumbnail( $post_id, $attach_id );
                        }

                        //TODO set format
                        
                    }

                }

            }

        }

    }


    protected function download_media( $igmdata, $post_id ) {

        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => '_igm_id', 'value' => $igmdata->id
            ]] 
        ]);

        if ( $query->have_posts() ) {
            $attach_id = $query->posts[0]->ID;
        }
        else {

            $attach_id = self::add_attachment( $igmdata->display_src, 'Instagram '. $igmdata->id, $post_id );

            if ( $igmdata->is_video ) {
                $video_id = self::add_attachment( $igmdata->video_url, 'Instagram video '. $igmdata->id, $post_id );
            }

        }
        
        return $attach_id;

    }


    static protected function add_attachment( $urls, $title, $parent_id=false ){

        if ( ! is_array($urls) ) $urls = [ $urls ];

        $code = false;
        $k = 0;

        while ( $k < count($urls) && $code !== 200 ) {
            $code = wp_remote_retrieve_response_code( wp_remote_get( $urls[$k++] ) );
        } 
        
        if ( $code == 200 ) {

            $url = $urls[ $k-1 ];

            $url = str_replace( 'https', 'http', $url );
            $upload_dir = wp_upload_dir();

            $pathinfo = pathinfo( parse_url($url, PHP_URL_PATH) );
            
            $ext = (isset($pathinfo['extension']) ? $pathinfo['extension'] : '');
            if (strpos($ext,'?') !== false) { 
                $ext = substr( $ext, 0, strpos($ext, '?') );
            }
            // $image_name = microtime(true).'.'.$ext;
            $image_name = md5($url).'.'.$ext;

            if ( wp_mkdir_p($upload_dir['path']) )
                $file = $upload_dir['path'].'/'.$image_name;
            else
                $file = $upload_dir['basedir'].'/'.$image_name;

            $headers = get_headers( $url, 1 );
            if (isset( $headers['Location']) ) {
                $url = $headers['Location']; // string
            } 

            if ( @copy( $url, $file ) ) {

                $wp_filetype = wp_check_filetype( basename($file) );
                $filetype = $wp_filetype['type'];

                $attachment = [
                    'guid' => $upload_dir['baseurl'].'/'._wp_relative_upload_path( $file ), 
                    'post_mime_type' => $filetype,
                    'post_title' => $title,
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => get_current_user_id()
                ];
                $attach_id = wp_insert_attachment( $attachment, $file, $parent_id );
                // you must first include the image.php file
                // for the function wp_generate_attachment_metadata() to work
                require_once(ABSPATH.'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
                wp_update_attachment_metadata( $attach_id, $attach_data );

                return $attach_id;

            }

        }

        return false;

    }


}


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


new Plugin();
