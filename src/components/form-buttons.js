/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { BaseControl, Button } from '@wordpress/components';


const CKLDGroupEnrollFormButtons = ({ status, onClick }) => {

    let buttonList = [];
    if( status === 'processing' ){
        buttonList.push({
            label     : __( 'Cancel Process', 'ck-ld-group-enroll' ),
            value     : 'cancel',
            className : 'is-primary is-large is-busy',
            disabled  : false,
        });
    } else if ( status === 'completed' ){
        buttonList.push({
            label     : __( 'Start New Enrollment Process', 'ck-ld-group-enroll' ),
            value     : 'reset',
            className : 'is-primary is-large',
            disabled  : false,
        });
    } else if( status === 'idle' || status === 'ready' ) {

        buttonList.push({
            label     : __( 'Enroll Users', 'ck-ld-group-enroll' ),
            value     : 'enroll',
            className : 'is-primary is-large',
            disabled  : status === 'idle' ? true : false,
        });
    }

    return (
        <BaseControl>
        { buttonList.map( ( button, index ) => {
            return (
                <Button
                    key={`ck-ld-group-enroll-process-form-controller-button-${index}`}
                    label={ button.label }
                    value={ button.value }
                    className={ button.className }
                    disabled={ button.disabled }
                    onClick={() => { onClick(button.value) }}
                >
                    { button.label }
                </Button>
            );
        } ) }
        </BaseControl>
    )
}

export default CKLDGroupEnrollFormButtons;