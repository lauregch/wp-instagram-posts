<?php 

namespace SocialPosts\Scrapers;

use SocialPosts\Consts\Fields;
use WP_Error;



class Twitter extends Scraper {


    static protected $source = 'twitter'; //Sources::Twitter; //TODO
    static public $url_pattern = "/^https?:\/\/twitter.com\/[^\/]+\/status\/[0-9]+\/?$/";

    private $_isImage = null;
    private $_isVideo = null;
    private $_isGallery = null;

    private $_isGIF = null;
    private $_gifEmbedHTML = '';


    protected function getID() {

        return $this->matchRegex(
            $this->_url,
            '/twitter.com\/[^\/]+\/status\/([0-9]+)/s'
        );

    }

    protected function getTitle() {

        return $this->matchRegex(
            $this->html(),
            '/<meta\s+property="og:title"\s+content="([^"]+)/s'
        );

    }

    protected function getContent() {

        $content = $this->matchRegex(
            $this->html(),
            '/<meta\s+property="og:description"\s+content="([^"]+)/s'
        );

        // Remove enclosing quotes
        $content = preg_replace( '/^“(.+)”$/', '$1', $content );

        // Add links to links
        $content = preg_replace( '/https?:\/\/\S*/', '<a href="$0" target="_blank">$0</a>',  $content );

        // Add links to hashtags
        $content = html_entity_decode( $content, ENT_QUOTES );
        $content = preg_replace( '/#(\w+)/', '<a href="https://twitter.com/hashtag/$1" target="_blank">$0</a>',  $content );

        return $content;

    }

     protected function getDate() {

        return $this->matchRegex(
            $this->html(),
            '/class="[^"]*tweet-timestamp[^"]+".*data-time="([0-9]+)/',
            1,
            function( $timestamp ) {
                return date( 'Y-m-d H:i:s', $timestamp );
            }
        );
    
    }

    protected function getImageURL() {

        $value = false; 

        if ( $this->isImage() || $this->isGallery() ) {

            $value = $this->matchRegex( 
                $this->html(),
                '/data-image-url="([^"]+)/s'
            );

        }
        elseif ( $this->isVideo() ) {

            $value = $this->matchRegex( 
                $this->html(),
                '/<meta\s+property="og:image"\s+content="([^"]+)/s'
            );

        }
        elseif ( $this->isGIF() ) {

            //TODO cache me
            $embed_data = $this->matchRegex(
                $this->gifEmbedHTML(),
                '/data-config="([^"]+)"/s',
                1,
                function($blah) {
                    return json_decode( html_entity_decode($blah) );
                }
            );

            $value = $embed_data->image_src;

        }

        return $value;

    }

    protected function getVideoURL() {

        $value = false;

        if ( $this->isGIF() ) {

            //TODO cache me
            $embed_data = $this->matchRegex(
                    $this->gifEmbedHTML(),
                    '/data-config="([^"]+)"/s',
                    1,
                    function( $blah ) {
                        return json_decode( html_entity_decode($blah) );
                }
            );

            $value = $embed_data->video_url;

        }

        return $value;

    }

    protected function getVideoEmbed() {

        $value = false;

        if ( $this->isVideo() ) {

            $value = $this->matchRegex(
                $this->html(),
                '/<meta\s+property="og:video:url"\s+content="([^"]+)/s',
                1,
                function( $a ) {
                    return strtok( $a, '?' );
                }
            );

        }
        elseif ( $this->isGIF() ) {
            $value = 'https://twitter.com/i/videos/tweet/' . $this->get( Fields::ID );
        }

        return $value;

    }

    protected function getGallery() {

        $value = false;

        if ( $this->isGallery() ) {

            $gallery_medias = $this->matchRegex(
                $this->html(),
                '/data-image-url="([^"]+)/s',
                false
            );

            $value = [];
            foreach ( $gallery_medias as $media_url ) {
                $value[] = [ Fields::ImageURL => $media_url ];
            }

        }

        return $value;

    }

    protected function getAuthorID() {

        return $this->matchRegex(
            $this->html(),
            '/data-user-id="([^"]+)"/s'
        );

    }

    protected function getAuthorUserName() {

        return $this->matchRegex(
            $this->_url,
            '/twitter.com\/([^\/]+)/'
        );

    }

    protected function getAuthorRealName() {

        return $this->matchRegex(
            $this->get( Fields::Title ),
            '/^(.*) on Twitter/'
        );

    }

    protected function getAuthorAvatarURL() {

        return $this->matchRegex(
            $this->html(),
            '/<a class="[^"]*profile-picture[^>]+href="([^"]+)/s'
        );

    }


    protected function matchRegex( $from_str, $pattern, $limit=1, $fct=false ) {

        $data = false;

        $matches = [];
        if ( preg_match_all( $pattern, $from_str, $matches ) ) {

            $matches = $matches[1];

            $data = array_slice( $matches, 0, $limit ? $limit : null );

            if ( $fct ) {
                foreach ( $data as &$item ) {
                    $item = $fct( $item );
                }
            }

            if ( $limit==1 ) $data = $data[0];

        }

        return $data;

    }


    public function getPostFormat() {

        $format = false;

        if ( $this->isVideo() ) $format = 'video';
        elseif ( $this->isGallery() ) $format = 'gallery';
        elseif ( $this->isImage() ) $format = 'image';
        else $format  = 'quote';

        return $format;
                
    }


    protected function isVideo() {

        if ( is_null($this->_isVideo) ) {

            $is = false; 

            $matches = [];
            if ( preg_match( '/<meta\s+property="og:type"\s+content="([^"]+)/s', $this->html(), $matches ) ) {
                $is |= $matches[1] == 'video';
            }

            $this->_isVideo = $is;

        }

        return $this->_isVideo;

    }


    protected function isGallery() {

        if ( is_null($this->_isGallery) ) {
          
            $is = false; 

            $matches = [];
            if ( preg_match( '/data-image-url="([^"]+)/s', $this->html(), $matches ) ) {
                $is = ( is_array($matches) && count($matches) > 1 );
            }

            $this->_isGallery = $is;

        }

        return $this->_isGallery;

    }


     protected function isImage() {

        if ( is_null($this->_isImage) ) {

            $is = false; 

            $matches = [];
            if ( preg_match( '/data-image-url="([^"]+)/s', $this->html(), $matches ) ) {
                $is = ( is_array($matches) && count($matches) == 1 );
            }

            $this->_isImage = $is;

        }

        return $this->_isImage;

    }


    protected function isGIF() {

        if ( is_null($this->_isGIF) ) {

            $is = false;

            $matches = [];
            if ( preg_match( '/<meta\s+property="og:type"\s+content="([^"]+)/s', $this->html(), $matches ) ) {

                if ( $matches[1] == 'article' ) {

                    // check if this is an embedded gif
                    $tweet_id = $this->getID();
                    $res = wp_remote_get( 'https://twitter.com/i/videos/tweet/'.$tweet_id );

                    if ( ! is_wp_error($res) && array_key_exists('response', $res) ) {
                        if ( $res['response']['code'] == 200 ) {
                            $this->_gifEmbedHTML = $res['body'];
                            $is = true;
                        }
                    }

                }

            }

            $this->_isGIF = $is;

        }

        return $this->_isGIF;

    }


    protected function gifEmbedHTML() {

        $value = '';

        if ( $this->isGIF() ) {
            $value = $this->_gifEmbedHTML;
        }

        return $value;

    }


}