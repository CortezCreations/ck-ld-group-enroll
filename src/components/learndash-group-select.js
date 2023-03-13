/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { BaseControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
  * React dependencies
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
const LearnDashGroupSelect = ({ selected, onChange }) => {

    //console.log('LearnDashGroupSelect: Entering...');

    // Prepare the initial state
    const [ groupData, setGroupData ] = useState( [] );
    const [ value, setValue ]         = useState( '' );
    const [ status, setStatus ]       = useState( 'loading' );
    // Fetch Group Data
    const [ apiResponse, loading, error ] = useEffectWPFetch(
        `/ldlms/v1/groups?context=embed&per_page=-1`
    );
        
    useEffect(() => {
        if( ! loading && apiResponse.length > 0 && status === 'loading'){
            // Map the API response to the component
            setGroupData( apiResponse.map( group => ( {
                label : group.title.rendered,
                value : group.id,
            } ) ) );
            setValue( selected ? mapSelectedToOptions(selected) : '' );
            setStatus( 'ready' );
        }
    }, [ loading, status ]);

    // Map the selected IDs to the options
    const mapSelectedToOptions = (selected) => {
        return selected.map(id => {
            const user = apiResponse.find(group => group.id === id);
            return {
                value: group.title.rendered, 
                label: user.email
            };
        });
    };

    if( status === 'ready' ){

        return (
            <BaseControl 
                className='ck-ld-group-enroll-control-wrap'
                label={ __( 'Select LearnDash Group', 'ck-ld-group-enroll' ) }
            >
            <Select
                key="ck-ld-group-enroll-group-select"
                name="ck-ld-group-enroll-group-select"
                value={ value }
                options={ groupData }
                onChange={ ( value ) => {
                    setValue( value );
                    onChange( value ? value.value : 0 );
                } }
                className='ck-ld-group-enroll-group-select'
                isClearable={false}
            />
        </BaseControl>
        );

    } else {

        const message = loading
            ? __( 'Loading Groups...', 'ck-ld-group-enroll' ) 
            : __( 'Error loading Grroups check console for details', 'ck-ld-group-enroll' );

        return (
            <BaseControl className='ck-ld-group-enroll-control-wrap'>
                <p className="ck-ld-group-enroll-dummy-input">
                    { message }
                </p>
            </BaseControl>
        );
    }
}

export default LearnDashGroupSelect;