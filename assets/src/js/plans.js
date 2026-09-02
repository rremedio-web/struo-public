async function previewAgentPlan(planId) {
	const endpoint = agentPlansEndpoint(planId, 'preview');
	if (!endpoint) {
		setComposeStatus('That agent plan is no longer available.', 'error');
		return;
	}
	if (state.agentPlanBusy) {
		return;
	}
	state.agentPlanBusy = true;
	setComposeStatus(
		isSimpleMode() ? 'Opening the agent plan…' : 'Loading agent plan review…',
		'loading',
	);
	setReviewLoadingStage('parsing', {
		requestText: 'Agent plan',
		targetLabel: '',
	});
	try {
		const plan = await requestJson(endpoint, { method: 'POST', body: {} });
		state.plan = plan;
		notifyWorkQueueSelect(plan);
		selectResolvedPlanPost(plan);
		renderPlan();
		if (els.planContent && !els.planContent.hidden) {
			els.planContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		setComposeStatus(
			isSimpleMode()
				? 'Agent plan ready. Approve when it looks right.'
				: 'Agent plan loaded. Approve, then apply via plan_id.',
			'info',
		);
		void loadStatus();
	} catch (error) {
		setComposeStatus(getFriendlyErrorMessage(error, 'Could not review the agent plan.'), 'error');
	} finally {
		state.agentPlanBusy = false;
		setReviewLoadingStage('');
	}
}

async function approveDurablePlan(planId) {
	const endpoint = agentPlansEndpoint(planId, 'approve');
	if (!endpoint) {
		setApplyStatus('Missing durable plan id.', 'error');
		return null;
	}
	if (!canApprovePlans()) {
		setApplyStatus('You do not have permission to approve plans.', 'error');
		return null;
	}
	setApplyStatus('Approving plan…', 'loading');
	const body = {};
	if (state.plan && isBundlePlan(state.plan) && getPlanEnvelopeId(state.plan) === planId) {
		const revision = Number(state.plan.select_revision);
		body.select_revision = Number.isFinite(revision) ? revision : 0;
	}
	const response = await requestJson(endpoint, { method: 'POST', body });
	if (state.plan && getPlanEnvelopeId(state.plan) === planId) {
		if (response && response.plan && isBundlePlan(response.plan)) {
			state.plan = response.plan;
		} else {
			state.plan.plan_state = 'approved';
			if (response && response.plan && response.plan.state) {
				state.plan.plan_state = String(response.plan.state);
			}
		}
		renderPlan();
	}
	setApplyStatus(
		isSimpleMode()
			? 'Plan approved. You can apply when ready.'
			: 'Plan approved via struo_approve.',
		'info',
	);
	return response;
}

async function applyDurablePlan(planId) {
	const endpoint = agentPlansEndpoint(planId, 'apply');
	if (!endpoint) {
		setApplyStatus('Missing durable plan id.', 'error');
		return null;
	}
	if (!canApplyPlans()) {
		setApplyStatus('You do not have permission to apply plans.', 'error');
		return null;
	}
	setApplyStatus('Applying approved plan…', 'loading');
	els.apply.disabled = true;
	try {
		const response = await requestJson(endpoint, { method: 'POST', body: {} });
		return response;
	} finally {
		updateApplyButtonState();
	}
}

async function dismissAgentPlan(planId) {
	const endpoint = agentPlansEndpoint(planId, 'dismiss');
	if (!endpoint || state.agentPlanBusy) {
		return;
	}
	state.agentPlanBusy = true;
	try {
		await requestJson(endpoint, { method: 'POST', body: {} });
		if (state.plan && getPlanEnvelopeId(state.plan) === String(planId)) {
			resetPlanState();
		}
		setComposeStatus('Agent plan dismissed.', 'info');
		await loadStatus();
	} catch (error) {
		setComposeStatus(getFriendlyErrorMessage(error, 'Could not dismiss the agent plan.'), 'error');
	} finally {
		state.agentPlanBusy = false;
	}
}

function handleAgentPlansClick(event) {
	const button =
		event.target instanceof Element ? event.target.closest('[data-agent-plan-action]') : null;
	if (!button || !els.agentPlansList || !els.agentPlansList.contains(button)) {
		return;
	}
	const article = button.closest('[data-agent-plan-id]');
	const planId = article ? String(article.getAttribute('data-agent-plan-id') || '') : '';
	const action = String(button.getAttribute('data-agent-plan-action') || '');
	if (action === 'open' || action === 'review') {
		void previewAgentPlan(planId);
		return;
	}
	if (action === 'dismiss') {
		void dismissAgentPlan(planId);
	}
}
