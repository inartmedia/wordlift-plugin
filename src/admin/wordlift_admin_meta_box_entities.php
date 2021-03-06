<?php

/**
 * This file provides methods and functions to generate entities meta-boxes in the admin UI.
 */

/**
 * Adds the entities meta box (called from *add_meta_boxes* hook).
 *
 * @param string $post_type The type of the current open post.
 */
function wl_admin_add_entities_meta_box( $post_type ) {
	wl_write_log( "wl_admin_add_entities_meta_box [ post type :: $post_type ]" );

	// Add meta box for related entities (separated from the others for historical reasons)
	add_meta_box(
		'wordlift_entities_box', __( 'Wordlift', 'wordlift' ), 'wl_entities_box_content', $post_type, 'side', 'high'
	);

	// Add meta box for specific type of entities
	$entity_id   = get_the_ID();
	$entity_type = wl_entity_type_taxonomy_get_type( $entity_id );
        
	if ( isset( $entity_id ) && is_numeric( $entity_id ) && isset( $entity_type['custom_fields'] ) ) {

		// In some special case, properties must be grouped in one metabox (e.g. coordinates)
		$metaboxes         = wl_entities_metaboxes_group_properties_by_input_field( $entity_type['custom_fields'] );
		$simple_metaboxes  = $metaboxes[0];
		$grouped_metaboxes = $metaboxes[1];

		// Loop over simple entity properties
		foreach ( $simple_metaboxes as $key => $property ) {
                        
                        // Don't present to the user the full schema name, just the slug
                        $property_slug_name = explode( '/', $property['predicate'] );
                        $property_slug_name = end( $property_slug_name );
                        
			// Metabox title
			$title = __( 'Edit', 'wordlift' ) . ' ' . __( $property_slug_name, 'wordlift' );

			// Info passed to the metabox
			$info         = array();
			$info[ $key ] = $property;

			$unique_metabox_name = uniqid( 'wl_metabox_' );

			add_meta_box(
				$unique_metabox_name, $title, 'wl_entities_' . $property['type'] . '_box_content', $post_type, 'side', 'default', $info
			);
		}

		// Loop over grouped properties
		foreach ( $grouped_metaboxes as $key => $property ) {

			// Metabox title
			$title = __( 'Edit', 'wordlift' ) . ' ' . __( $key, 'wordlift' );

			$unique_metabox_name = uniqid( 'wl_metabox_' );

			add_meta_box(
				$unique_metabox_name, $title, 'wl_entities_' . $key . '_box_content', $post_type, 'side', 'default'
			);

		}
	}
}

/**
 * Separes metaboxes in simple and grouped (called from *wl_admin_add_entities_meta_box*).
 *
 * @param array $custom_fields Information on the entity type.
 */
function wl_entities_metaboxes_group_properties_by_input_field( $custom_fields ) {

	$simple_properties  = array();
	$grouped_properties = array();

	// Loop over possible entity properties
	foreach ( $custom_fields as $key => $property ) {

		// Check presence of predicate and type
		if ( isset( $property['predicate'] ) && isset( $property['type'] ) ) {

			// Check if input_field is defined
			if ( isset( $property['input_field'] ) && $property['input_field'] !== '' ) {

				$grouped_key = $property['input_field'];

				// Update list of grouped properties
				$grouped_properties[ $grouped_key ][ $key ] = $property;

			} else {

				// input_field not defined, add simple metabox
				$simple_properties[ $key ] = $property;
			}
		}
	}

	return array( $simple_properties, $grouped_properties );
}

add_action( 'add_meta_boxes', 'wl_admin_add_entities_meta_box' );

/**
 * Displays the meta box contents (called by *add_meta_box* callback).
 *
 * @param WP_Post $post The current post.
 */
