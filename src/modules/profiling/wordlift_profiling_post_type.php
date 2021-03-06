<?php

/**
 * Registers the profiling custom post type (from the *init* hook).
 *
 * @since 3.0.0
 */
function wl_profiling_post_type_register() {

	$labels = array(
		'name'               => _x( 'SPARQL Performance', 'as shown in the admin menu', 'wordlift' ),
		'singular_name'      => _x( 'Analysis', 'post type singular name', 'wordlift' ),
		'add_new'            => _x( 'Add New Analysis', 'Profiling', 'wordlift' ),
		'add_new_item'       => __( 'Add New Analysis', 'wordlift' ),
		'edit_item'          => __( 'Edit Analysis', 'wordlift' ),
		'new_item'           => __( 'New Analysis', 'wordlift' ),
		'all_items'          => __( 'All Analyses', 'wordlift' ),
		'view_item'          => __( 'View Analysis', 'wordlift' ),
		'search_items'       => __( 'Search in Performance Analyses', 'wordlift' ),
		'not_found'          => __( 'No Performance Analyses found', 'wordlift' ),
		'not_found_in_trash' => __( 'No Performance Analyses found in the Trash', 'wordlift' ),
		'parent_item_colon'  => '',
		'menu_name'          => __( 'Performance Analyses', 'wordlift' )
	);

	$args = array(
		'labels'              => $labels,
		'description'         => 'Performance Analyses ',
		'show_in_nav_menus'   => true,
		'show_ui'             => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => false,
		'menu_position'       => 20, // after the pages menu.
		'supports'            => array( 'title', 'comments' ),
		'has_archive'         => true
	);

	register_post_type( WL_PROFILING_POST_TYPE, $args );
}

add_action( 'init', 'wl_profiling_post_type_register' );


/**
 * Removes the *author* column and adds the *duration* column do the *profiling* post type. This function is called by
 * the manage_*profiling*_posts_columns hook.
 *
 * @since 3.0.0
 *
 * @param array $columns An array of existing columns.
 *
 * @return array The new array of columns.
 */
function wl_profiling_posts_columns( $columns ) {

	unset( $columns['author'] );
	unset( $columns['comments'] );
	unset( $columns['date'] );

	return array_merge( $columns, array(
		'duration' => __( 'Duration', 'wordlift ' ),
		'comments' => __( 'Comments', 'wordlift ' ),
		'date'     => __( 'Date', 'wordlift ' )
	) );

}

add_filter( 'manage_' . WL_PROFILING_POST_TYPE . '_posts_columns', 'wl_profiling_posts_columns' );


/**
 * Get the value for columns of the *profiling* type, such as *duration*. The value is echoed. This function is called
 * by the manage_*profiling*_posts_custom_column hook.
 *
 * @since 3.0.0
 *
 * @param string $column The column name (as defined in *wl_profiling_posts_columns*).
 * @param int $post_id   The post Id.
 */
function wl_profiling_posts_custom_column( $column, $post_id ) {

	switch ( $column ) {
		case 'duration':
			echo number_format( wl_profiling_get_duration( $post_id ) / 1000, 2 ) . ' s.';
			break;
		default:
	}

}
add_action( 'manage_' . WL_PROFILING_POST_TYPE . '_posts_custom_column' , 'wl_profiling_posts_custom_column', 10, 2 );

/**
 * Create a *profiling* post with the provided data.
 *
 * @since 3.0.0
 *
 * @param string $query The SPARQL query.
 * @param int $duration The query duration.
 *
 * @return int|false The post Id or false in case of error.
 */
function wl_profiling_insert( $query, $duration ) {

	// Create the post and check success (0 means failure).
	if ( 0 === ( $post_id = wp_insert_post( array(
			'post_status'  => 'private',
			'post_type'    => WL_PROFILING_POST_TYPE
		) ) )
	) {
		return false; // return false in case of error.
	};

	// Add the query.
	add_post_meta( $post_id, WL_PROFILING_SPARQL_QUERY_META_KEY, $query);

	// Add the duration.
	add_post_meta( $post_id, WL_PROFILING_DURATION_META_KEY, $duration );

	return $post_id;

}


/**
 * Get the *duration* for the profiling analysis with the specified post Id.
 *
 * @since 3.0.0
 *
 * @param int $post_id The post Id.
 * @return int The duration.
 */
function wl_profiling_get_duration( $post_id ) {

	return get_post_meta( $post_id, WL_PROFILING_DURATION_META_KEY, true );

}


/**
 * Get the *SPARQL query* for the profiling analysis with the specified post Id.
 *
 * @since 3.0.0
 *
 * @param int $post_id The post Id.
 * @return string The SPARQL query.
 */
function wl_profiling_get_sparql_query( $post_id ) {

	return get_post_meta( $post_id, WL_PROFILING_SPARQL_QUERY_META_KEY, true );

}


/**
 * Adds a box to the profiling analysis custom post type to display the SPARQL Query. This function is called by the
 * *add_meta_boxes* hook. It calls the *wl_profiling_sparql_query_meta_box_callback* function to display the box
 * contents.
 *
 * @since 3.0.0
 */
function wl_profiling_add_meta_box() {

	add_meta_box(
		'wl_profiling_sparql_query',
		__( 'SPARQL Query', 'wordlift' ),
		'wl_profiling_meta_box_callback',
		WL_PROFILING_POST_TYPE,
		'normal', // The part of the page where the edit screen section should be shown.
		'high'    // The priority within the context where the boxes should show.
	);

}

add_action( 'add_meta_boxes', 'wl_profiling_add_meta_box' );


/**
 * Echoes the SPARQL query for the provided post.
 *
 * @param object $post The post.
 */
function wl_profiling_meta_box_callback( $post ) {

	$formatted_duration = number_format( wl_profiling_get_duration( $post->ID ) / 1000, 2 );
	$query_h            = esc_html( wl_profiling_get_sparql_query( $post->ID ) );

	printf( __( 'The following query took %s s.', 'wordlift'), $formatted_duration );

?>
	<div>
		<pre><?php echo $query_h; ?></pre>
	</div>
<?php
}


/**
 * Removes unnecessary metaboxes from the profiling post type edit screen. This function is called by the *admin_menu*
 * hook.
 *
 * @since 3.0.0
 */
function wl_profiling_remove_meta_boxes() {

	// See http://codex.wordpress.org/Function_Reference/remove_meta_box
	remove_meta_box( 'commentstatusdiv', WL_PROFILING_POST_TYPE, 'normal' );

}
add_action( 'admin_menu', 'wl_profiling_remove_meta_boxes' );