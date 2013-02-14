<?php
/**
 * @package Term Meta
 * @version 0.1
 */
/*
Plugin Name: Term Meta
Plugin URI: http://www.alleyinteractive.com/
Description: Creates a framework to store additional meta data for taxonomy terms.
Author: Alley Interactive (Bradford Campeau-Laurion)
Version: 0.1
Author URI: http://www.alleyinteractive.com/
*/

if( !class_exists( 'Term_Meta' ) ) {

define( 'TERM_META_PATH', dirname( __FILE__ ) );

require_once( TERM_META_PATH . '/functions.php' );

class Term_Meta {

	/**
	 * Constructor to set necessary action hooks and filters
	 *
	 * @return void
	 */
	public function __construct() {
		$this->create_content_type();
 	}

	/**
	 * Create the custom content type
	 *
	 * @return void
	 */
	public function create_content_type() {
		$args = array(
			'public' => false,
			'publicly_queryable' => false,
			'exclude_from_search' => false,
			'query_var' => 'term-meta',
			'rewrite' => false,
			'show_ui' => false,
			'capability_type' => 'post',
			'hierarchical' => true,
			'has_archive' => false
		);
		register_post_type( 'term-meta', $args );
	}
	
	/**
	 * Handles getting metadata for taxonomy terms
	 * @param int $term_id
	 * @param string $meta_key
	 * @param string $meta_value optional
	 * @return bool
	 */
	public function get_term_meta( $term_id, $meta_key='', $single=false ) {
	
		// Check if this term has a post to store meta data
		$term_meta_post_id = $this->get_term_meta_post_id( $term_id );
		if ( $term_meta_post_id === false ) {
	
			// If not, exit. There is no meta data for this term at all.
			// Mimic the normal return behavior of get_post_meta
			if ( $single ) return '';
			else return array();
		
		}
		
		// Get the meta data
		return get_post_meta( $term_meta_post_id, $meta_key, $single );
	
	}
	
	/**
	 * Handles adding metadata for taxonomy terms
	 * @param int $term_id
	 * @param string $meta_key
	 * @param string $meta_value
	 * @param bool $unique optional
	 * @return bool
	 */
	public function add_term_meta( $term_id, $meta_key, $meta_value, $unique=false ) {
	
		// Check if this term already has a post to store meta data
		$term_meta_post_id = $this->get_term_meta_post_id( $term_id );
		if ( $term_meta_post_id === false ) {
	
			// If not, create the post to store the metadata
			$term_meta_post_id = $this->add_term_meta_post( $term_id );
			
			// Check for errors
			if ( $term_meta_post_id === false ) {
				
				return false;
			
			}
		
		}
		
		// Add this key/value pair as post meta data
		$result = add_post_meta( $term_meta_post_id, $meta_key, $meta_value, $unique );
		
		if ( $result === false ) {
			
			return false;
			
		} else {
			
			return true;
			
		}
		
	}
	
	/**
	 * Handles updating metadata for taxonomy terms
	 * @param int $term_id
	 * @param string $meta_key
	 * @param string $meta_value optional
	 * @return bool
	 */
	public function update_term_meta( $term_id, $meta_key, $meta_value, $meta_prev_value='' ) {
	
		// Check if this term already has a post to store meta data
		$term_meta_post_id = $this->get_term_meta_post_id( $term_id );
		if ( $term_meta_post_id === false ) {
	
			// If not, create the post to store the metadata
			$term_meta_post_id = $this->add_term_meta_post( $term_id );
			
			// Check for errors
			if ( $term_meta_post_id === false ) {
	
				return false;
			
			}
		
		}
		
		// Add this key/value pair as post meta data
		$result = update_post_meta( $term_meta_post_id, $meta_key, $meta_value, $meta_prev_value );
		
		if ( $result === false ) {
	
			return false;
			
		} else {
			
			return true;
			
		}
		
	}
	
	/**
	 * Handles deleting metadata for taxonomy terms
	 * @param int $term_id
	 * @param string $meta_key
	 * @param string $meta_value
	 * @return bool
	 */
	public function delete_term_meta( $term_id, $meta_key, $meta_value='' ) {
	
		// Get the post used for this term
		$term_meta_post_id = $this->get_term_meta_post_id( $term_id );
		
		// If no post exist, there is nothing further to do here. This is not necessarily an error.
		if( $term_meta_post_id === false ) {
			
			return false;
			
		}
	
		// Remove the meta data
		$result = delete_post_meta( $term_meta_post_id, $meta_key, $meta_value );
		
		// Check if this term has any metadata at all
		$post_terms = get_post_meta( $term_meta_post_id );
		if ( empty( $post_terms ) ) {
		
			// If not, remove the post to store the metadata to free up space in wp_posts
			$result = wp_delete_post( $term_meta_post_id, true );
			
		}
		
		return $result;
		
	}
	
	/**
	 * Handles checking if post exists and returning its ID to store taxonomy term meta data
	 * @param int $term_id
	 * @return bool
	 */
	public function get_term_meta_post_id( $term_id ) {
	
		// Check if a post exists for this term
		$query = new WP_Query( 
			array(
				'name' => 'term-meta-' . $term_id,
				'post_type' => 'term-meta'
			)
		);
		
		// Return the post ID if it exists, otherwise false
		if( $query->have_posts() ) {
		
			$query->next_post();
			return $query->post->ID;
		
		} else {
		
			return false;
			
		}
		
	}
	
	/**
	 * Handles adding a post to store taxonomy term meta data
	 * @param int $term_id
	 * @return bool
	 */
	public function add_term_meta_post( $term_id ) {
	
		// Add the skeleton post to store meta data for this taxonomy term
		$result = wp_insert_post( 
			array( 
				'post_name' => 	'term-meta-' . $term_id,
				'post_title' => 'term-meta-' . $term_id,
				'post_type' => 'term-meta',
				'post_status' => 'publish'
			)
		);
		
		// Check the result
		if ( $result != 0 ) {
			
			return $result;
			
		} else {
		
			return false;
			
		}
		
	}
	
}

// Create an instance of the class
global $term_meta;
$term_meta = new Term_Meta;

}