function wl_entities_box_content( $post ) {
    
        // Store referenced entity ids
	$referenced_entity_ids = array();

	wl_write_log( "wl_entities_box_content [ post id :: $post->ID ]" );
	// Angularjs edit-post widget wrapper
	echo '<div id="wordlift-edit-post-outer-wrapper"></div>';
	// Angularjs edit-post widget classification boxes configuration
	$classification_boxes = unserialize( WL_CORE_POST_CLASSIFICATION_BOXES );
	// Add selected entities to classification_boxes
	foreach ( $classification_boxes as $i => $box ) {
		// Build the proper relation name
		$relation_name = $box['id'];
		
		wl_write_log( "Going to related of $relation_name" );
    
		// Get entity ids related to the current post for the given relation name
		$entity_ids = wl_core_get_related_post_and_entities( $post->ID, $relation_name );
		// Add as referenced entities
		$referenced_entity_ids = array_merge( $referenced_entity_ids, $entity_ids);

		// Transform entity ids array in entity uris array
		array_walk($entity_ids, function(&$entity_id) {
    		// Retrieve the entity uri for the given entity id
    		$entity_id = wl_get_entity_uri( $entity_id );
		});
		
		// Enhance current box selected entities
		$classification_boxes[ $i ]['selectedEntities'] = $entity_ids;
	}
	// Json encoding for classification boxes structure
	$classification_boxes = json_encode( $classification_boxes );
	// Retrievies all referenced entities performing a Wp_Query 
	// if there is at least one referenced entity id
        $referenced_entities = array();
        if ( !empty( $referenced_entity_ids ) ){
            $args = array(
                    'post_status' => 'any',
                    'post__in'    => array_unique( $referenced_entity_ids ),
                    'post_type'   => 'entity'
                    );
                    $query            = new WP_Query( $args );
                    $referenced_entities = $query->get_posts();
            }
            // Build the entity storage object
        $referenced_entities_obj = array();
        foreach ( $referenced_entities as $related_entity ) {
            $entity = wl_serialize_entity( $related_entity );
            $referenced_entities_obj[ $entity['id'] ] = $entity;
        }

        $referenced_entities_obj = empty($referenced_entities_obj) ? 
            '{}' : json_encode( $referenced_entities_obj );
		
		$default_thumbnail_path = WL_DEFAULT_THUMBNAIL_PATH;

	echo <<<EOF
    <script type="text/javascript">
        jQuery( function() {
        	if ('undefined' == typeof window.wordlift) {
            	window.wordlift = {}
            	window.wordlift.entities = {}  		
        	}

        	window.wordlift.classificationBoxes = $classification_boxes;
        	window.wordlift.entities = $referenced_entities_obj;
        	window.wordlift.currentPostId = $post->ID;
			window.wordlift.defaultThumbnailPath = '$default_thumbnail_path';


        });
    </script>
EOF;
}

/**
 * Displays the date meta box contents (called by *add_meta_box* callback).
 *
 * @param WP_Post $post The current post.
 * @param $info Array The custom_field the method must manage.
 */
function wl_entities_date_box_content( $post, $info ) {

	// Which meta/custom_field are we managing?
	$custom_field = $info['args'];
	$meta_name    = ( array_keys( $custom_field ) );
	$meta_name    = $meta_name[0];

	// Include dateTimePicker on page
	wp_enqueue_style(
		'datetimepickercss', plugins_url( 'js-client/datetimepicker/jquery.datetimepicker.css', __FILE__ )
	);
	wp_enqueue_script(
		'datetimepickerjs', plugins_url( 'js-client/datetimepicker/jquery.datetimepicker.js', __FILE__ )
	);

	// Set nonce
	wl_echo_nonce( $meta_name );

	$date = get_post_meta( $post->ID, $meta_name, true );
	$date = esc_attr( $date );

	// Give the timepicker the date in its favourite format.
	if ( ! empty( $date ) ) {
		$pickerDate = date( 'Y/m/d H:i', strtotime( $date ) );
	}

	// Two input fields, one for the datetimepicker and another to store the time in the required format
	echo '<input type="text" id="' . $meta_name . '" value="' . $pickerDate . '" style="width:100%" />';
	echo '<input type="hidden" id="' . $meta_name . '_hidden" name="wl_metaboxes[' . $meta_name . ']" value="' . $date . '" style="width:100%" />';

	echo "<script type='text/javascript'>
    $ = jQuery;
    $(document).ready(function() {
    
        var lastDateTimePickerClicked;

        $('#" . $meta_name . "').datetimepicker({
            onChangeDateTime:function(dp, input){
                // format must be: 'YYYY-MM-DDTHH:MM:SSZ' from '2014/11/21 04:00'
                var currentDate = input.val();
                currentDate = currentDate.replace(/(\d{4})\/(\d{2})\/(\d{2}) (\d{2}):(\d{2})/,'$1-$2-$3T$4:$5:00Z')
                // store value to save in the hidden input field
                $('#" . $meta_name . "_hidden').val( currentDate );
            }
        });
    });
    </script>";
}

