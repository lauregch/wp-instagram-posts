<?php

namespace SocialPosts\Scrapers;
use WP_Error;


abstract class Scraper {


    static protected $source;
    static public $url_pattern;

    protected $_url;
    protected $_html = false; 
    protected $_data = [];
    protected $_fields = [];


    public function __construct( $url ) {

        $this->_url = trim( $url );

        $fieldsClass = new \ReflectionClass( 'SocialPosts\Consts\Fields' );
        $this->_fields = $fieldsClass->getConstants();

    }


    public function __call( $name, $args ) {

        $field = preg_replace( '/get(\S+)/', '$1', $name );

        if ( in_array( $field, $this->_fields ) ) {
            return $this->$name( $args );
        }
        else {
            throw new Exception( 'The specified method or method alias is undefined in the current context' );
        }

    }


    public function getPostFormat() {
        return false;
    }


    public function get( $field ) {

        $value = new WP_Error();

        if ( ! array_key_exists( $field, $this->_data ) ) {

            if ( in_array( $field, $this->_fields ) ) {

                $funcName = "get{$field}";
                $value = $this->{$funcName}();

                if ( ! is_wp_error($value) ) {
                    $this->_data[ $field ] = $value;
                }
            }

        }
        else {
            $value = $this->_data[ $field ];
        }

        return $value;

    }


    public function scrapeAll() {

        $remaining_fields = array_diff( $this->_fields, array_keys($this->_data) );

        foreach ( $remaining_fields as $field ) {
            $this->get( $field );
        }

    }


    public function html() {

        if ( ! $this->_html ) {

            $res = wp_remote_get( $this->_url );
            
            if ( $res && ! is_wp_error($res) && $res['response']['code']==200 ) {
                $this->_html = $res['body'];
            }

        }

        return $this->_html;

    }


    public function data() {
        return $this->_data;
    }


    public function url() {
        return $this->_url;
    }


    public function source() {
        return static::$source;
    }


}