<?php 

namespace SocialPosts\Scrapers;

use SocialPosts\Consts\Fields;
// use SocialPosts\Consts\Sources;
use \WP_Error;



class Instagram extends Scraper {


    static protected $source = 'instagram'; //Sources::Instagram; //TODO
    static public $url_pattern = "/^https?:\/\/www\.instagram\.com\/p\/((?:[0-9]|[A-zA-Z]|_|-)+)\/?/";

    private $_sharedData = '';
    private $_mainMediaData = '';

    private $_isVideo = null;
    private $_isGallery = null;


    private function sharedData() {

        if ( ! $this->_sharedData ) {

            $matches = [];
            if ( preg_match( "/window\._sharedData\s=\s({.*});\s?<\/script>/", $this->html(), $matches ) ) {
                $this->_sharedData = json_decode( $matches[1] );
            }

        }

        return $this->_sharedData;

    }


    private function mainMediaData() {

        if ( ! $this->_mainMediaData ) {
            $this->_mainMediaData = $this->sharedData()->entry_data->PostPage[0]->graphql->shortcode_media;
        }

        return $this->_mainMediaData;

    }


    public function getPostFormat() {

        if ( $this->isVideo() ) $format = 'video';
        elseif ( $this->isGallery() ) $format = 'gallery';
        else $format  = 'image';
        
    }


    protected function getID() {
        return $this->mainMediaData()->id;
    }

    protected function getTitle() {
        return false;
    }

    protected function getContent() {

        $content = '';
        $edges = $this->mainMediaData()->edge_media_to_caption->edges;

        if ( is_array($edges) && ! empty($edges) ) {

            $content = $edges[0]->node->text;

            // Add links to hashtags
            $content = html_entity_decode( $content, ENT_QUOTES );
            $content = preg_replace( '/#(\w+)/', '<a href="https://www.instagram.com/explore/tags/$1" target="_blank">$0</a>',  $content );

            // Add links to profiles
            $content = preg_replace( '/@(\w+)/', '<a href="https://www.instagram.com/$1" target="_blank">$0</a>',  $content );

        }

        return $content;

    }

    protected function getDate() {
        $timestamp = $this->mainMediaData()->taken_at_timestamp;
        return date( 'Y-m-d H:i:s', $timestamp );
    }

    protected function getImageURL() {
        return $this->mainMediaData()->display_url;
    }

    protected function getVideoURL() {

        if ( $this->isVideo() ) {
            return $this->mainMediaData()->video_url;
        }
        return false;

    }

    protected function getVideoEmbed() {

        if ( $this->isVideo() ) {
            return trim( $this->_url, '/' ) . '/embed/';
        }
        return false;

    }

    protected function getGallery() {

        if ( $this->isGallery() ) {

            $children = $this->mainMediaData()->edge_sidecar_to_children->edges;

            $gallery = [];
            foreach ( $children as $child ) {
                $item = [ Fields::ImageURL => $child->node->display_url ];
                if ( $child->node->is_video ) {
                    $item[ Fields::VideoURL ] = $child->node->video_url;
                }
                $gallery[] = $item;
            }
            return $gallery;

        }

        return false;

    }

    protected function getAuthorID() {
        return $this->mainMediaData()->owner->id;
    }

    protected function getAuthorUserName() {
        return $this->mainMediaData()->owner->username;
    }

    protected function getAuthorRealName() {
        return $this->mainMediaData()->owner->full_name;
    }

    protected function getAuthorAvatarURL() {
        return $this->mainMediaData()->owner->profile_pic_url;
    }


    private function isVideo() {

        if ( is_null( $this->_isVideo ) ) {

            $is = false;

            if ( property_exists( $this->mainMediaData(), 'is_video' ) ) {
                $is = $this->mainMediaData()->is_video;
            }

            $this->_isVideo = $is;

        }

        return $this->_isVideo;

    }


    private function isGallery() {

        if ( is_null( $this->_isGallery ) ) {

            $is = false;

            if ( property_exists( $this->mainMediaData(), 'edge_sidecar_to_children' ) ) {
                $children = $this->mainMediaData()->edge_sidecar_to_children->edges;
                $is = is_array( $children ) && ! empty( $children );
            }

            $this->_isGallery = false;

        }

        return $this->_isGallery;

    }


}