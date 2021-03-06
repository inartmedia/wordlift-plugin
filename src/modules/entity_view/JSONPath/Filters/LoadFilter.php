<?php
namespace Flow\JSONPath\Filters;

class LoadFilter extends AbstractFilter {
	/**
	 * @param array $collection
	 *
	 * @return array
	 */
	public function filter( $collection ) {

		$return = [ ];

		foreach ( $collection as $item ) {
			foreach ( $item as $key => $value ) {
				if ( $this->value === $key ) {

					if ( '.jpg' === strtolower( substr( $value, -4 ) )
						|| '.gif' === strtolower( substr( $value, -4 ) ) )
						continue;

					$return[] = array( wl_jsonld_load_remote( $value . '.json' ) );
				}
			}
		}

		return $return;

	}

}
 