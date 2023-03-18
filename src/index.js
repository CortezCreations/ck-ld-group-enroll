/**
 * WordPress dependencies
 */

import api from '@wordpress/api';
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { Fragment, useEffect, useState, render } from '@wordpress/element';
import { Panel, PanelRow, Spinner } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CKLDGroupEnrollForm from './components/form.js';
import CKLDGroupEnrollResults from './components/form-results.js';
import './style.scss';

/**
 * CK LearnDash Group Enroll Component - Enrolls users to LearnDash Groups
 *
 * Loads plugin settings option from the API
 * Displays a form to update them or the results from last process
 */
const CKLDGroupEnroll = () => {

	// Get settings from the API
	const [ckld_group_enroll_queue, setCKLDEnrollQueue
	] = useEntityProp('root', 'site', 'ckld_group_enroll_queue');

	// Update settings via the API for now
	const updateCKLDEnrollQueue = (updates) => {
		const model = new api.models.Settings({
			ckld_group_enroll_queue: updates
		});
		model.save().then(response => {
			setCKLDEnrollQueue(response.ckld_group_enroll_queue);
		});
	}

	// Display Results || Form || Placeholder
	const [isDisplay, setIsDisplay] = useState(false);

	// Proccess Polling For Updated Results
	const [processing, setProcessing] = useState(false);
	useEffect(() => {
		let interval;

		if (!processing) {
			return () => { };
		}
		console.log("Results Polling Started");
		interval = setInterval(() => {
			new api.models.Settings().fetch().then((response) => {
				console.log("Fetch Results", response);
				updateCKLDEnrollQueue(response.ckld_group_enroll_queue);
			});
		}, 2500);

		return () => {
			console.log("Results Polling Stopped");
			clearInterval(interval);
		};
	}, [processing]);


	// Not Ready to Render
	const isResolved = ckld_group_enroll_queue !== undefined && ckld_group_enroll_queue !== null;

	if (isResolved) {
		const displayCheck = ckld_group_enroll_queue.status !== 'idle';
		console.log({
			func: 'CKLDGroupEnroll:Data Set',
			ckld_group_enroll_queue: ckld_group_enroll_queue,
			displayCheck: displayCheck,
			status: ckld_group_enroll_queue.status
		});
		//[ 'run', 'processing', 'completed', 'cancelled' ].includes( ckld_group_enroll_queue.status );
		if (displayCheck !== isDisplay) {
			setIsDisplay(displayCheck);
		}

		if (ckld_group_enroll_queue.status === 'processing' || ckld_group_enroll_queue.status === 'run') {
			if (!processing) {
				setProcessing(true);
			}
		} else if (ckld_group_enroll_queue.status === 'completed' || ckld_group_enroll_queue.status === 'cancelled') {
			if (processing) {
				setProcessing(false);
			}
		}

	} else {
		if (ckld_group_enroll_queue === null) {
			console.log('CKLDGroupEnroll:ckld_group_enroll_queue is null bad data');
		}
		// Placeholder
		return (
			<Fragment>
				<Panel
					header={__('Enroll Users to LearnDash Groups', 'ck-ld-group-enroll')}
					className="ck-ld-group-enroll-panel"
				>
					<PanelRow className="ck-ld-group-enroll-loading">
						<Spinner />
						<span className="ck-ld-group-enroll-loading-text">
							{__('Loading...', 'ck-ld-group-enroll')}
						</span>
					</PanelRow>
				</Panel>
			</Fragment>
		);

	}

	if (isDisplay) {

		// Show Results or Running Process
		return (
			<Fragment key="ck-ld-group-enroll">
				<CKLDGroupEnrollResults
					key="ck-ld-group-enroll-process-results"
					{...ckld_group_enroll_queue}
					onChange={(e) => updateCKLDEnrollQueue(e)}
				/>
			</Fragment>
		);

	} else {

		// Show Form
		return (
			<Fragment key="ck-ld-group-enroll">
				<CKLDGroupEnrollForm
					key="ck-ld-group-enroll-process-form"
					onChange={(e) => updateCKLDEnrollQueue(e)}
				/>
			</Fragment>
		);

	}
}

// Render the component
document.addEventListener('DOMContentLoaded', () => {
	const htmlOutput = document.getElementById("ck-ld-group-enroll-admin")
	if (htmlOutput) {
		render(
			<CKLDGroupEnroll />,
			htmlOutput
		);
	}
});