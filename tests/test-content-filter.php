<?php

/**
 * This file covers tests related to the microdata printing routines.
 */

require_once 'functions.php';

class ContentFilterTest extends WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	function setUp() {
		parent::setUp();

		wl_configure_wordpress_test();
		wl_empty_blog();
		rl_empty_dataset();
	}

	function testColorCodingOnFrontEnd() {

		$entity_id  = wl_create_post( '', 'entity-1', 'Entity 1', 'draft', 'entity' );
		$entity_uri = wl_get_entity_uri( $entity_id );
		wl_set_entity_main_type( $entity_id, 'http://schema.org/Event' );

		# Create a test entity post.
		$content = <<<EOF
This post is referencing the sample <span id="urn:enhancement-4f0e0fbc-e981-7852-9521-f4718eafa13f" class="textannotation highlight wl-event" itemid="$entity_uri">Entity 1</span>.
EOF;

		$post_id = wl_create_post( $content, 'post-1', 'Post 1', 'publish', 'post' );
		$post    = get_post( $post_id );

		// Disable front-end color coding.
		wl_configuration_set_enable_color_coding( false );
		$this->assertNotContains( 'class="wl-event"', wl_content_embed_item_microdata( $post->post_content, $entity_uri ) );

		// Enable front-end color coding.
		wl_configuration_set_enable_color_coding( true );
		$this->assertContains( 'class="wl-event"', wl_content_embed_item_microdata( $post->post_content, $entity_uri ) );

	}

	// Test if the microdata compiling does not fail on an entity with an undefined schema.org type
	function testMicrodataCompilingForAnEntityWithUndefinedType() {
		// Create an entity without defining the schema.org type properly
		$entity_id  = wl_create_post( 'Just a place', 'my-place', 'MyPlace', 'publish', 'entity' );
		$entity_uri = wl_get_entity_uri( $entity_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyPlace</span>
EOF;
		// Create a post referincing to the created entity
		$post_id = wl_create_post( $content, 'my-post', 'A post' );
		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		// Expected markup
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_with_undefined_type.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);
	}

	// Test if the microdata compiling does not fail on an entity with defined type and undefined custom fields
	function testMicrodataCompilingForAnEntityWithDefinedTypeAndUndefinedCustomFields() {

		// Create an entity without defining the schema.org type properly
		$entity_id = wl_create_post( 'Just a place', 'my-place', 'MyPlace', 'publish', 'entity' );
		wl_set_entity_main_type( $entity_id, 'http://schema.org/Place' );
		$entity_uri = wl_get_entity_uri( $entity_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyPlace</span>
EOF;
		// Create a post referencing to the created entity
		$post_id = wl_create_post( $content, 'my-post', 'A post' );

		// The expected mark-up expects color coding to be on.
		wl_configuration_set_enable_color_coding( true );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		// Expected markup
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_with_defined_type_and_undefined_custom_fields.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);
	}

	// Test if the microdata compiling does not fail on an entity with an unexpected custom field
	function testMicrodataCompilingForAnEntityWithUnexpectedCustomField() {
		// Create an entity without defining the schema.org type properly
		$entity_id = wl_create_post( 'Just a place', 'my-place', 'MyPlace', 'publish', 'entity' );
		wl_set_entity_main_type( $entity_id, 'http://schema.org/Place' );
		// The field 'foo' is not included in the 'Place' type definition
		add_post_meta( $entity_id, "foo", "bar", true );
		$entity_uri = wl_get_entity_uri( $entity_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyPlace</span>
EOF;
		// Create a post referincing to the created entity
		$post_id = wl_create_post( $content, 'my-post', 'A post' );

		// The expected mark-up expects color coding to be on.
		wl_configuration_set_enable_color_coding( true );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		// Expected markup
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_with_unexpected_custom_fields.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);
	}

	// Test microdata compiling on a well-formed entity without a nested entity
	function testMicrodataCompilingProperlyForAnEntityWithoutNestedEntities() {
		// Create an entity without defining the schema.org type properly
		$entity_id = wl_create_post( 'Just a place', 'my-place', 'MyPlace', 'publish', 'entity' );
		wl_set_entity_main_type( $entity_id, 'http://schema.org/Place' );
		add_post_meta( $entity_id, WL_CUSTOM_FIELD_GEO_LATITUDE, 40.12, true );
		add_post_meta( $entity_id, WL_CUSTOM_FIELD_GEO_LONGITUDE, 72.3, true );
		$entity_uri = wl_get_entity_uri( $entity_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyPlace</span>
EOF;
		// Create a post referincing to the created entity
		$post_id = wl_create_post( $content, 'my-post', 'A post' );

		// The expected mark-up expects color coding to be on.
		wl_configuration_set_enable_color_coding( true );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		// Expected markup
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_without_nested_entities.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);
	}

	// Check if nested entities microdata compiling works on nested entities
	function testMicrodataCompilingProperlyForAnEntityWithNestedEntities() {

		// A place
		$place_id = wl_create_post( 'Just a place', 'my-place', 'MyPlace', 'publish', 'entity' );
		wl_set_entity_main_type( $place_id, 'http://schema.org/Place' );
		add_post_meta( $place_id, WL_CUSTOM_FIELD_GEO_LATITUDE, 40.12, true );
		add_post_meta( $place_id, WL_CUSTOM_FIELD_GEO_LONGITUDE, 72.3, true );

		// An Event having as location the place above
		$event_id = wl_create_post( 'Just an event', 'my-event', 'MyEvent', 'publish', 'entity' );
		wl_set_entity_main_type( $event_id, 'http://schema.org/Event' );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_CAL_DATE_START, '2014-10-21', true );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_CAL_DATE_END, '2015-10-26', true );

		// Create an annotated post containing the entities
		$entity_uri = wl_get_entity_uri( $event_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyEvent</span>
EOF;
		$post_id    = wl_create_post( $content, 'post', 'A post' );

		// Case 1 - Nested entity is referenced trough the wordpress entity ID
		add_post_meta( $event_id, WL_CUSTOM_FIELD_LOCATION, $place_id, true );

		// The expected mark-up expects color coding to be on.
		wl_configuration_set_enable_color_coding( true );

		// Set the recursion limit.
		$this->setRecursionDepthLimit( 1 );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_with_nested_entities.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup ),
			"Error on comparing markup when the entity type is not defined"
		);

		delete_post_meta( $event_id, WL_CUSTOM_FIELD_LOCATION );
		// Check if meta were deleted properly
		$this->assertEquals( array(), get_post_meta( $event_id, WL_CUSTOM_FIELD_LOCATION ) );
		// Case 2 - Nested entity is referenced trough the an uri
		add_post_meta( $event_id, WL_CUSTOM_FIELD_LOCATION, wl_get_entity_uri( $place_id ), true );

		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_with_nested_entities.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);

	}

	// Check if nested entities microdata compiling works on nested entities
	function testMicrodataCompilingForAnEntityWithNestedBrokenEntities() {

		// An Event having as location the place above
		$event_id = wl_create_post( 'Just an event', 'my-event', 'MyEvent', 'publish', 'entity' );
		wl_set_entity_main_type( $event_id, 'http://schema.org/Event' );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_CAL_DATE_START, '2014-10-21', true );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_CAL_DATE_END, '2015-10-26', true );
		// Set a fake uri ad entity reference
		add_post_meta( $event_id, WL_CUSTOM_FIELD_LOCATION, 'http://my.fake.uri/broken/entity/linking', true );

		// Create an annotated post containing the entities
		$entity_uri = wl_get_entity_uri( $event_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyEvent</span>
EOF;
		$post_id    = wl_create_post( $content, 'post', 'A post' );

		// The expected mark-up expects color coding to be on.
		wl_configuration_set_enable_color_coding( true );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_bad_referenced_entities.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);

	}

	// Check recursivity limitation feature
	function testMicrodataCompilingRecursivityLimitation() {

		// A place
		$place_id = wl_create_post( 'Just a place', 'my-place', 'MyPlace', 'publish', 'entity' );
		wl_set_entity_main_type( $place_id, 'http://schema.org/Place' );
		add_post_meta( $place_id, WL_CUSTOM_FIELD_GEO_LATITUDE, 40.12, true );
		add_post_meta( $place_id, WL_CUSTOM_FIELD_GEO_LONGITUDE, 72.3, true );

		// An Event having as location the place above
		$event_id = wl_create_post( 'Just an event', 'my-event', 'MyEvent', 'publish', 'entity' );
		wl_set_entity_main_type( $event_id, 'http://schema.org/Event' );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_CAL_DATE_START, '2014-10-21', true );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_CAL_DATE_END, '2015-10-26', true );
		add_post_meta( $event_id, WL_CUSTOM_FIELD_LOCATION, $place_id, true );

		// Create an annotated post containing the entities
		$entity_uri = wl_get_entity_uri( $event_id );
		$content    = <<<EOF
    <span itemid="$entity_uri">MyEvent</span>
