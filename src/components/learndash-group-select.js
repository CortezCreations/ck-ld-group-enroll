/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { BaseControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
  * React Select Dependencies
  *
  * @link Git https://github.com/JedWatson/react-select
  * @link Doc https://react-select.com/home
*/
import React from 'react';
import Select from 'react-select';

/**
 * Internal dependencies
 */
import useEffectWPFetch from '../hooks/use-effect-wp-fetch.js';

/**
 * LearnDash Group Select Component
 */
const LearnDashGroupSelect = ({ disabled, onChange }) => {

	const [inputValue, setValue] = useState('');
	const [apiResponse, loading, error] = useEffectWPFetch(
		`/ldlms/v1/groups?context=embed&per_page=-1`
	);

	// handle input change event
	const handleInputChange = value => {
		setValue(value);
	};

	// Loading Message
	const loadingMessage = () => {
		return __('Loading LearnDash Groups ...', 'ck-ld-group-enroll');
	};

	// No Options Message
	const noOptionsMessage = () => {
		if (error) {
			return __(`Error: Loading LearnDash Groups`, 'ck-ld-group-enroll');
		} else if (inputValue.length > 0) {
			return sprintf(
				__(`No LearnDash Groups found with name matching "%s"`, 'ck-ld-group-enroll'),
				inputValue
			);
		} else {
			return __(`No LearnDash Groups found`, 'ck-ld-group-enroll');
		}
	};

	return (
		<BaseControl
			label={__('LearnDash Group', 'ck-ld-group-enroll')}
			className='ck-ld-group-enroll-control-wrap'
		>
			<Select
				className='ck-ld-group-enroll-group-select'
				value={inputValue}
				options={apiResponse}
				onInputChange={handleInputChange}
				onChange={(value) => {
					setValue(value);
					onChange(value ? value.id : 0);
				}}
				getOptionLabel={value => value.title.rendered}
				getOptionValue={value => value.id}
				isLoading={loading}
				disabled={disabled}
				loadingMessage={loadingMessage}
				noOptionsMessage={noOptionsMessage}
			/>
		</BaseControl>
	);
}

export default LearnDashGroupSelect;