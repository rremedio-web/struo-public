function getAuditSeverityWeight(severity) {
	const normalized = String(severity || '')
		.trim()
		.toLowerCase();
	if (normalized === 'high') {
		return 3;
	}
	if (normalized === 'medium') {
		return 2;
	}
	if (normalized === 'low') {
		return 1;
	}
	return 0;
}

function buildAuditFollowUpState(previousSnapshot, nextSnapshot) {
	const previous = normalizeScoutAuditSnapshot(previousSnapshot);
	const next = normalizeScoutAuditSnapshot(nextSnapshot);
	if (!next) {
		return {
			state: 'error',
			headline: 'Fresh audit unavailable',
			summary: 'The change was applied, but the page could not be re-scored automatically.',
			improvements: [],
			next_actions: [],
		};
	}

	const previousScore = Number(previous && previous.audit ? previous.audit.score : 0) || 0;
	const nextScore = Number(next.audit && next.audit.score ? next.audit.score : 0) || 0;
	const scoreDelta = previous ? nextScore - previousScore : 0;
	const previousIssues =
		previous && previous.audit && Array.isArray(previous.audit.issues) ? previous.audit.issues : [];
	const nextIssues = next.audit && Array.isArray(next.audit.issues) ? next.audit.issues : [];
	const nextSignatures = new Set(
		nextIssues.map((issue) => buildScoutIssueSignature(issue)).filter(Boolean),
	);
	const resolvedIssues = previousIssues
		.filter((issue) => {
			const signature = buildScoutIssueSignature(issue);
			return signature && !nextSignatures.has(signature);
		})
		.slice(0, 3);
	const previousIssueCount =
		previous && previous.audit
			? Number(previous.audit.issue_count || previousIssues.length) || previousIssues.length
			: 0;
	const nextIssueCount = Number(next.audit.issue_count || nextIssues.length) || nextIssues.length;
	const improvements = [];

	if (previous && previousScore > 0 && nextScore > 0) {
		if (scoreDelta > 0) {
			improvements.push(`Score improved from ${previousScore}/10 to ${nextScore}/10.`);
		} else if (scoreDelta < 0) {
			improvements.push(
				`Score moved from ${previousScore}/10 to ${nextScore}/10 after the latest change.`,
			);
		} else {
			improvements.push(`Score holds at ${nextScore}/10 after the latest change.`);
		}
	} else if (nextScore > 0) {
		improvements.push(`Fresh audit score: ${nextScore}/10.`);
	}

	if (resolvedIssues.length) {
		improvements.push(
			`Improved: ${resolvedIssues
				.map((issue) => issue.title)
				.filter(Boolean)
				.join(', ')}.`,
		);
	} else if (previous && previousIssueCount > nextIssueCount) {
		improvements.push(`Issue count dropped from ${previousIssueCount} to ${nextIssueCount}.`);
	}

	if (!nextIssueCount) {
		improvements.push('No remaining high-priority issues were flagged.');
	}

	const nextActions = Array.isArray(next.next_actions) ? next.next_actions.slice(0, 3) : [];
	const headline = !nextIssueCount
		? 'This page is strong now'
		: scoreDelta > 0
			? `Score improved to ${nextScore}/10`
			: nextScore > 0
				? `Fresh score: ${nextScore}/10`
				: 'Fresh audit ready';

	return {
		state: 'ready',
		headline,
		summary:
			String(next.audit.summary || '').trim() ||
			(!nextIssueCount
				? 'This page is strong. No high-priority fixes recommended.'
				: 'Fresh audit ready.'),
		score: nextScore,
		score_label: nextScore > 0 ? `${nextScore}/10` : '--',
		score_tone: getAuditScoreTone(nextScore),
		score_delta: scoreDelta,
		score_delta_label: scoreDelta > 0 ? `+${scoreDelta}` : `${scoreDelta}`,
		improvements,
		next_actions: nextActions,
		freshness_label: formatRelativeTime(next.analyzed_at || next.cached_at).short,
		issue_count: nextIssueCount,
	};
}

function buildAuditFollowUpLoadingState(previousSnapshot) {
	const previous = normalizeScoutAuditSnapshot(previousSnapshot);
	const previousScore = Number(previous && previous.audit ? previous.audit.score : 0) || 0;
	return {
		state: 'loading',
		headline: 'Re-scoring page',
		summary:
			previousScore > 0
				? `Checking what improved after the latest fix from ${previousScore}/10.`
				: 'Checking what improved after the latest fix.',
		improvements: [],
		next_actions: [],
	};
}

function buildAuditFollowUpErrorState() {
	return {
		state: 'error',
		headline: 'Fresh score unavailable',
		summary:
			'Saved to WordPress, but the follow-up audit did not finish. Refresh analysis to continue.',
		improvements: [],
		next_actions: [],
	};
}

function isActiveReceiptAuditFollowUp(token) {
	if (!token) {
		return false;
	}
	const payload =
		state.receiptPayload && typeof state.receiptPayload === 'object' ? state.receiptPayload : null;
	if (!payload || !els.receipt || els.receipt.hidden) {
		return false;
	}
	return String(payload.audit_follow_up_token || '').trim() === String(token).trim();
}