EOF;
		$post_id    = wl_create_post( $content, 'post', 'A post' );

		// Set to 0 the recursivity limitation on entity metadata compiling
		$this->setRecursionDepthLimit( 0 );

		// The expected mark-up expects color coding to be on.
		wl_configuration_set_enable_color_coding( true );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_recursivity_limitation.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);

		$this->setRecursionDepthLimit( 1 );

		// Compile markup for the given content
		$compiled_markup = _wl_content_embed_microdata( $post_id, $content );
		$expected_markup = file_get_contents( dirname( __FILE__ ) .
		                                      '/assets/microdata_compiling_for_an_entity_with_nested_entities.txt' );

		// Verify correct markup
		$this->assertEquals(
			$this->prepareMarkup( $expected_markup ),
			$this->prepareMarkup( $compiled_markup )
		);
	}

	function setRecursionDepthLimit( $value ) {
		// Set the default as index.
		$options                                                          = get_option( WL_OPTIONS_NAME );
		$options[ WL_CONFIG_RECURSION_DEPTH_ON_ENTITY_METADATA_PRINTING ] = $value;
		update_option( WL_OPTIONS_NAME, $options );
	}

	function prepareMarkup( $markup ) {
		$markup = preg_replace( '/\s+/', '', $markup );
		$markup = preg_replace(
			'/{{REDLINK_ENDPOINT}}/',
			wl_configuration_get_redlink_dataset_uri(),
			$markup );

		return $markup;
	}


}

