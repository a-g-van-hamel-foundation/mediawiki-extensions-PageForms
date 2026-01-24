<?php
/**
 * Methods for mapping values to labels
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class PFMappingUtils {

	/**
	 * @param array $args
	 * @param bool $useDisplayTitle
	 * @return string|null
	 */
	public static function getMappingType( array $args, bool $useDisplayTitle = false ) {
		$mappingType = null;
		if ( array_key_exists( 'mapping property', $args ) ) {
			$mappingType = 'mapping property';
		} elseif ( array_key_exists( 'mapping template', $args ) ) {
			$mappingType = 'mapping template';
		} elseif ( array_key_exists( 'mapping cargo table', $args ) &&
		array_key_exists( 'mapping cargo field', $args ) ) {
			// @todo: or use 'cargo field'?
			$mappingType = 'mapping cargo field';
		} elseif ( array_key_exists( 'mapping using translate', $args ) ) {
			$mappingType = 'mapping using translate';
		 } elseif( array_key_exists( "values from url", $args ) && array_key_exists( "mapping from url", $args ) ) {
			$mappingType = 'mapping from url';
		} elseif ( $useDisplayTitle ) {
			$mappingType = 'displaytitle';
		}
		return $mappingType;
	}

	/**
	 * Map values if possible and return a named (associative) array
	 * @param array $values
	 * @param array $args
	 * @return array
	 */
	public static function getMappedValuesForInput( array $values, array $args = [] ) {
		global $wgPageFormsUseDisplayTitle;
		$mappingType = self::getMappingType( $args, $wgPageFormsUseDisplayTitle );
		if ( !self::isIndexedArray( $values ) ) {
			// already named associative
			$pages = array_keys( $values );
			$values = self::getMappedValues( $pages, $mappingType, $args, $wgPageFormsUseDisplayTitle );
			$res = $values;
		} elseif ( $mappingType !== null ) {
			$res = self::getMappedValues( $values, $mappingType, $args, $wgPageFormsUseDisplayTitle );
		} else {
			$res = [];
			foreach ( $values as $key => $value ) {
				$res[$value] = $value;
			}
		}
		return $res;
	}

	/**
	 * Check if array is indexed/sequential (true), else named/associative (false)
	 * @param array $arr
	 * @return string
	 */
	public static function isIndexedArray( $arr ) {
		if ( array_keys( $arr ) == range( 0, count( $arr ) - 1 ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return named array of mapped values
	 * Static version of PF_FormField::setMappedValues
	 * @param array $values
	 * @param string|null $mappingType
	 * @param array $args
	 * @param bool $useDisplayTitle
	 * @return array
	 */
	public static function getMappedValues(
		array $values,
		?string $mappingType,
		array $args,
		bool $useDisplayTitle
	) {
		$mappedValues = null;
		switch ( $mappingType ) {
			case 'mapping property':
				$mappingProperty = $args['mapping property'];
				$mappedValues = self::getValuesWithMappingProperty( $values, $mappingProperty );
				break;
			case 'mapping template':
				$mappingTemplate = $args['mapping template'];
				$mappedValues = self::getValuesWithMappingTemplate( $values, $mappingTemplate );
				break;
			case 'mapping cargo field':
				$mappingCargoField = isset( $args['mapping cargo field'] ) ? $args['mapping cargo field'] : null;
				$mappingCargoValueField = isset( $args['mapping cargo value field'] ) ? $args['mapping cargo value field'] : null;
				$mappingCargoTable = $args['mapping cargo table'];
				$mappedValues = self::getValuesWithMappingCargoField( $values, $mappingCargoField, $mappingCargoValueField, $mappingCargoTable, $useDisplayTitle );
				break;
			case 'mapping using translate':
				$translateMapping = $args[ 'mapping using translate' ];
				$mappedValues = self::getValuesWithTranslateMapping( $values, $translateMapping );
				break;
			case 'mapping from url':
				// Used only to map current values to labels
				// if supported by the service
				$mappedValues = [];
				foreach( $values as $k => $v ) {
					$urlResults = PFValuesUtils::getValuesFromExternalURL( $args["values from url"], $v );
					if ( is_array( $urlResults ) && array_key_exists( $v, $urlResults ) ) {
						$mappedValues[$v] = $urlResults[$v];
					} else {
						$mappedValues[$v] = $v;
					}
				}
				break;
			case 'displaytitle':
				$isReverseLookup = ( array_key_exists( 'reverselookup', $args ) && ( $args['reverselookup'] == 'true' ) );
				$mappedValues = self::mapPagenamesToDisplayTitles( $values );				
				break;
		}
		$res = ( $mappedValues !== null ) ? self::disambiguateLabels( $mappedValues ) : $values;
		return $res;
	}

	/**
	 * Helper function to get a named array of labels from
	 * an indexed array of values given a mapping property.
	 * Originally in PF_FormField
	 * @param array $values
	 * @param string $propertyName
	 * @return array
	 */
	public static function getValuesWithMappingProperty(
		array $values,
		string $propertyName
	): array {
		$store = PFUtils::getSMWStore();
		if ( $store == null || empty( $values ) ) {
			return [];
		}
		$res = [];
		foreach ( $values as $index => $value ) {
			// @todo - does this make sense?
			// if ( $useDisplayTitle ) {
			// $value = $index;
			// }
			$subject = Title::newFromText( $value );
			if ( $subject != null ) {
				$vals = PFValuesUtils::getSMWPropertyValues( $store, $subject, $propertyName );
				if ( count( $vals ) > 0 ) {
					$res[$value] = trim( $vals[0] );
				} else {
					// @todo - make this optional
					$label = self::removeNSPrefixFromLabel( trim( $value ) );
					$res[$value] = $label;
				}
			} else {
				$res[$value] = $value;
			}
		}
		return $res;
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping template.
	 * @todo remove $useDisplayTitle?
	 * @param array $values
	 * @param string $mappingTemplate
	 * @param bool $useDisplayTitle
	 * @return array
	 */
	public static function getValuesWithMappingTemplate(
		array $values,
		string $mappingTemplate,
		bool $useDisplayTitle = false
	): array {
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $mappingTemplate );
		$templateExists = $templateTitle->exists();

		$res = [];
		if ( $templateExists ) {
			$parser = PFUtils::getInitialisedParser();
			foreach ( $values as $index => $value ) {
				$label = trim( $parser->recursiveTagParse( '{{' . $mappingTemplate . '|' . $value . '}}' ) );
				$res[$value] = $label == '' ? $value : $label;
			}
		} else {
			foreach ( $values as $index => $value ) {
				$res[$value] = $value;
			}
		}

		return $res;
	}

	/**
	 * Helper function to get an array of labels from an array of values
	 * given a mapping Cargo table/field.
	 * Derived from PFFormField::setValuesWithMappingCargoField
	 * @todo does $useDisplayTitle make sense here?
	 * @todo see if check for $mappingCargoValueField works
	 * @param array $values
	 * @param string|null $mappingCargoField
	 * @param string|null $mappingCargoValueField
	 * @param string|null $mappingCargoTable
	 * @param bool $useDisplayTitle
	 * @return array
	 */
	public static function getValuesWithMappingCargoField(
		$values,
		$mappingCargoField,
		$mappingCargoValueField,
		$mappingCargoTable,
		bool $useDisplayTitle = false
	) {
		$labels = [];
		foreach ( $values as $index => $value ) {
			if ( $useDisplayTitle ) {
				$value = $index;
			}
			$labels[$value] = $value;
			// Check if this works
			if ( $mappingCargoValueField !== null ) {
				$valueField = $mappingCargoValueField;
			} else {
				$valueField = '_pageName';
			}
			$vals = PFValuesUtils::getValuesForCargoField(
				$mappingCargoTable,
				$mappingCargoField,
				$valueField . '="' . $value . '"'
			);
			if ( count( $vals ) > 0 ) {
				$labels[$value] = html_entity_decode( trim( $vals[0] ) );
			}
		}
		return $labels;
	}

	/**
	 * Mapping with the Translate extension
	 * @param array $values
	 * @param string $translateMapping
	 * @return array
	 */
	public static function getValuesWithTranslateMapping(
		array $values,
		string $translateMapping
	) {
		$res = [];
		foreach ( $values as $key ) {
			$res[$key] = PFUtils::getParser()->recursiveTagParse( '{{int:' . $translateMapping . $key . '}}' );
		}
		return $res;
	}

	/**
	 * Maps values to an array of labels or value-label pairs.
	 * Can be used to map submitted to possible values.
	 * Works with both local and remote autocompletion.
	 * (Previously part of PF_FormField but repurposed)
	 * 
	 * @param string|array|null $val
	 * @param string|null $delimiter
	 * @param array $args
	 * @param bool $getMapped
	 * @return string[]
	 */
	public static function mapValuesToLabels(
		mixed $val,
		?string $delimiter,
		array $args = [],
		bool $getMapped = true
	): array {
		if ( $val === null ) {
			return [];
		}
		$values = PFValuesUtils::getValuesArray( $val, $delimiter ?? null );
		$possibleValues = array_key_exists( 'possible_values', $args )
			? $args['possible_values']
			: null;
		
		// Remote autocompletion? Don't try mapping 
		// current to possible values
		$labels = $mappedValues = [];
        $valMax = PFValuesUtils::getMaxValuesToRetrieve();
		//$form_submitted &&
		$mode = count( $possibleValues ) >= $valMax
			|| ( array_key_exists( "values from url", $args ) && array_key_exists( "mapping from url", $args ) )
			? 'remote'
			: 'local';

		if ( $mode === 'local' ) {
			foreach ( $values as $value ) {
				if ( $value === '' ) {
					continue;
				}
				if ( $possibleValues !== null && array_key_exists( $value, $possibleValues ) ) {
					$labels[] = $possibleValues[$value];
					$mappedValues[$value] = $possibleValues[$value];
				} else {
					$labels[] = $value;
					$mappedValues[$value] = $value;
				}
			}
		} elseif ( $mode === 'remote' ) {
			$mappedValues = self::getMappedValuesForInput( $values, $args );
			$labels = array_values( $mappedValues );
		}

		return $getMapped ? $mappedValues : $labels;
	}

	/**
	 * Attempts to map pagenames to their display titles if any
	 * and if that fails, defaults to their pagenames.
	 */
	public static function mapPagenamesToDisplayTitles(
		array $pagenames
	): array {
		$titlesLabels = [];

		// Create two arrays of Title objects first: 
		// $allTitles (named array) - Title may be null
		// $titleInstances (indexed) - proper Title objects only
		$allTitles = $titleInstances = [];
		foreach ( $pagenames as $k => $pagename ) {
			$pagename = trim( $pagename );
			if ( $pagename === "" ) {
				continue;
			}
			$title = Title::newFromText( $pagename );
			if ( $title instanceof Title ) {
				$allTitles[$pagename] = $title;
				$titleInstances[] = $title;
			} else {
				$allTitles[$pagename] = null;
			}
		}

		$properties = MediaWikiServices::getInstance()->getPageProps()
			->getProperties( $titleInstances, [ 'displaytitle', 'defaultsort' ] );

		// Build the array we want to output
		foreach( $allTitles as $pagename => $title ) {
			if ( $title === null ) {
				$titleLabels[$pagename] = $pagename;
				continue;
			}
			if ( array_key_exists( $title->getArticleID(), $properties ) ) {
				$titleprops = $properties[$title->getArticleID()];
			} else {
				$titleprops = [];
			}
			// Potentially normalise pagename
			$pagename = $title->getPrefixedText();
			// Populate titlesLabels
			if ( array_key_exists( 'displaytitle', $titleprops ) &&
				trim( str_replace( '&#160;', '', strip_tags( $titleprops['displaytitle'] ) ) ) !== '' ) {
				$titlesLabels[$pagename] = htmlspecialchars_decode( $titleprops['displaytitle'] );
			} else {
				$titlesLabels[$pagename] = $pagename;
			}
		}
		return $titlesLabels;
	}

	/**
	 * Remove namespace prefix (if any) from label
	 * @param string $label
	 * @return string
	 */
	private static function removeNSPrefixFromLabel( string $label ) {
		$labelArr = explode( ':', trim( $label ) );
		if ( count( $labelArr ) > 1 ) {
			$prefix = array_shift( $labelArr );
			$res = implode( ':', $labelArr );
		} else {
			$res = $label;
		}
		return $res;
	}

	/**
	 * Doing "mapping" on values can potentially lead to more than one
	 * value having the same "label". To avoid this, we find duplicate
	 * labels, if there are any, add on the real value, in parentheses,
	 * to all of them.
	 *
	 * @param array $labels
	 * @return array
	 */
	public static function disambiguateLabels( array $labels ) {
		if ( count( $labels ) == count( array_unique( $labels ) ) ) {
			return $labels;
		}
		$fixed_labels = [];
		foreach ( $labels as $value => $label ) {
			$fixed_labels[$value] = $labels[$value];
		}
		$counts = array_count_values( $fixed_labels );
		foreach ( $counts as $current_label => $count ) {
			if ( $count > 1 ) {
				$matching_keys = array_keys( $labels, $current_label );
				foreach ( $matching_keys as $key ) {
					$fixed_labels[$key] .= ' (' . $key . ')';
				}
			}
		}
		if ( count( $fixed_labels ) == count( array_unique( $fixed_labels ) ) ) {
			return $fixed_labels;
		}
		// If that didn't work, just add on " (value)" to *all* the
		// labels. @TODO - is this necessary?
		foreach ( $labels as $value => $label ) {
			$labels[$value] .= ' (' . $value . ')';
		}
		return $labels;
	}

}
