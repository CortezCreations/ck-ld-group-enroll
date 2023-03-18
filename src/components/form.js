/**
 * WordPress dependencies
 */

import { __ } from '@wordpress/i18n';
import { BaseControl, Panel, PanelRow, Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */

import WPUserSelect from '../components/user-select.js';
import LearnDashGroupSelect from '../components/learndash-group-select.js';

const CKLDGroupEnrollForm = ({ onChange }) => {

	// Set State Variables
	const [status, setStatus] = useState('disabled');
	const [formData, setFormData] = useState({
		userIDs: [],
		groupID: 0
	});

	// Get Current Admin User ID
	const currentUser = useSelect(select => select('core').getCurrentUser());

	// Handle Field Change Events
	const handleFormChange = (key, value) => {
		setFormData(formData => ({
			// Retain the existing values
			...formData,
			// update the current field
			[key]: value,
		}));
	}

	// Handle Form Submission
	const onFormSubmit = () => {

		setStatus('processing');

		onChange({
			user_ids: formData.userIDs,
			group_id: formData.groupID,
			admin_id: currentUser.id,
			status: 'run',
			courses: [],
			results: [],
			started: 0,
			completed: 0,
			messaging: []
		});

	}

	// Manage the button status
	const handleButtonStatus = () => {
		const { userIDs, groupID } = formData;
		const userCount = userIDs.length;
		if (status !== 'processing') {
			if (status === 'disabled' && userCount > 0 && groupID > 0) {
				setStatus('ready');
			} else if (status === 'ready' && (userCount === 0 || groupID === 0)) {
				setStatus('disabled');
			}
		}
	}
	handleButtonStatus();
	const buttonLabel = (status !== 'processing')
		? __('Enroll Users', 'ck-ld-group-enroll')
		: __('Processing...', 'ck-ld-group-enroll');
	const buttonClassNames = (status !== 'processing')
		? 'is-primary is-large'
		: 'is-primary is-large is-busy';
	const buttonDisabled = (status === 'processing' || status === 'disabled');

	return (
		<Panel
			key="ck-ld-group-enroll-process-form"
			header={__('Enroll WP Users to LearnDash Groups', 'ck-ld-group-enroll')}
			className="ck-ld-group-enroll-panel ">
			<PanelRow className="ck-ld-group-enroll-mobile-full-width">
				<WPUserSelect
					key='user-select'
					onChange={(e) => handleFormChange("userIDs", e)}
					disabled={status === 'processing'}
				/>
				<LearnDashGroupSelect
					key='learndash-group-select'
					onChange={(e) => handleFormChange("groupID", e)}
					disabled={status === 'processing'}
				/>
			</PanelRow>
			<PanelRow className="ck-ld-group-enroll-panel-bottom-button-row">
				<BaseControl
					className='ck-ld-group-enroll-panel-bottom-help'
					help={__('Select one or more Users and a LearnDash Group to enroll.', 'ck-ld-group-enroll')} />
				<Button
					key={`ck-ld-group-enroll-submit-button`}
					className={buttonClassNames}
					disabled={buttonDisabled}
					onClick={(e) => onFormSubmit(e)}
				>
					{buttonLabel}
				</Button>
			</PanelRow>
		</Panel>
	);

}

export default CKLDGroupEnrollForm;