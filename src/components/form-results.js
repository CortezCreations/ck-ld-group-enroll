/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Dashicon, ExternalLink, Panel, PanelRow } from '@wordpress/components';

const CKLDGroupEnrollResults = (props) => {

	const {
		status,
		title,
		messaging,
		onChange,
	} = props;

	let { results } = props;
	const loadedResults = { ...results };

	// No Results array
	if (!results?.length) {
		results = [
			{
				email: '',
				message: __('No Results', 'ck-ld-group-enroll'),
				status: '',
			}
		];
	}

	// Prepare Button
	let buttonLabel = __('Start New Enrollment Process', 'ck-ld-group-enroll');
	let buttonClassNames = 'is-primary is-large';
	let buttonValue = 'reset';
	if (status === 'processing' || status === 'run') {
		buttonLabel = __('Cancel Running Process', 'ck-ld-group-enroll');
		buttonClassNames = 'is-primary is-large is-busy';
		buttonValue = 'cancel';
	}

	// Handle Form Submission
	const onFormSubmit = (event) => {
		// TODO: Add a confirmation dialog
		// TODO: Spread these from the props
		// Options for both reset and cancel
		let option = {
			user_ids: [],
			group_id: 0,
			admin_id: 0,
			courses: [],
			status: (event === 'reset') ? 'idle' : 'cancelled'
		};

		// Clear the rest for reset
		if (event === 'reset') {
			option.results = [];
			option.title = '';
			option.started = 0;
			option.completed = 0;
			option.messaging = [];
		} else {
			// Restore the results for cancel
			option.results = loadedResults;
			option.title = title;
			option.started = props.started,
				option.completed = props.completed,
				option.messaging = messaging;
		}

		console.log('ck-ld-group-enroll-results:onFormSubmit', {
			event: event,
			option: option
		});

		onChange(option);
	}

	const ProfileLink = ({ id }) => {
		if (!id) {
			return null;
		}
		return (
			<ExternalLink
				className="ck-ld-group-enroll-profile-link"
				target="_blank"
				href={`/wp-admin/user-edit.php?user_id=${id}`}
				title={__('View User Profile', 'ck-ld-group-enroll')}
			>
				<Dashicon icon="admin-users" />
			</ExternalLink>
		);
	}

	const StatusIcon = ({ status }) => {
		if (status === 0) {
			return <Dashicon icon="no" title={__('Error', 'ck-ld-group-enroll')} />;
		} else if (status === 1) {
			return <Dashicon icon="yes" title={__('Success', 'ck-ld-group-enroll')} />;
		} else {
			return null;
			//<Dashicon icon="minus" title={ __( 'Unknown', 'ck-ld-group-enroll') } />;
		}
	}

	return (
		<Panel
			key="ck-ld-group-enroll-results"
			header={title}
			className="ck-ld-group-enroll-panel ck-ld-group-enroll-results"
		>
			<PanelRow className="ck-ld-group-enroll-mobile-full-width">
				<ul className="ck-ld-group-enroll-results-message">
					<li
						key='status'
						className='ck-ld-group-enroll-results-message-status'>
						{__('Status', 'ck-ld-group-enroll')} : {status}
					</li>
					{messaging?.map((message, i) => (
						<li
							key={`${message.type}-${i}`}
							className={`ck-ld-group-enroll-results-message-${message.type}`}
						>
							{message.message}
						</li>
					))}
				</ul>
				<Button
					key={`ck-ld-group-enroll-submit-button`}
					className={buttonClassNames}
					value={buttonValue}
					onClick={() => onFormSubmit(buttonValue)}
				>
					{buttonLabel}
				</Button>
			</PanelRow>
			<table className="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<td>{__('User Email', 'ck-ld-group-enroll')}</td>
						<td>{__('Result', 'ck-ld-group-enroll')}</td>
					</tr>
				</thead>
				<tbody>
					{results?.map((result) => (
						<tr key={result.user_id}
							className={result.status === 0 ? 'ck-ld-group-enroll-error' : 'ck-ld-group-enroll-succcess'}
						>
							<td>
								<ProfileLink id={result.user_id} />
								{result.email}
							</td>
							<td>
								{result.message}
								<div className="ck-ld-group-enroll-results-status-icon">
									<StatusIcon status={result.status} />
								</div>
							</td>
						</tr>
					))}
				</tbody>
			</table>
		</Panel>
	);
}

export default CKLDGroupEnrollResults;