/**
 * Displays the string meta box contents (called by *add_meta_box* callback).
 *
 * @param WP_Post $post The current post.
 * @param $info Array The custom_field the method must manage.
 */
function wl_entities_string_box_content( $post, $info ) {

	// Which meta/custom_field are we managing?
	$custom_field = $info['args'];
	$meta_name    = ( array_keys( $custom_field ) );
	$meta_name    = $meta_name[0];

	// Set nonce
	wl_echo_nonce( $meta_name );

	$default = get_post_meta( $post->ID, $meta_name, true );

	echo '<input type="text" id="' . $meta_name . '" name="wl_metaboxes[' . $meta_name . ']" value="' . $default . '" style="width:100%" />';
}

/**
 * Displays the coordinates meta box contents (called by *add_meta_box* callback).
 *
 * @param WP_Post $post The current post.
 */
function wl_entities_coordinates_box_content( $post ) {

	// Add leaflet css and library.
	wp_enqueue_style(
		'leaflet_css', plugins_url( 'bower_components/leaflet/dist/leaflet.css', __FILE__ )
	);
	wp_enqueue_script(
		'leaflet_js', plugins_url( 'bower_components/leaflet/dist/leaflet.js', __FILE__ )
	);

	// Set nonce for both meta (latitude and longitude)
	wl_echo_nonce( WL_CUSTOM_FIELD_GEO_LATITUDE );
	wl_echo_nonce( WL_CUSTOM_FIELD_GEO_LONGITUDE );

	// Get coordinates
	$coords    = wl_get_coordinates( $post->ID );
	$latitude  = $coords['latitude'];
	$longitude = $coords['longitude'];

	// Default coords values [0, 0]
	if ( ! isset( $longitude ) || ! is_numeric( $longitude ) ) {
		$longitude = 0.0;
	}
	if ( ! isset( $latitude ) || ! is_numeric( $latitude ) ) {
		$latitude = 0.0;
	}

	// Default zoom value
	if ( $latitude == 0.0 || $longitude == 0.0 ) {
		$zoom = 1;  // Choose from a world panoramic
	} else {
		$zoom = 9;  // Close up view
	}

	// Print input fields
	echo '<label for="wl_place_lat">' . __( 'Latitude', 'wordlift' ) . '</label>';
	echo '<input type="text" id="wl_place_lat" name="wl_metaboxes[' . WL_CUSTOM_FIELD_GEO_LATITUDE . ']" value="' . $latitude . '" style="width:100%" />';

	echo '<label for="wl_place_lon">' . __( 'Longitude', 'wordlift' ) . '</label>';
	echo '<input type="text" id="wl_place_lon" name="wl_metaboxes[' . WL_CUSTOM_FIELD_GEO_LONGITUDE . ']" value="' . $longitude . '" style="width:100%" />';

	// Show Leaflet map to pick coordinates
	echo "<div id='wl_place_coords_map'></div>";
	echo "<script type='text/javascript'>
    $ = jQuery;
    $(document).ready(function(){
        $('#wl_place_coords_map').width('100%').height('200px');
        var wlMap = L.map('wl_place_coords_map').setView([$latitude, $longitude], $zoom);
    
        L.tileLayer( 'http://{s}.tile.osm.org/{z}/{x}/{y}.png',
            { attribution: '&copy; <a href=http://osm.org/copyright>OpenStreetMap</a> contributors'}
        ).addTo( wlMap );
        
        var marker = L.marker([$latitude, $longitude]).addTo( wlMap );
    
        function refreshCoords(e) {
            $('#wl_place_lat').val( e.latlng.lat );
            $('#wl_place_lon').val( e.latlng.lng );
            marker.setLatLng( e.latlng )
        }

        wlMap.on('click', refreshCoords);
    });
    </script>";
}

