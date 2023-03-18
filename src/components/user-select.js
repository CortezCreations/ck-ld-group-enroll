/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { BaseControl, Spinner } from '@wordpress/components'
import apiFetch from '@wordpress/api-fetch';

/**
  * React Select Dependencies
  *
  * @link Git https://github.com/JedWatson/react-select
  * @link Doc https://react-select.com/home
*/
import React from 'react';
import AsyncSelect from 'react-select/async';

/**
 * WPUserSelect Component
 *
 * Searches User Rest Route for users matching the search term
 * Sends the selected user IDs to the parent component
 */
const WPUserSelect = ({ disabled, onChange }) => {

	const [inputValue, setValue] = useState('');
	const [selectedValue, setSelectedValue] = useState(null);

	// handle input change event
	const handleInputChange = value => {
		setValue(value);
	};

	// handle selection
	const handleChange = value => {
		setSelectedValue(value);
		//Pass the IDs to the parent
		const ids = value.map(v => v.id);
		onChange(ids);
	}

	// get search params as string
	const getSearchParams = (inputValue) => {
		let params = {
			search: encodeURIComponent(inputValue),
			orderby: 'email',
			//roles: 'administrator,editor,author,contributor,subscriber',
			per_page: -1,
			context: 'edit',
		};
		return new URLSearchParams(params).toString();
	};

	// load options using API call
	const loadOptions = (inputValue) => {
		if (inputValue.length > 2) {
			const searchParams = getSearchParams(inputValue);
			return apiFetch({
				path: `/wp/v2/users/?${searchParams}`,
				method: 'GET'
			});
		} else {
			return [];
		}
	};

	// Loading Message
	const loadingMessage = () => {
		if (inputValue.length > 2) {
			return sprintf(
				__(`Loading Users with emails matching "%s" ...`, 'ck-ld-group-enroll'),
				inputValue
			);
		} else {
			return __('Type at least 3 characters to start search', 'ck-ld-group-enroll');
		}
	};

	// No Options Message
	const noOptionsMessage = () => {
		return sprintf(
			__(`No Users found with emails matching "%s"`, 'ck-ld-group-enroll'),
			inputValue
		);
	};

	return (
		<BaseControl
			className='ck-ld-group-enroll-control-wrap'
			label={__('Select Users To Enroll', 'ck-ld-group-enroll')}
			help={__("Enter at least 3 characters of a user's email to start search.", 'ck-ld-group-enroll')}
		>
			<AsyncSelect
				cacheOptions
				defaultOptions
				value={selectedValue}
				getOptionLabel={e => e.email}
				getOptionValue={e => e.id}
				loadOptions={loadOptions}
				onInputChange={handleInputChange}
				onChange={handleChange}
				isMulti={true}
				disabled={disabled}
				loadingMessage={loadingMessage}
				noOptionsMessage={noOptionsMessage}
			/>
		</BaseControl>
	);
}

export default WPUserSelect;