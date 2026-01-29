<?php
/**
 * @file
 * @ingroup PF
 */

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;

/**
 * @ingroup PFFormInput
 */
class PFTokensInput extends PFFormInput {

	public static function getName(): string {
		return 'tokens';
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_txt', '_wpg' ];
	}

	public static function getDefaultPropTypeLists() {
		return [
			'_wpg' => [ 'is_list' => true, 'size' => 100 ]
		];
	}

	public static function getOtherPropTypeListsHandled() {
		return [ '_txt' ];
	}

	public static function getDefaultCargoTypes() {
		return [];
	}

	public static function getOtherCargoTypesHandled() {
		return [ 'Page', 'String' ];
	}

	public static function getDefaultCargoTypeLists() {
		return [
			'Page' => [ 'is_list' => true, 'size' => 100 ]
		];
	}

	public static function getOtherCargoTypeListsHandled() {
		return [ 'String' ];
	}

	/**
	 * @param string|array|null $cur_value
	 * @param string $input_name
	 * @param bool $is_mandatory
	 * @param bool $is_disabled
	 * @param array $other_args = []
	 * @return string
	 */
	public static function getHTML( $cur_value, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsEDSettings;

		$other_args['is_list'] = true;

		/* Not currently implemented
		// $other_args['possible_values'] 
		$possible_values = $other_args['possible_values'];
		// Note that $other_args['possible_values'] are called
		// through PFValuesUtils::setAutocompleteValues() below
		if ( $possible_values == null ) {
			// If it's a Boolean property, display 'Yes' and 'No'
			// as the values.
			if ( array_key_exists( 'property_type', $other_args ) && $other_args['property_type'] == '_boo' ) {
				$possible_values = [
					PFUtils::getWordForYesOrNo( true ),
					PFUtils::getWordForYesOrNo( false ),
				];
			} else {
				$possible_values = [];
			}
		}
		*/

		if ( array_key_exists( 'values from external data', $other_args ) ) {
			$autocompleteSettings = 'external data';
			$remoteDataType = null;
			if ( array_key_exists( 'origName', $other_args ) ) {
				$name = $other_args['origName'];
			} else {
				$name = $input_name;
			}
			$wgPageFormsEDSettings[$name] = [];
			if ( $other_args['values from external data'] != null ) {
				$wgPageFormsEDSettings[$name]['title'] = $other_args['values from external data'];
			}
			if ( array_key_exists( 'image', $other_args ) ) {
				$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
				$image_param = $other_args['image'];
				$wgPageFormsEDSettings[$name]['image'] = $image_param;
				global $edgValues;
				for ( $i = 0; $i < count( $edgValues[$image_param] ); $i++ ) {
					$image = $edgValues[$image_param][$i];
					if ( strpos( $image, "http" ) !== 0 ) {
						$file = $repoGroup->findFile( $image );
						if ( $file ) {
							$url = $file->getFullUrl();
							$edgValues[$image_param][$i] = $url;
						} else {
							$edgValues[$image_param][$i] = "";
						}
					}
				}
			}
			if ( array_key_exists( 'description', $other_args ) ) {
				$wgPageFormsEDSettings[$name]['description'] = $other_args['description'];
			}
			if ( array_key_exists( 'delimiter', $other_args ) ) {
				$delimiter = $other_args['delimiter'];
			} else {
				$delimiter = ',';
			}
		} else {
			[ $autocompleteSettings, $remoteDataType, $delimiter ] = PFValuesUtils::setAutocompleteValues( $other_args, true );
		}

		$className = 'pfTokens ';
		$className .= ( $is_mandatory ) ? 'mandatoryField' : 'createboxInput';
		if ( array_key_exists( 'class', $other_args ) ) {
			$className .= ' ' . $other_args['class'];
		}
		$input_id = 'input_' . $wgPageFormsFieldNum;

		if ( array_key_exists( 'size', $other_args ) ) {
			$size = intval( $other_args['size'] );
			if ( $size == 0 ) {
				$size = 100;
			}
		} else {
			$size = 100;
		}

		$cur_value_str = PFValuesUtils::getValuesString( $cur_value, $delimiter );

		$inputAttrs = [
			'id' => $input_id,
			'name' => $input_name . '[]',
			'class' => $className,
			'style' => 'width:' . $size * 6 . 'px',
			'multiple' => 'multiple',
			'value' => $cur_value_str,
			'size' => 1,
			'data-size' => $size * 6 . 'px',
			'tabindex' => $wgPageFormsTabIndex,
			'autocompletesettings' => $autocompleteSettings,
		];
		if ( array_key_exists( 'mapping from url', $other_args ) ) {
			$inputAttrs['data-mapping-from-url'] = $other_args['mapping from url'];
		}
		if ( array_key_exists( 'origName', $other_args ) ) {
			$inputAttrs['origName'] = $other_args['origName'];
		}
		if ( array_key_exists( 'existing values only', $other_args ) ) {
			$inputAttrs['existingvaluesonly'] = 'true';
		}
		if ( $remoteDataType !== null ) {
			$inputAttrs['autocompletedatatype'] = $remoteDataType;
		}
		if ( $is_disabled ) {
			$inputAttrs['disabled'] = true;
		}
		if ( array_key_exists( 'maxlength', $other_args ) ) {
			$inputAttrs['maxlength'] = $other_args['maxlength'];
		}
		if ( array_key_exists( 'placeholder', $other_args ) ) {
			$inputAttrs['placeholder'] = $other_args['placeholder'];
		}
		if ( array_key_exists( 'max values', $other_args ) ) {
			$inputAttrs['maxvalues'] = $other_args['max values'];
		}
		if ( array_key_exists( 'mapping property', $other_args ) ) {
			$inputAttrs['mappingproperty'] = $other_args['mapping property'];
		}
		if ( array_key_exists( 'mapping template', $other_args ) ) {
			$inputAttrs['mappingtemplate'] = $other_args['mapping template'];
		}

		// This code adds predefined tokens in the form of <options>
		$optionsText = self::createOptionsFromValues(
			PFValuesUtils::getValuesArray( $cur_value, $delimiter ),
			$other_args['value_labels'] ?? null
		);

		$text = "\n\t" . Html::rawElement( 'select', $inputAttrs, $optionsText ) . "\n";
		$text .= Html::hidden( $input_name . '[is_list]', 1 );

		if ( array_key_exists( 'uploadable', $other_args ) && $other_args['uploadable'] == true ) {
			if ( array_key_exists( 'default filename', $other_args ) ) {
				$default_filename = $other_args['default filename'];
			} else {
				$default_filename = '';
			}

			$text .= PFTextInput::uploadableHTML( $input_id, $delimiter, $default_filename, $cur_value_str, $other_args );
		}

		$spanID = 'span_' . $wgPageFormsFieldNum;
		$spanClass = 'inputSpan';
		if ( $is_mandatory ) {
			$spanClass .= ' mandatoryFieldSpan';
		}

		if ( array_key_exists( 'show on select', $other_args ) ) {
			$spanClass .= ' pfShowIfSelected';
			PFFormUtils::setShowOnSelect( $other_args['show on select'], $spanID );
		}

		$spanAttrs = [
			'id' => $spanID,
			'class' => $spanClass,
			'data-input-type' => 'tokens'
		];
		$text = "\n" . Html::rawElement( 'span', $spanAttrs, $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'size',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_size' )->text()
		];
		$params[] = [
			'name' => 'placeholder',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_placeholder' )->text()
		];
		$params[] = [
			'name' => 'existing values only',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_existingvaluesonly' )->text()
		];
		$params[] = [
			'name' => 'max values',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_maxvalues' )->text()
		];
		$params = array_merge( $params, PFTextWithAutocompleteInput::getAutocompletionParameters() );
		$params[] = [
			'name' => 'uploadable',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_uploadable' )->text()
		];
		$params[] = [
			'name' => 'default filename',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_defaultfilename' )->text()
		];

		return $params;
	}

