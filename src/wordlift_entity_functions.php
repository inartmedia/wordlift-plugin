<?php
/**
 * This file contains methods related to entities.
 */

/**
 * Build the entity URI given the entity's post.
 *
 * @uses wl_sanitize_uri_path to sanitize the post title.
 * @uses wl_configuration_get_redlink_dataset_uri to get the dataset base URI.
 *
 * @param int $post_id The post ID
 *
 * @return string The URI of the entity
 */
function wl_build_entity_uri( $post_id ) {

	// Get the post.
	$post = get_post( $post_id );

	if ( null === $post ) {

		wl_write_log( "wl_build_entity_uri : error [ post ID :: $post_id ][ post :: null ]" );

		return;
	}

	// Create an ID given the title.
	$path = wl_sanitize_uri_path( $post->post_title );

	// If the path is empty, i.e. there's no title, use the post ID as path.
	if ( empty( $path ) ) {
		$path = "id/$post->ID";
	}

	// Create the URL (dataset base URI has a trailing slash).
	$url = sprintf( '%s/%s/%s', wl_configuration_get_redlink_dataset_uri(), $post->post_type, $path );

	wl_write_log( "wl_build_entity_uri [ post_id :: $post->ID ][ type :: $post->post_type ][ title :: $post->post_title ][ url :: $url ]" );

	return $url;
}

/**
 * Get the entity URI of the provided post.
 *
 * @uses wl_build_entity_uri to create a new URI if the entity doesn't have an URI yet.
 * @uses wl_set_entity_uri to set a newly create URI.
 *
 * @param int $post_id The post ID.
 *
 * @return string|null The URI of the entity or null if not configured.
 */
function wl_get_entity_uri( $post_id ) {
	$uri = get_post_meta( $post_id, WL_ENTITY_URL_META_NAME, true );
	$uri = utf8_encode( $uri );

	// Set the URI if it isn't set yet.
	$post_status = get_post_status( $post_id );
	if ( empty( $uri ) && 'auto-draft' !== $post_status && 'revision' !== $post_status ) {
		$uri = wl_build_entity_uri( $post_id ); //  "http://data.redlink.io/$user_id/$dataset_id/post/$post->ID";
		wl_set_entity_uri( $post_id, $uri );
	}

	return $uri;
}

/**
 * Save the entity URI for the provided post ID.
 *
 * @param int $post_id The post ID.
 * @param string $uri The post URI.
 *
 * @return bool True if successful, otherwise false.
 */
function wl_set_entity_uri( $post_id, $uri ) {

	wl_write_log( "wl_set_entity_uri [ post id :: $post_id ][ uri :: $uri ]" );

	$uri = utf8_decode( $uri );

	return update_post_meta( $post_id, WL_ENTITY_URL_META_NAME, $uri );
}


/**
 * Get the entity type URIs associated to the specified post.
 *
 * @since 3.0.0
 *
 * @param int $post_id The post ID.
 *
 * @return array An array of terms.
 */
function wl_get_entity_rdf_types( $post_id ) {
	return get_post_meta( $post_id, 'wl_entity_type_uri' );
}

/**
 * Set the types for the entity with the specified post ID.
 *
 * @param int $post_id The entity post ID.
 * @param array $type_uris An array of type URIs.
 */
function wl_set_entity_rdf_types( $post_id, $type_uris = array() ) {

	// Avoid errors because of null values.
	if ( is_null( $type_uris ) ) {
		$type_uris = array();
	}

	// Ensure there are no duplicates.
	$type_uris = array_unique( $type_uris );

	delete_post_meta( $post_id, 'wl_entity_type_uri' );
	foreach ( $type_uris as $type_uri ) {
		if ( empty( $type_uri ) ) {
			continue;
		}
		add_post_meta( $post_id, 'wl_entity_type_uri', $type_uri );
	}
}

// TODO: this method must be eliminated in favor of the new *wl_schema_get_property_expected_type*
/**
 * Retrieve entity property type, starting from the schema.org's property name
 * or from the WL_CUSTOM_FIELD_xxx name.
 *
 * @param $property_name as defined by schema.org or WL internal constants
 *
 * @return array containing type(s) or null (in case of error or no types).
 */
function wl_get_meta_type( $property_name ) {

	// Property name must be defined.
	if ( ! isset( $property_name ) || is_null( $property_name ) ) {
		return null;
	}
        
        // store eventual schema name in  different variable
        $property_schema_name = wl_build_full_schema_uri_from_schema_slug( $property_name );

	// Loop over custom_fields
	$entity_terms = wl_entity_taxonomy_get_custom_fields();

	foreach ( $entity_terms as $term ) {
		foreach ( $term as $wl_constant => $field ) {

			// Is this the predicate we are searching for?
			if ( isset( $field['type'] ) ) {
				$found_predicate = isset( $field['predicate'] ) && ( $field['predicate'] == $property_schema_name );
				$found_constant  = ( $wl_constant == $property_name );
				if ( $found_predicate || $found_constant ) {
					return $field['type'];
				}
			}
		}
	}

	return null;
}

/**
 * Retrieve entity property constraints, starting from the schema.org's property name
 * or from the WL_CUSTOM_FIELD_xxx name.
 *
 * @param $property_name as defined by schema.org or WL internal constants
 *
 * @return array containing constraint(s) or null (in case of error or no constraint).
 */
function wl_get_meta_constraints( $property_name ) {

    	// Property name must be defined.
	if ( ! isset( $property_name ) || is_null( $property_name ) ) {
		return null;
	}
        
        // store eventual schema name in  different variable
        $property_schema_name = wl_build_full_schema_uri_from_schema_slug( $property_name );
        
	// Get WL taxonomy mapping.
	$types = wl_entity_taxonomy_get_custom_fields();

	// Loop over types
	foreach ( $types as $type ) {
		// Loop over custom fields of this type
		foreach ( $type as $property => $field ) {
			if ( isset( $field['constraints'] ) && ! empty( $field['constraints'] ) ) {
				// Is this the property we are searhing for?
				if ( ( $property == $property_name ) || ( $field['predicate'] == $property_schema_name ) ) {
					return $field['constraints'];
				}
			}
		}
	}

	return null;
}

/**
 * Retrieve entity type custom fields
 *
 * @param int $entity_id id of the entity, if any
 *
 * @return mixed if $entity_id was specified, return custom_fields for that entity's type. Otherwise returns all custom_fields
 */
function wl_entity_taxonomy_get_custom_fields( $entity_id = null ) {

	if ( is_null( $entity_id ) ) {
		// Return all custom fields.
		// Get taxonomy terms
		$terms = get_terms( WL_ENTITY_TYPE_TAXONOMY_NAME, array( 'hide_empty' => 0 ) );
		if ( is_wp_error( $terms ) ) {
			return null;
		}

		$custom_fields = array();
		foreach ( $terms as $term ) {
			// Get custom_fields
			$term_options = wl_entity_type_taxonomy_get_term_options( $term->term_id );
			$custom_fields[ $term_options['uri'] ] = $term_options['custom_fields'];
		}

		return $custom_fields;
	} else {
		// Return custom fields for this specific entity's type.
		$type = wl_entity_type_taxonomy_get_type( $entity_id );

		return $type['custom_fields'];
	}
}