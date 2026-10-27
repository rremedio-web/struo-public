async function requestJson(url, options = {}) {
	const method = options.method || 'GET';
	const headers = {
		'X-WP-Nonce': config.nonce || '',
	};
	if (method !== 'GET') {
		headers['Content-Type'] = 'application/json';
	}

	const controller = new AbortController();
	const externalSignal =
		options.signal && typeof options.signal.addEventListener === 'function' ? options.signal : null;
	const externalAbortListener = () => controller.abort();
	if (externalSignal) {
		if (externalSignal.aborted) {
			controller.abort();
		} else {
			externalSignal.addEventListener('abort', externalAbortListener, { once: true });
		}
	}
	const timeoutMs =
		Number.isFinite(Number(options.timeoutMs)) && Number(options.timeoutMs) > 0
			? Number(options.timeoutMs)
			: REQUEST_TIMEOUT_MS;
	const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

	let response;
	try {
		response = await fetch(url, {
			method,
			credentials: 'same-origin',
			headers,
			body: method !== 'GET' && options.body ? JSON.stringify(options.body) : undefined,
			cache: typeof options.cache === 'string' && options.cache ? options.cache : undefined,
			signal: controller.signal,
		});
	} catch (error) {
		if (error && error.name === 'AbortError') {
			if (externalSignal && externalSignal.aborted) {
				throw { code: 'request_aborted', message: 'Request cancelled.' };
			}
			throw {
				code: 'timeout',
				message: `Request timed out after ${Math.floor(timeoutMs / 1000)}s.`,
			};
		}
		throw {
			code: 'network_error',
			message: config.strings?.network_error || 'Network request failed.',
		};
	} finally {
		window.clearTimeout(timeoutId);
		if (externalSignal) {
			externalSignal.removeEventListener('abort', externalAbortListener);
		}
	}

	let payload = null;
	try {
		payload = await response.json();
	} catch (error) {
		payload = null;
	}

	if (!response.ok) {
		const code = payload && payload.code ? payload.code : `http_${response.status}`;
		const message =
			payload && payload.message ? payload.message : `Request failed (${response.status}).`;
		throw { code, message, status: response.status, payload };
	}

	return payload;
}
