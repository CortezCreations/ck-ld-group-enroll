/**
 * @fileoverview useEffect Hook to fetch data from WP REST API.
 * @package ck-ld-group-enroll
 * @since 1.0.0
 * @version 1.0.0
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const useEffectWPFetch = ( url ) => {
    
    const [apiResponse, setAPIResponse] = useState([]);
    const [loading, setLoading]      = useState(true);
    const [error, setError]          = useState(false);

    useEffect(() => {
        apiFetch( {
            path   : url,
            method : 'GET',
        } ).then( ( apiResponse ) => {
            setAPIResponse( apiResponse );
            setLoading(false);
            //console.log( "useEffectWPFetch result: ", apiResponse );
        } ).catch( ( error ) => {
            console.log( "useEffectWPFetch error: ", error );
            setError( error );
            setLoading(false);
        } );
    }, []);

    return [apiResponse, loading, error];
};

export default useEffectWPFetch;