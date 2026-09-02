import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

function isSimpleMode() {
	try {
		return window.localStorage.getItem( 'sae_console_mode_v1' ) !== 'dev';
	} catch ( error ) {
		return true;
	}
}

function originLabel( origin ) {
	return origin === 'mcp' ? 'Agent' : 'You';
}

function publicLabel( status ) {
	if ( status === 'ok' ) {
		return 'Public page matches';
	}
	if ( status === 'skipped' ) {
		return 'Public page skipped (draft or private)';
	}
	if ( status === 'failed' ) {
		return 'Public page not confirmed';
	}
	return 'Public page not checked';
}

export default function App() {
	const [ items, setItems ] = useState( [] );
	const [ selectedId, setSelectedId ] = useState( '' );
	const [ detail, setDetail ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( '' );

	const loadList = useCallback( async () => {
		const data = await apiFetch( { path: '/struo/v1/console/agent-plans' } );
		setItems( Array.isArray( data?.items ) ? data.items : [] );
	}, [] );

	const loadCard = useCallback( async ( planId ) => {
		if ( ! planId ) {
			setDetail( null );
			return;
		}
		const data = await apiFetch( { path: `/struo/v1/console/agent-plans/${ planId }` } );
		setDetail( data );
	}, [] );

	useEffect( () => {
		loadList().catch( () => {
			setNotice( 'Could not load the Work Queue.' );
		} );
	}, [ loadList ] );

	useEffect( () => {
		const onSelect = ( event ) => {
			const planId = event?.detail?.planId ? String( event.detail.planId ) : '';
			if ( ! planId ) {
				return;
			}
			setSelectedId( planId );
			loadCard( planId ).catch( () => {
				setNotice( 'Could not open that plan.' );
			} );
			loadList().catch( () => {} );
		};
		const onRefresh = () => {
			loadList().catch( () => {} );
		};
		window.addEventListener( 'struo-work-queue-select', onSelect );
		window.addEventListener( 'struo-work-queue-refresh', onRefresh );
		return () => {
			window.removeEventListener( 'struo-work-queue-select', onSelect );
			window.removeEventListener( 'struo-work-queue-refresh', onRefresh );
		};
	}, [ loadCard, loadList ] );

	async function openCard( planId ) {
		setSelectedId( planId );
		setNotice( '' );
		try {
			await loadCard( planId );
		} catch ( error ) {
			setNotice( 'Could not open that plan.' );
		}
	}

	async function postPlan( planId, suffix, extra = {} ) {
		return apiFetch( {
			path: `/struo/v1/console/agent-plans/${ planId }/${ suffix }`,
			method: 'POST',
			data: extra,
		} );
	}

	async function onApply() {
		const planId = selectedId;
		const state = String( detail?.plan?.state || '' );
		if ( ! planId || busy ) {
			return;
		}
		setBusy( true );
		setNotice( '' );
		try {
			if ( 'planned' === state ) {
				await postPlan( planId, 'approve' );
			}
			const applied = await postPlan( planId, 'apply' );
			const outcome = String( applied?.outcome || '' );
			if ( outcome === 'safe_to_retry' ) {
				setNotice( 'Safe to retry. Apply again when you are ready.' );
			} else if ( outcome === 'recovered_persisted' ) {
				setNotice( 'Saved to WordPress. Recovered without writing twice.' );
			} else if ( applied?.code === 'sae_outcome_unknown_conflict' ) {
				setNotice( 'Named conflict — Struo will not guess.' );
			}
			if ( outcome !== 'safe_to_retry' && applied?.code !== 'sae_outcome_unknown_conflict' ) {
				const postId = Number(
					applied?.plan?.post_id
					|| applied?.post_id
					|| detail?.plan?.post_id
					|| detail?.plan?.post?.post_id
					|| 0
				);
				window.dispatchEvent( new CustomEvent( 'struo-mutation-applied', { detail: { postId } } ) );
			}
			await loadCard( planId );
			await loadList();
		} catch ( error ) {
			const code = error?.code ? String( error.code ) : '';
			if ( code === 'sae_outcome_unknown_conflict' ) {
				setNotice( 'Named conflict — Struo will not guess.' );
			} else {
				setNotice( error?.message || 'Apply failed.' );
			}
			await loadCard( planId ).catch( () => {} );
		} finally {
			setBusy( false );
		}
	}

	async function onDismiss() {
		if ( ! selectedId || busy ) {
			return;
		}
		setBusy( true );
		try {
			await postPlan( selectedId, 'dismiss' );
			setSelectedId( '' );
			setDetail( null );
			await loadList();
		} catch ( error ) {
			setNotice( error?.message || 'Could not dismiss.' );
		} finally {
			setBusy( false );
		}
	}

	async function onRollback() {
		if ( ! selectedId || busy ) {
			return;
		}
		setBusy( true );
		setNotice( '' );
		try {
			const queued = await postPlan( selectedId, 'rollback' );
			const newId = String( queued?.plan_id || '' );
			await loadList();
			if ( newId ) {
				setSelectedId( newId );
				await loadCard( newId );
			}
		} catch ( error ) {
			setNotice( error?.message || 'Could not queue rollback.' );
		} finally {
			setBusy( false );
		}
	}

	const plan = detail?.plan || null;
	const evidence = detail?.evidence || null;
	const recovery = detail?.recovery || null;
	const publicStatus = evidence?.public?.status ? String( evidence.public.status ) : '';
	const fieldRows = [];
	if ( plan?.payload?.fields && typeof plan.payload.fields === 'object' ) {
		Object.keys( plan.payload.fields ).forEach( ( key ) => {
			fieldRows.push( { key, value: String( plan.payload.fields[ key ] ?? '' ) } );
		} );
	}

	return (
		<section className="sae-work-queue__panel">
			<h2 className="sae-work-queue__title">Work Queue</h2>
			<p className="sae-help">Changes you planned and changes an agent queued. Only you can apply.</p>
			{ notice ? <p className="sae-status" role="status">{ notice }</p> : null }
			{ items.length === 0 ? (
				<p className="sae-work-queue__empty">No plans in the queue.</p>
			) : (
				<ul className="sae-work-queue__list">
					{ items.map( ( item ) => {
						const id = String( item.id || '' );
						const persisted = item.queue?.persisted;
						return (
							<li key={ id } className={ id === selectedId ? 'is-open' : '' }>
								<button type="button" className="sae-work-queue__row" onClick={ () => openCard( id ) }>
									<span className="sae-work-queue__row-title">{ item.request || item.post_title || id }</span>
									<span className="sae-work-queue__row-meta">
										{ originLabel( item.origin ) }
										{ ' · ' }
										{ item.post_title || 'Allowlisted page' }
										{ ' · ' }
										{ item.state }
									</span>
									{ item.state === 'applied' && persisted ? (
										<span className="sae-work-queue__row-evidence">
											{ persisted.match ? 'Saved to WordPress' : 'Saved copy does not match' }
										</span>
									) : null }
								</button>
							</li>
						);
					} ) }
				</ul>
			) }
			{ plan ? (
				<article className="sae-work-queue__card">
					<p className="sae-work-queue__brief">{ plan.request || '' }</p>
					<p className="sae-muted">
						{ originLabel( plan.origin ) }
						{ ' · ' }
						{ plan.post_title || plan.post?.post_title || '' }
						{ ' · ' }
						{ plan.state }
					</p>
					{ fieldRows.length ? (
						<div className="sae-work-queue__diff">
							{ fieldRows.map( ( row ) => (
								<p key={ row.key }>
									<span className="sae-work-queue__after">{ row.key }: { row.value }</span>
								</p>
							) ) }
						</div>
					) : null }
					{ recovery?.outcome ? (
						<p className="sae-work-queue__recovery">{ String( recovery.outcome ).replace( /_/g, ' ' ) }</p>
					) : null }
					{ evidence ? (
						<div className="sae-work-queue__truths">
							<div>
								<p className="sae-work-queue__truth-label">WordPress</p>
								<p>{ evidence.persisted?.match ? 'Saved to WordPress' : 'Not saved as approved' }</p>
							</div>
							<div>
								<p className="sae-work-queue__truth-label">Public page</p>
								<p>{ publicLabel( publicStatus ) }</p>
							</div>
						</div>
					) : null }
					<div className="sae-work-queue__actions">
						{ plan.state === 'planned' || plan.state === 'approved' || plan.state === 'applying' ? (
							<button type="button" className="button button-primary" disabled={ busy } onClick={ onApply }>
								{ plan.state === 'planned' && ! isSimpleMode() ? 'Approve and apply' : 'Apply' }
							</button>
						) : null }
						{ plan.state === 'applied' ? (
							<button type="button" className="button button-primary" disabled={ busy } onClick={ onRollback }>
								Queue rollback
							</button>
						) : null }
						{ plan.state === 'planned' || plan.state === 'approved' ? (
							<button type="button" className="button button-secondary" disabled={ busy } onClick={ onDismiss }>
								Dismiss
							</button>
						) : null }
					</div>
				</article>
			) : null }
		</section>
	);
}
