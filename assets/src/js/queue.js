function getAgentPlanItems(status = state.status) {
	const items = status && Array.isArray(status.agent_plans) ? status.agent_plans : [];
	return items.filter((item) => item && item.id);
}

function getDiscoveryPlanItems(status = state.status) {
	const items = status && Array.isArray(status.discovery_plans) ? status.discovery_plans : [];
	return items.filter((item) => item && item.id);
}

function renderDiscoveryPlans(status = state.status) {
	if (!els.agentPlans || !els.agentPlansList) {
		return;
	}
	const items = getDiscoveryPlanItems(status);
	if (!items.length) {
		els.agentPlans.hidden = true;
		els.agentPlansList.innerHTML = '';
		return;
	}

	els.agentPlans.hidden = false;
	els.agentPlansList.innerHTML = items
		.map((item) => {
			const created = formatRelativeTime(item.created_at || '');
			const origin = String(item.origin || '') === 'mcp' ? 'Agent' : 'You';
			const title = String(item.request || item.post_title || '').trim() || 'Waiting plan';
			const payloadType = String(item.payload_type || '');
			const kind = payloadType === 'page_spec_v1' ? 'New page' : 'These pages';
			return `
				<article class="sae-agent-plan" data-agent-plan-id="${escapeHtml(item.id)}" data-origin="${escapeHtml(String(item.origin || ''))}">
					<p class="sae-agent-plan__title">${escapeHtml(title)}</p>
					<p class="sae-agent-plan__meta">${escapeHtml(`${origin} · ${kind} · ${created.short}`)}</p>
					<div class="sae-agent-plan__actions">
						<button type="button" class="button button-primary" data-agent-plan-action="open">${escapeHtml('Open')}</button>
						<button type="button" class="button button-secondary" data-agent-plan-action="dismiss">${escapeHtml('Dismiss')}</button>
					</div>
				</article>
			`;
		})
		.join('');
}

function renderAgentPlansInbox(status = state.status) {
	if (!els.agentPlans || !els.agentPlansList) {
		return;
	}
	const items = getAgentPlanItems(status);
	if (!items.length) {
		els.agentPlans.hidden = true;
		els.agentPlansList.innerHTML = '';
		return;
	}

	els.agentPlans.hidden = false;
	els.agentPlansList.innerHTML = items
		.map((item) => {
			const created = formatRelativeTime(item.created_at || '');
			const title = item.post_title || `Post ${item.post_id || ''}`.trim() || 'Allowlisted page';
			const operation = String(item.operation || 'update');
			const request = String(item.request || '').trim();
			return `
				<article class="sae-agent-plan" data-agent-plan-id="${escapeHtml(item.id)}">
					<p class="sae-agent-plan__title">${escapeHtml(title)}</p>
					<p class="sae-agent-plan__meta">${escapeHtml(`${operation} · ${created.short}`)}</p>
					${request ? `<p class="sae-agent-plan__request">${escapeHtml(request)}</p>` : ''}
					<div class="sae-agent-plan__actions">
						<button type="button" class="button button-primary" data-agent-plan-action="review">${escapeHtml('Review')}</button>
						<button type="button" class="button button-secondary" data-agent-plan-action="dismiss">${escapeHtml('Dismiss')}</button>
					</div>
				</article>
			`;
		})
		.join('');
}

function agentPlansEndpoint(planId, suffix = '') {
	const base = typeof rest.agent_plans === 'string' ? rest.agent_plans.replace(/\/$/, '') : '';
	if (!base || !/^[a-f0-9]{16}$/.test(String(planId || ''))) {
		return '';
	}
	return suffix ? `${base}/${planId}/${suffix}` : `${base}/${planId}`;
}
