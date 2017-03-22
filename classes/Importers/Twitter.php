<?php 

namespace InstagramPosts\Importers;

use InstagramPosts\Consts\Fields;
// use InstagramPosts\Consts\Sources;
use \WP_Error;



class Twitter extends Importer {


    static protected $source = 'twitter'; //Sources::Instagram; //TODO
    static protected $pattern = "//";


    public function scrape( $url, $html ) {

        $data = [];

        $this->scrape_field( $data, Fields::ID, $url, '/twitter.com\/[^\/]+\/status\/([0-9]+)/s' );
        $this->scrape_field( $data, Fields::Content, $html, '/<meta\s+property="og:description"\s+content="([^"]+)/s' );
        $this->scrape_field( $data, Fields::Title, $html, '/<meta\s+property="og:title"\s+content="([^"]+)/s' );
        $this->scrape_field( $data, Fields::Image, $html, '/data-image-url="([^"]+)/s', $false );
        $this->scrape_field( $data, Fields::Date, $html, '/class="[^"]*tweet-timestamp[^"]+".*data-time="([0-9]+)/' );
        $data[ Fields::Date ] = date( 'Y-m-d H:i:s', $data[ Fields::Date ] );
        $this->scrape_field( $data, Fields::AuthorUserName, $url, '/twitter.com\/([^\/]+)/' );
        $this->scrape_field( $data, Fields::AuthorRealName, $data[ Fields::Title ], '/^(.*) on Twitter/' );
        $this->scrape_field( $data, Fields::AuthorAvatarURL, $html, '/<a class="[^"]*profile-picture[^>]+href="([^"]+)/s' );

        return $data;

    }


    protected function scrape_field( &$data, $field, $str, $pattern, $mandatory=true ) {

        if ( ! is_wp_error($data) ) {

            $matches = [];

            if ( preg_match( $pattern, $str, $matches ) ) {
                $data[ $field ] = $matches[1];
            }
            elseif ( $mandatory ) {
                $data = new WP_Error( -1, $field );
            }

        }

    }



    protected function get_format() {

        return 'quote';
                
    }


}