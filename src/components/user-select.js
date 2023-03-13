/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { BaseControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
  * React dependencies
  * @link Git https://github.com/JedWatson/react-select
  * @link Doc https://react-select.com/home
  * @link REVIEW import AsyncSelect from 'react-select/async'; to run a search on WP Users istead of tryig to load them all like a fool
*/
import React from 'react';
import Select from 'react-select';

/**
 * Internal dependencies
 */
import useEffectWPFetch from '../hooks/use-effect-wp-fetch.js';
//import './style.scss';

/**
 * WP User Multi Select Component
 * 
 * @param boolean disabled - Disable the component
 * @param array selected - User IDs - Gets convereted to emails from IDs
 * @param function onChange - Callback function to pass the selected IDs to the parent 
 */
const WPUserSelect = ({ disabled, selected, onChange }) => {

    //console.log('WPUserSelect: Entering...');

    // Prepare the initial state
    const [value, setValue] = useState( [] );
    const [options, setOptions] = useState( [] );
    // Fetch WP Users
    const [apiResponse, loading, error] = useEffectWPFetch(
       `/wp/v2/users/?per_page=-1&context=edit`
    );
    
    // Filter the data to work with the component
    useEffect(() => {

        // Bail if loading
        if( loading || apiResponse.length === 0 ){
            return;
        }
        // Map Options
        setOptions( apiResponse.map(user => ({value: user.id, label: user.email})) );

        if( selected && selected.length > 0 ){
            setValue( mapSelectedToOptions(selected) );
        }

    }, [selected, loading]);

    // Display a loading || error message
    if( loading || error ){
        let message = error ? __('Error loading Users check console for details', 'ck-ld-group-enroll')
                            : __('Loading Users...', 'ck-ld-group-enroll');
        return (
            <BaseControl className='ck-ld-group-enroll-control-wrap'>
                <p className="ck-ld-group-enroll-dummy-input">
                    { message }
                    <Spinner />
                </p>
            </BaseControl>
        );
    }

    // Handle the change event 
    const onChangeFilter = ( value ) => {
        // Bail if disabled
        if( loading ){
            return;
        }
        
        setValue( value );
        //Pass the IDs to the parent
        const ids = value.map(v => v.value);

        onChange( ids );
    };

    // Map the selected IDs to the options
    const mapSelectedToOptions = (selected) => {
        return selected.map(id => {
            const user = apiResponse.find(user => user.id === id);
            return {value: user.id, label: user.email};
        });
    };

    // Render the component
    return (
        <BaseControl 
            className='ck-ld-group-enroll-control-wrap'
            label={ __( 'Select Users To Enroll', 'ck-ld-group-enroll' ) }
        >
            <Select
                key="ck-ld-group-enroll-user-select"
                name="ck-ld-group-enroll-user-select"
                value={ value }
                onChange={ onChangeFilter }
                options={ options }
                className='ck-ld-group-enroll-user-select'
                isMulti='true'
                isClearable={false}
                disabled={ disabled }
            />
        </BaseControl>
	);
};

export default WPUserSelect;