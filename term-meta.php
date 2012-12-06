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

if( !class_exists( 'Related_Links_Plus' ) ) {

class Term_Meta {

	/**
	 * Constructor to set necessary action hooks and filters
	 *
	 * @return void
	 */
	public function __construct() {
		$this->create_content_type();
		
		// Also handle saving the extended fields
		add_action( 'edited_term', array( $this, 'save_term_edit_fields'), 10, 3 );
		add_action( 'created_term', array( $this, 'save_term_edit_fields'), 10, 3 );
		
		// Enqueue required scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Filter terms to include mapped words
		add_filter( 'fm_terms_pre_match', array( $this, 'term_map' ), 10, 2 ); 
		
		// Filter term matches based on Extended Categories term parent matches
		add_filter( 'fm_terms_match', array( $this, 'match_parent_terms' ), 10, 2 ); 
		
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
			'query_var' => 'ec-term-meta',
			'rewrite' => false,
			'show_ui' => false,
			'capability_type' => 'post',
			'hierarchical' => true,
			'has_archive' => false
		);
		register_post_type( 'ec-term-meta', $args );
	}
	
	/**
	 * Add CSS and JS to admin area, hooked into admin_enqueue_scripts.
	 */
	function enqueue_scripts() {
		global $extended_categories;
		wp_enqueue_script( 'extended_categories_term_meta_script', $extended_categories->get_baseurl() . 'js/extended-categories-term-meta.js', array( 'extended_categories_script' ) );
	}
	
	/**
	 * Handles getting metadata for taxonomy terms
	 * @param int $term_id
	 * @param string $meta_key
	 * @param string $meta_value optional
	 * @return bool
	 */
	function get_term_meta( $term_id, $meta_key='', $single=false ) {
	
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
	function add_term_meta( $term_id, $meta_key, $meta_value, $unique=false ) {
	
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
	function update_term_meta( $term_id, $meta_key, $meta_value, $meta_prev_value='' ) {
	
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
	function delete_term_meta( $term_id, $meta_key, $meta_value='' ) {
	
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
	function get_term_meta_post_id( $term_id ) {
	
		// Check if a post exists for this term
		$query = new WP_Query( 
			array(
				'name' => 'ec-term-meta-' . $term_id,
				'post_type' => 'ec-term-meta'
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
	function add_term_meta_post( $term_id ) {
	
		// Add the skeleton post to store meta data for this taxonomy term
		$result = wp_insert_post( 
			array( 
				'post_name' => 	'ec-term-meta-' . $term_id,
				'post_title' => 'ec-term-meta-' . $term_id,
				'post_type' => 'ec-term-meta',
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
	 
	/**
	 * Creates the HTML template to add fields to the term add form
	 *
	 * @params object $tag
	 * @params string $taxonomy
	 * @return void
	 */
	function add_term_fields( $taxonomy ) {
		$html_template = '<div class="form-field"><label for="%s">%s</label>%s<p>%s</p></div>';
		echo $this->term_fields( $html_template, $taxonomy );
	}
	
	/**
	 * Creates the HTML template to add fields to the term edit form
	 *
	 * @params object $tag
	 * @params string $taxonomy
	 * @return void
	 */
	function edit_term_fields( $tag, $taxonomy ) {
		$html_template = '<tr class="form-field"><th scope="row" valign="top"><label for="%s">%s</label></th><td>%s<br /><span class="description">%s</span></td></tr>';
		echo $this->term_fields( $html_template, $taxonomy, $tag );
	}
	
	/**
	 * Adds fields for cross-taxonomy parent selection and term mapping, both to assist extraction, to the term add/edit forms
	 *
	 * @params string $html_template
	 * @params object $tag
	 * @params string $taxonomy
	 * @return void
	 */
	function term_fields( $html_template, $taxonomy, $tag=null ) {
		$html_out = "";
		
		// Iterate through the taxonomies and build the select field for parent term selection
		if( is_array( $this->taxonomies ) && !empty( $this->taxonomies ) ) {
	
			// If the tag parameter is set, set the current parent ID if it exists
			$parent_id = ( isset( $tag ) ) ? $this->get_term_meta( $tag->term_id, 'ec_parent', true ) : '';
			$parent_taxonomy = ( isset( $tag ) ) ? $this->get_term_meta( $tag->term_id, 'ec_parent_taxonomy', true ) : ''; 
		
			$parent_options = "<option></option>";
			foreach( $this->taxonomies as $taxonomy ) {
				
				// Get all terms for this taxonomy
				$terms = get_terms( $taxonomy, array(
					'orderby' => 'name',
					'hide_empty' => 0
				) );
				
				// Build the option list
				$option_list = "";
				foreach( $terms as $term ) {
					$option_list .= sprintf( 
						'<option value="%s" data-taxonomy="%s" %s>%s</option>',
						$term->term_id,
						$taxonomy,
						( $term->term_id == $parent_id ) ? 'selected="selected"' : '',
						$term->name
					);
				}
				
				// If there is more than one taxonomy, we should use optgroups
				if( count( $this->taxonomies ) > 1 ) {
					// Get the taxonomy label
					$tax_data = get_taxonomy( $taxonomy);
					
					// Add the optgroup
					$option_list = sprintf( 
						'<optgroup label="%s">%s</optgroup>',
						$tax_data->label,
						$option_list
					);
				}
				
				$parent_options .= $option_list;
			}
			
			$parent_select = sprintf(
				'<select class="chzn-select" name="ec_parent" id="ec_parent" data-placeholder="%s">%s</select><input type="hidden" id="ec_parent_taxonomy" name="ec_parent_taxonomy" value="%s" />',
				__('Select term parent'),
				$parent_options,
				$parent_taxonomy
			);
			
			$html_out .= sprintf(
				$html_template,
				'ec_parent',
				__( 'Parent' ),
				$parent_select,
				__( 'Add a parent term from any taxonomy to create rich associations for term extraction.' )
			);
				
		}
		
		// Get the current value for the term map, if one exists
		$term_map = ( isset( $tag ) ) ? $this->get_term_meta( $tag->term_id, 'ec_term_map', true ) : '';
		
		// Create the field to handle term mappings	
		$term_map_field = sprintf(
			'<input name="ec_term_map" id="ec_term_map" type="text" value="%s" size="40">',
			$term_map
		);
		
		$html_out .= sprintf(
			$html_template,
			'ec_term_map',
			__( 'Term Map' ),
			$term_map_field,
			__( 'Enter a comma separated list of words that should also map to this term during term extraction.' )
		);
		
		return $html_out;

	}
	
	/**
	 * Saves fields for cross-taxonomy parent selection and term mapping, both to assist extraction, to the term edit form
	 *
	 * @params object $tag
	 * @params string $taxonomy
	 * @return void
	 */
	function save_term_edit_fields( $term_id, $tt_id, $taxonomy ) {
		// If the custom term fields are present, save them
		if( array_key_exists( 'ec_parent', $_POST ) ) $this->update_term_meta( $term_id, 'ec_parent', $_POST['ec_parent'] );
		if( array_key_exists( 'ec_parent_taxonomy', $_POST ) ) $this->update_term_meta( $term_id, 'ec_parent_taxonomy', $_POST['ec_parent_taxonomy'] );
		if( array_key_exists( 'ec_term_map', $_POST ) ) $this->update_term_meta( $term_id, 'ec_term_map', $_POST['ec_term_map'] );
	}
	
	/**
	 * Handle term mapping for auto term extraction.
	 * Additional processing for Fieldmanager_Terms auto term extraction.
	 *
	 * @params object $term
	 * @params array &$term_matches
	 * @params int $match_count
	 * @return void
	 */
	function term_map( $term_name, $term_id ) {

		// See if this term has any mappings
		$term_map = $this->get_term_meta( $term_id, 'ec_term_map', true );
		
		if( $term_map != "" ) {
				
			// Split the values into an array
			$additional_terms = array_map( 'trim', explode( ",", $term_map ) );
			
			// Reassemble with the original term into a regular expression
			$additional_terms[] = $term_name;
			$term_list = implode( "|", $additional_terms );
			$term_name = sprintf( "(?:%s)", $term_list );	
		
		}
		
		return $term_name;
	 
	 }	
	
	/**
	 * Handle auto term matching for parent terms across taxonomies for posts.
	 * Additional processing for Fieldmanager_Terms auto term extraction.
	 *
	 * @params object $term
	 * @params array &$term_matches
	 * @params int $match_count
	 * @return void
	 */
	function match_parent_terms( $term_matches, $term ) {

		// See if this term has any cross-taxonomy parents
		$term_meta = $this->get_term_meta( $term->term_id );
		
		if( array_key_exists( 'ec_parent', $term_meta ) && array_key_exists( 'ec_parent_taxonomy', $term_meta ) ) {
		
			// Get the term data
			$parent_term = get_term( intval( $term_meta['ec_parent'][0] ), $term_meta['ec_parent_taxonomy'][0] );
			
			// Get the taxonomy data
			$taxonomy_data = get_taxonomy( $parent_term->taxonomy );
			
			// Add to the list of matches. Use the same match count as the child term that was actually in the text.
			$term_matches[$taxonomy_data->label][] = $parent_term->term_id;
			
			// Call this function recursively on the parent term
			$term_matches = $this->match_parent_terms( $term_matches, $parent_term );
		}
		
		return $term_matches;
	 
	 }
	
}

// Create an instance of the class
global $term_meta;
$term_meta = new Term_Meta;

}
