import { dispatch, select, subscribe } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';

const PLUGIN = 'struo-gutenberg-host';
const SIDEBAR = 'brief';
const SIDEBAR_NAME = `${ PLUGIN }/${ SIDEBAR }`;

function openStruoSidebar() {
	const editPost = dispatch( 'core/edit-post' );
	if ( editPost && typeof editPost.openGeneralSidebar === 'function' ) {
		editPost.openGeneralSidebar( SIDEBAR_NAME );
	}
}

function currentEditorPostId() {
	const editor = select( 'core/editor' );
	if ( ! editor || typeof editor.getCurrentPostId !== 'function' ) {
		return 0;
	}
	const raw = Number( editor.getCurrentPostId() || 0 );
	return Number.isInteger( raw ) && raw > 0 ? raw : 0;
}

function emitEditorPostId( last ) {
	const id = currentEditorPostId();
	if ( ! id || id === last.current ) {
		return;
	}
	last.current = id;
	if ( window.saeConsoleConfig ) {
		window.saeConsoleConfig.current_post_id = id;
	}
	window.dispatchEvent( new CustomEvent( 'struo-gutenberg-post-id', { detail: { postId: id } } ) );
}

function watchEditorPostId() {
	const last = { current: 0 };
	emitEditorPostId( last );
	return subscribe( () => {
		emitEditorPostId( last );
	} );
}

function adoptBriefHost( slot ) {
	const host = document.getElementById( 'sae-gutenberg-host' );
	if ( ! slot || ! host || host.parentElement === slot ) {
		return;
	}
	host.hidden = false;
	slot.appendChild( host );
}

function invalidateEditorPost( postId ) {
	const id = Number( postId || 0 );
	if ( ! Number.isInteger( id ) || id <= 0 || id !== currentEditorPostId() ) {
		return;
	}
	const editor = select( 'core/editor' );
	const type = editor && typeof editor.getCurrentPostType === 'function'
		? editor.getCurrentPostType()
		: 'post';
	if ( ! type ) {
		return;
	}
	const core = dispatch( 'core' );
	if ( core && typeof core.invalidateResolution === 'function' ) {
		core.invalidateResolution( 'getEntityRecord', [ 'postType', type, id ] );
	}
}

function StruoHost() {
	useEffect( () => {
		openStruoSidebar();
		const again = window.setTimeout( openStruoSidebar, 250 );
		const unsub = watchEditorPostId();
		const onApplied = ( event ) => {
			invalidateEditorPost( event && event.detail ? event.detail.postId : 0 );
		};
		window.addEventListener( 'struo-mutation-applied', onApplied );
		return () => {
			window.clearTimeout( again );
			if ( typeof unsub === 'function' ) {
				unsub();
			}
			window.removeEventListener( 'struo-mutation-applied', onApplied );
		};
	}, [] );

	return (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR }>
				Struo
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={ SIDEBAR }
				title="Struo"
				icon="superhero-alt"
				isPinnable
			>
				<div
					className="sae-gutenberg-slot"
					ref={ adoptBriefHost }
				/>
			</PluginSidebar>
		</>
	);
}

registerPlugin( PLUGIN, {
	render: StruoHost,
	icon: 'superhero-alt',
} );
