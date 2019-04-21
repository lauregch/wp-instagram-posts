<?php 

namespace SocialPosts\Scrapers;
use WP_Error;

class ScraperFactory {

    public static function create( $url ) {

        $scraper = new WP_Error();

        $classes = apply_filters( 'social_posts/scrapers', [
            __NAMESPACE__.'\Instagram',
            __NAMESPACE__.'\Twitter' 
        ]);

        foreach ( $classes as $class ) {

            $matches = [];
            if ( preg_match( $class::$url_pattern, $url, $matches ) ) {
                $scraper = new $class( $url );
            }
            
        }

        return $scraper;

    }

}