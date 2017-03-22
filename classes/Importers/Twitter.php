<?php 

namespace InstagramPosts\Importers;

use InstagramPosts\Consts\Fields;
// use InstagramPosts\Consts\Sources;
use \WP_Error;



class Twitter extends Importer {


    static protected $source = 'twitter'; //Sources::Instagram; //TODO
    static protected $pattern = "//";


    public function scrape( $html ) {

        return new WP_Error();

    }


    protected function get_format() {

        return 'quote';
                
    }


}