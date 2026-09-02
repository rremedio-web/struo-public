function setNodeInert(node, isInert) {
	if (!node) {
		return;
	}
	node.inert = !!isInert;
	if (isInert) {
		node.setAttribute('inert', '');
		node.setAttribute('aria-hidden', 'true');
	} else {
		node.removeAttribute('inert');
		node.removeAttribute('aria-hidden');
	}
	node.querySelectorAll('a, button, input, select, textarea, [tabindex]').forEach((el) => {
		if (isInert) {
			if (!Object.prototype.hasOwnProperty.call(el.dataset, 'saePrevTabindex')) {
				el.dataset.saePrevTabindex = el.hasAttribute('tabindex') ? el.getAttribute('tabindex') : '';
			}
			el.setAttribute('tabindex', '-1');
			return;
		}
		if (!Object.prototype.hasOwnProperty.call(el.dataset, 'saePrevTabindex')) {
			return;
		}
		const prev = el.dataset.saePrevTabindex;
		if (prev === '') {
			el.removeAttribute('tabindex');
		} else {
			el.setAttribute('tabindex', prev);
		}
		delete el.dataset.saePrevTabindex;
	});
}

function getModalFocusable(overlay) {
	if (!overlay) {
		return [];
	}
	const selector =
		'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
	return Array.from(overlay.querySelectorAll(selector)).filter((node) => {
		if (!(node instanceof HTMLElement) || node.hidden) {
			return false;
		}
		if (node.closest('[hidden]')) {
			return false;
		}
		return true;
	});
}

function bindModalFocusTrap(overlay) {
	if (!overlay || overlay.dataset.saeFocusTrap === '1') {
		return;
	}
	overlay.dataset.saeFocusTrap = '1';
	overlay.addEventListener('keydown', (event) => {
		if (event.key !== 'Tab' || overlay.hidden) {
			return;
		}
		const nodes = getModalFocusable(overlay);
		if (!nodes.length) {
			event.preventDefault();
			return;
		}
		const first = nodes[0];
		const last = nodes[nodes.length - 1];
		const active = document.activeElement;
		if (event.shiftKey) {
			if (active === first || !overlay.contains(active)) {
				event.preventDefault();
				last.focus();
			}
			return;
		}
		if (active === last) {
			event.preventDefault();
			first.focus();
		}
	});
}

function showModal(overlay, focusEl) {
	if (!overlay) {
		return;
	}
	bindModalFocusTrap(overlay);
	if (overlay.hidden) {
		overlay._saeRestoreFocus =
			document.activeElement instanceof HTMLElement ? document.activeElement : null;
	}
	overlay.hidden = false;
	window.requestAnimationFrame(() => {
		const target = focusEl || getModalFocusable(overlay)[0];
		if (target && typeof target.focus === 'function') {
			target.focus();
		}
	});
}

function hideModal(overlay) {
	if (!overlay || overlay.hidden) {
		return;
	}
	const restore = overlay._saeRestoreFocus instanceof HTMLElement ? overlay._saeRestoreFocus : null;
	overlay._saeRestoreFocus = null;
	overlay.hidden = true;
	if (restore && document.contains(restore) && typeof restore.focus === 'function') {
		restore.focus();
	}
}
