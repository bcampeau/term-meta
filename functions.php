<?php
/**
 * Retrieve the global Term Meta object
 *
 * @return object
 */
function tm_get_instance() {
	global $term_meta;
	return $term_meta;
}

/**
 * Handles getting metadata for taxonomy terms
 * @param int $term_id
 * @param string $meta_key
 * @param string $meta_value optional
 * @return bool
 */
function tm_get_term_meta( $term_id, $meta_key='', $single=false ) {	
	return tm_get_instance()->get_term_meta( $term_id, $meta_key, $single );
}

/**
 * Handles adding metadata for taxonomy terms
 * @param int $term_id
 * @param string $meta_key
 * @param string $meta_value
 * @param bool $unique optional
 * @return bool
 */
function tm_add_term_meta( $term_id, $meta_key, $meta_value, $unique=false ) {
	return tm_get_instance()->add_term_meta( $term_id, $meta_key, $meta_value, $unique );
}

/**
 * Handles updating metadata for taxonomy terms
 * @param int $term_id
 * @param string $meta_key
 * @param string $meta_value optional
 * @return bool
 */
function tm_update_term_meta( $term_id, $meta_key, $meta_value, $meta_prev_value='' ) {
	return tm_get_instance()->update_term_meta( $term_id, $meta_key, $meta_value, $meta_prev_value );
}

/**
 * Handles deleting metadata for taxonomy terms
 * @param int $term_id
 * @param string $meta_key
 * @param string $meta_value
 * @return bool
 */
function tm_delete_term_meta( $term_id, $meta_key, $meta_value='' ) {
	return tm_get_instance()->delete_term_meta( $term_id, $meta_key, $meta_value );
}