	/**
	 * Convert current values to HTML options
	 * 
	 * @param array $cur_values - current values (see note below)
	 * @param ?array $valueLabels
	 * @return string
	 */
	private static function createOptionsFromValues(
		array $cur_values,
		?array $valueLabels = null
	): string {
		$optionsText = "";
		$currIsIndexedArray = PFMappingUtils::isIndexedArray($cur_values);

		// $cur_values can be:
		// (a) sequential array of values that are mapped to labels 
		// in $valueLabels
		// (b) pre-mapped associative array in which each key holds
		// the value to be stored and each array value its matching label
		// (c) indexed array without label mappings
		
		foreach ( $cur_values as $key => $current_value ) {
			if ( $current_value === '' ) {
				continue;
			}
			$valId = $currIsIndexedArray ? $current_value : $key;
			if ( is_array( $valueLabels ) && array_key_exists( $valId, $valueLabels ) ) {
				// (a)
				$optionLabel = $valueLabels[$valId];
				$optionAttrs = [ 'value' => $valId ];
			} else {
				if ( !$currIsIndexedArray ) {
					// (b) pre-mapped. May still be needed.
					$optionAttrs = [ 'value' => $key ];
				} else {
					// (c) simple indexed array
					$optionAttrs = [ 'value' => $current_value ];
				}
				$optionLabel = $current_value;
			}
			$optionAttrs['selected'] = 'selected';
			$optionsText .= Html::element( 'option', $optionAttrs, $optionLabel );
		}

		return $optionsText;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return self::getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
