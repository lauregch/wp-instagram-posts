<?php 

namespace InstagramPosts\Importers;

use InstagramPosts\Consts\Fields;
// use InstagramPosts\Consts\Sources;
use \WP_Error;



class Instagram extends Importer {


    static protected $source = 'instagram'; //Sources::Instagram; //TODO
    static protected $pattern = "/^https?:\/\/www\.instagram\.com\/p\/((?:[0-9]|[A-zA-Z]|_|-)+)\/?/";


    public function scrape( $url, $html ) {

        $data = new WP_Error();

        $matches = [];
        if ( preg_match( "/window\._sharedData\s=\s({.*});\s?<\/script>/", $html, $matches ) ) {

            $jsdata = json_decode( $matches[1] );
            @$igmdata = $jsdata->entry_data->PostPage[0]->media;
            
            if ( $igmdata ) {

                $this->raw_data = $igmdata;

                $data = [
                    Fields::ID => $igmdata->id,
                    Fields::Content => isset( $igmdata->caption ) ? $igmdata->caption : '',
                    Fields::Date => date( 'Y-m-d H:i:s', $igmdata->date ),
                    Fields::Image => $igmdata->display_src,
                    Fields::AuthorID => $igmdata->owner->id,
                    Fields::AuthorUserName => $igmdata->owner->username,
                    Fields::AuthorRealName => $igmdata->owner->full_name,
                    Fields::AuthorAvatarURL => $igmdata->owner->profile_pic_url
                ];

                if ( $igmdata->is_video ) {
                    $data[ Fields::Video ] = $igmdata->video_url;
                }
                
            }

        }

        return $data;

    }


    protected function get_format() {

        if ( is_null( $this->raw_data ) ) {
            return false;
        }

        return ( $this->raw_data->is_video ? 'video' : 'image' );

        //TODO gallery
        
    }


}