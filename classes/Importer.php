<?php 

namespace SocialPosts;

use SocialPosts\Consts\Fields;
use SocialPosts\Consts\Metas;
use SocialPosts\Scrapers\ScraperFactory;
use WP_Query;
use WP_Error;


class Importer {


    protected $_postID;
    protected $_scraper;


    public function __construct( $url, $post_id ) {

        $this->_scraper = ScraperFactory::create( $url );
        $this->_postID = intval( $post_id );

    }


    protected function ready() {
        return ( ! is_wp_error($this->_scraper) );
    }


    protected function scrape( $field ) {
        return $this->_scraper->get( $field );
    }


    protected function scraperData() {
        return [
            'url' => $this->_scraper->url(),
            'html' => $this->_scraper->html(),
            'data' => $this->_scraper->data()
        ];
    }


    final public function import() {

        if ( ! $this->ready() ) return;

        // UPDATE POST
        $post_data = $this->buildPostData();
        $post_data['ID'] = $this->_postID;
        apply_filters( 'social_posts/post_data', $post_data, $this->scraperData() );
        wp_update_post( $post_data );

        // UPDATE POST METAS
        $metas = $this->buildPostMetas();
        apply_filters( 'social_posts/post_meta', $metas, $this->_postID, $this->scraperData() );
        foreach ( $metas as $key => $value ) {
            update_post_meta( $this->_postID, $key, $value );
        }

        // DOWNLOAD IMAGE AND ASSIGN AS THUMBNAIL
        if ( $img_url = $this->scrape( Fields::ImageURL ) ) {

            if ( $attach_id = $this->get_media( $img_url, $this->_postID ) ) {
                update_post_meta( $attach_id, Metas::SocialID, $this->scrape(Fields::ID) );
                set_post_thumbnail( $this->_postID, $attach_id );
            }

        }

        // SET FORMAT
        if ( $format = $this->_scraper->getPostFormat() ) {
            set_post_format( $this->_postID, $format );
        }

        // IF SOME FIELDS HAVE NOT BEEN SCRAPED, DO IT NOW
        $this->_scraper->scrapeAll();

        do_action( 'social_posts/after_update_post', $this->_postID, $this->scraperData() );
            
    }


    protected function buildPostData() {

        $title = $this->scrape(Fields::Title) ? $this->scrape(Fields::Title) : $this->scrape(Fields::Content);

        return [
            'post_title' => trim( wp_trim_words( wp_strip_all_tags($title), 12 ) ),
            'post_content' => $this->scrape( Fields::Content ),
            'post_date' => $this->scrape( Fields::Date )
        ];

    }


    private function buildPostMetas() {

        $metas = [];

        // SOCIAL ID
        $id = $this->scrape( Fields::ID );
        $metas[ Metas::SocialID ] = $id;

        // VIDEO
        if ( $video_url = $this->scrape( Fields::VideoURL ) ) {
            if ( $video_id = $this->get_media( $video_url, $this->_postID ) ) {
                $metas[ Metas::VideoID ] = $video_id;
            }
        }
        if ( $video_embed = $this->scrape( Fields::VideoEmbed ) ) {
            $metas[ Metas::VideoEmbed ] = $video_embed;
        }
        
        // GALLERY
        if ( $gallery_items = $this->scrape( Fields::Gallery ) ) {

            $gallery_meta = [];

            if ( is_array($gallery_items) && ! empty($gallery_items) ) {
                foreach ( $gallery_items as $item ) {
            
                    $m = [];

                    $img_url = $item[ Fields::ImageURL ];
                    if ( $media_id = $this->get_media($img_url, $this->_postID) ) {
                        $m[ Metas::MediaID ] = $media_id;
                    }
                    if ( array_key_exists( Fields::VideoURL, $item ) ) {
                        $video_url = $item[ Fields::VideoURL ];
                        if ( $media_id = $this->get_media($video_url, $this->_postID) ) {
                            $m[ Metas::VideoID ] = $media_id;
                        }
                    }
                    $gallery_meta[] = $m;
                }
            }
            $metas[ Metas::Gallery ] = $gallery_meta;
        }

        return $metas;
        
    }


    protected function get_media( $url, $post_id=false ) {

        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'meta_query' => [[ 'key' => Metas::SourceURL, 'value' => $url ]]
        ]);

        if ( $query->have_posts() ) {
            $attach_id = $query->posts[0]->ID;
        }
        else {
            $attach_id = self::download_attachment( $url, $this->_scraper->source() . ' '. $this->scrape(Fields::ID), $post_id );
            update_post_meta( $attach_id, Metas::SourceURL, $url );
        }
        
        return $attach_id;

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