/**
 * Displays jQuery autocomplete in a meta box, to assign an entity as property value (e.g. location of an Event).
 * The assigned entity can also be created on the fly.
 *
 * @param WP_Post $post The current post.
 * @param $info Array The custom_field the method must manage.
 */
function wl_entities_uri_box_content( $post, $info ) {

	// Which meta/custom_field are we managing?
	$custom_field = $info['args'];
	$meta_name    = ( array_keys( $custom_field ) );
	$meta_name    = $meta_name[0];

	// Which type of entity is object?
	if ( isset( $custom_field[ $meta_name ]['constraints']['uri_type'] ) ) {
		// Specific type (e.g. Place, Event, ecc.)
		$expected_type = $custom_field[ $meta_name ]['constraints']['uri_type'];
	} else {
		// Any entity
		$expected_type = null;
	}
        
	// Set Nonce
	wl_echo_nonce( $meta_name );

	// Get default value, if any
	$defaultEntity = get_post_meta( $post->ID, $meta_name, true );

	// Is there a value?
	if ( $defaultEntity !== '' ) {
		// Is the value an ID or a string?
		if ( is_numeric( $defaultEntity ) ) {
			$defaultEntity      = get_post( $defaultEntity );
			$defaultEntity->uri = wl_get_entity_uri( $defaultEntity->ID );
		} else {
			// Is the entity external or internal?
			$defaultEntityTmp = wl_get_entity_post_by_uri( $defaultEntity );
			if ( is_null( $defaultEntityTmp ) ) {
				// external entity
				$defaultEntity = array(
					'uri'        => $defaultEntity,
					'post_title' => $defaultEntity
				);
			} else {
				// internal entity
				$defaultEntity      = $defaultEntityTmp;
				$defaultEntity->uri = wl_get_entity_uri( $defaultEntity->ID );
			}
		}
	}

	// Search entities of the expected type
	$args       = array(
		'posts_per_page'             => - 1,
		'orderby'                    => 'RECENCYYYYYYYY',
		'post_type'                  => WL_ENTITY_TYPE_NAME,
		WL_ENTITY_TYPE_TAXONOMY_NAME => $expected_type
	);

        $candidates = get_posts( $args );

	// Write Autocomplete selection
	if ( count( $candidates ) > 0 ) {
            
                $autocomplete_visible_input_id = 'autocompleteEntity-' . $meta_name;
                $autocomplete_hidden_input_id = 'autocompleteEntityHidden-' . $meta_name;
                $autocomplete_create_new_input_id = 'autocompleteCreateNew-' . $meta_name;
            
		// Input to show the autocomplete options
		echo '<input id="' . $autocomplete_visible_input_id . '" style="width:100%" >';
		// Input to store the actual chosen values ( autocomplete quirks... )
		echo '<input type="hidden" id="' . $autocomplete_hidden_input_id . '" name="wl_metaboxes[' . $meta_name . ']">';
		// Input to create new entity (insert uri or just give a name)
		$placeholder = __( 'Insert uri or just a name', 'wordlift' );
		echo '<input id="' . $autocomplete_create_new_input_id . '" placeholder="' . $placeholder . '" style="width:100%" >';


		// Add jQuery Autocomplete
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		// Filter $candidates to only contain id, name and uri
		$simpleCandidates = array_map( function ( $p ) {
			return array(
				'value' => wl_get_entity_uri( $p->ID ),
				'label' => $p->post_title
			);
		}, $candidates );

		// Add null value (to delete location)
		$nullCandidate = array(
			'value' => '',
			'label' => __( '< no entity >', 'wordlift' )
		);
		array_unshift( $simpleCandidates, $nullCandidate );

		// Add null value (to delete location)
		$newCandidate = array(
			'value' => '§',
			'label' => __( '< add new entity >', 'wordlift' )
		);
		array_unshift( $simpleCandidates, $newCandidate );

		// Add to Autocomplete available place
                $entities_list_name = 'availableEntities' . $meta_name;
		wp_localize_script( 'jquery-ui-autocomplete', $entities_list_name,
			array(
				'list'    => $simpleCandidates,
				'default' => $defaultEntity
			)
		);
                
		echo "<script type='text/javascript'>
        $ = jQuery;
        $(document).ready(function() {
            var availableEntities = " . $entities_list_name . "
            var selector = '#" . $autocomplete_visible_input_id . "';               // to display labels
            var createNewSelector = '#" . $autocomplete_create_new_input_id . "';   // to insert new entitiy
            var hiddenSelector = '#" . $autocomplete_hidden_input_id . "';   // to contain the value to be saved
            
            // 'create new' input
            $(createNewSelector).hide()     // Starts hidden
                .change( function(){        // keyboard event
                    $(hiddenSelector).val( $(this).val() );
                });
                
            // Default label and value
            if( availableEntities.default.hasOwnProperty( 'uri' ) ){
                $(selector).val( availableEntities.default.post_title );
                $(hiddenSelector).val( availableEntities.default.uri );
            }
            
            // Init autocomplete
            $(selector).autocomplete({
                minLength: 0,
                source: availableEntities.list,
                select: function( event, ui ){
                    // Display label but store value in the hidden <input>
                    event.preventDefault();
                    $(selector).val( ui.item.label );
                    $(hiddenSelector).val( ui.item.value );

                    if( $(hiddenSelector).val() === '§' ){
                        $(createNewSelector).show();
                        $(createNewSelector).focus();
                        $(hiddenSelector).val('');
                    } else {
                        $(createNewSelector).hide();
                    }
                },
                focus: function( event, ui ) {
                    // Do not show values instead of the label
                    event.preventDefault();
                    $(selector).val(ui.item.label);
                }
            });
            
        });
        </script>";
	} else {
		echo __( 'No entities of the right type found.', 'wordlift' );
	}
}

