<?php 

namespace InstagramPosts\Importers;

use InstagramPosts\Consts\Fields;
use \WP_Query;


abstract class Importer {


    static protected $source;
    static protected $pattern;

    static protected $meta_key = '_social_id';

    protected $raw_data = null;


    final public function import( $url, $post_id ) {

        _log( 'import '.$url );

        $matches = [];
        if ( preg_match( static::$pattern, $url, $matches ) ) {

            $res = wp_remote_get( $url );

            if ( $res && ! is_wp_error($res) && $res['response']['code']==200 ) {
                
                $html = $res['body'];
                
                $data = $this->scrape( $url, $html );
                _log($data);

                if ( ! is_wp_error($data) ) {

                    $mapped_data = $this->map_fields( $data );

                    $mapped_data['ID'] = $post_id;
                    wp_update_post( $mapped_data );

                    // STORE SOCIAL ID IN A META
                    update_post_meta( $post_id, self::$meta_key, $data[Fields::ID] );

                   // DOWNLOAD IMAGE AND ASSIGN AS THUMBNAIL
                    if ( array_key_exists( Fields::Image, $data ) ) {

                        $attach_id = $this->get_main_media( $data[Fields::Image], $data[Fields::ID], $post_id );
                        if ( $attach_id ) {
                            update_post_meta( $attach_id, self::$meta_key, $data[Fields::ID] );
                            set_post_thumbnail( $post_id, $attach_id );
                        }

                    }

                    // SET FORMAT
                    if ( $format = $this->get_format() ) {
                        set_post_format( $post_id, $format );
                    }
                        
                }

            }

        }

    }


    protected function map_fields( $data ) {

        return [
            'post_title' => array_key_exists( Fields::Title, $data ) && $data[ Fields::Title ] ? $data[ Fields::Title ] : $data[ Fields::Content ],
            'post_content' => $data[ Fields::Content ],
            'post_date' => $data[ Fields::Date ]
        ];

    }


    protected function get_main_media( $url, $social_id, $post_id=false ) {

        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => self::$meta_key,
                'value' => $social_id
            ]] 
        ]);

        if ( $query->have_posts() ) {
            $attach_id = $query->posts[0]->ID;
        }
        else {

            $attach_id = self::download_attachment( $url, static::$source . ' '. $social_id, $post_id );

            // if ( $igmdata->is_video ) {
            //     $video_id = self::download_attachment( $igmdata->video_url, 'Instagram video '. $igmdata->id, $post_id );
            // }

        }
        
        return $attach_id;

    }


    abstract protected function scrape( $url, $html );


    protected function get_format() {

        return false;

    }


    //TODO Helper
    static protected function download_attachment( $urls, $title, $parent_id=false ){

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
                require_once( ABSPATH.'wp-admin/includes/image.php' );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
                wp_update_attachment_metadata( $attach_id, $attach_data );

                return $attach_id;

            }

        }

        return false;

    }


}