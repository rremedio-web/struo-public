import { createRoot, StrictMode } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';

const config = window.saeConsoleConfig || {};
if ( config.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
}

export function mountWorkQueue( root ) {
	createRoot( root ).render(
		<StrictMode>
			<App />
		</StrictMode>
	);
}

const rootEl = document.getElementById( 'sae-work-queue' );
if ( rootEl ) {
	mountWorkQueue( rootEl );
}