/**
 * Saves the values of wordlift metaboxes set in the entity editor page
 */
function wl_entity_metabox_save( $post_id ) {

	if ( ! isset( $_POST['wl_metaboxes'] ) ) {
		return;
	}

	// Loop over the wl_metaboxes array and save metaboxes values
	foreach ( $_POST['wl_metaboxes'] as $meta_name => $meta_value ) {

		// First, verify nonce is set for this meta
		$nonce_name   = 'wordlift_' . $meta_name . '_entity_box_nonce';
		$nonce_verify = 'wordlift_' . $meta_name . '_entity_box';
		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			return $post_id;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST[ $nonce_name ], $nonce_verify ) ) {
			return $post_id;
		}

		// Save the property value
		if ( isset( $meta_name ) && isset( $meta_value ) && $meta_value !== '' ) {

			// If the meta expects an entity...
			$expecting_uri = ( wl_get_meta_type( $meta_name ) === WL_DATA_TYPE_URI );
			// ...and the user inputs an entity that is not present in the db...
			$absent_from_db = is_null( wl_get_entity_post_by_uri( $meta_value ) );
			// ...and that is not a external uri
			$name_is_uri = strpos( $meta_value, 'http' ) === 0;

			if ( $expecting_uri && $absent_from_db && ! $name_is_uri ) {

				// ...we create a new entity!
				$new_entity = wl_save_entity( '', $meta_value, WL_ENTITY_TYPE_NAME, '' );

				// Assign type
				$constraints = wl_get_meta_constraints( $meta_name );
				$type        = 'http://schema.org/' . $constraints['uri_type'];
				wl_set_entity_main_type( $new_entity->ID, $type );

				// TODO: directly publish the new entity

				// Update the value that will be saved as meta
				$meta_value = wl_get_entity_uri( $new_entity->ID );
			}
                        
                        // TODO: use WL methods
			update_post_meta( $post_id, $meta_name, $meta_value );
		} else {
                        // TODO: use WL methods
			delete_post_meta( $post_id, $meta_name );
		}
	}
	// Push changes on RedLink
	wl_push_to_redlink( $post_id );
}

add_action( 'wordlift_save_post', 'wl_entity_metabox_save' );


function wl_echo_nonce( $meta_name ) {
	wp_nonce_field( 'wordlift_' . $meta_name . '_entity_box', 'wordlift_' . $meta_name . '_entity_box_nonce' );
}