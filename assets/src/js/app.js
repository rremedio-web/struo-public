	const els = {
		headerSubtitle: document.getElementById('sae-header-subtitle'),
		platformHint: document.getElementById('sae-platform-hint'),
		request: document.getElementById('sae-request'),
		quickActions: document.getElementById('sae-quick-actions'),
		quickActionsBlog: document.getElementById('sae-quick-actions-blog'),
		quickstart: document.getElementById('sae-quickstart'),
		quickstartDynamic: document.getElementById('sae-quickstart-dynamic'),
		composeTitle: document.getElementById('sae-compose-title'),
		composeSubtitle: document.getElementById('sae-compose-subtitle'),
		requestLabel: document.getElementById('sae-request-label'),
		postHelp: document.getElementById('sae-post-help'),
		postCombobox: document.getElementById('sae-post-combobox'),
		postFilter: document.getElementById('sae-post-filter'),
		postClear: document.getElementById('sae-post-clear'),
		postComboboxMeta: document.getElementById('sae-post-combobox-meta'),
		postComboboxList: document.getElementById('sae-post-combobox-list'),
		postTypeFilter: document.getElementById('sae-post-type-filter'),
		postId: document.getElementById('sae-post-id'),
		intentRail: document.getElementById('sae-intent-rail'),
		intentRailHint: document.getElementById('sae-intent-rail-hint'),
		stepTarget: document.getElementById('sae-step-target'),
		stepTargetSummary: document.getElementById('sae-step-target-summary'),
		stepTargetPicked: document.getElementById('sae-step-target-picked'),
		stepTargetTag: document.getElementById('sae-step-target-tag'),
		stepTargetChange: document.getElementById('sae-step-target-change'),
		stepTargetBody: document.getElementById('sae-step-target-body'),
		stepPlan: document.getElementById('sae-step-plan'),
		scoutLive: document.getElementById('sae-scout-live'),
		scoutLiveToggle: document.getElementById('sae-scout-live-toggle'),
		scoutLiveText: document.getElementById('sae-scout-live-text'),
		scoutLivePanel: document.getElementById('sae-scout-live-panel'),
		agentPlans: document.getElementById('sae-agent-plans'),
		agentPlansList: document.getElementById('sae-agent-plans-list'),
		findings: document.getElementById('sae-findings'),
		findingsList: document.getElementById('sae-findings-list'),
		advanced: document.getElementById('sae-advanced'),
		batchMode: document.getElementById('sae-batch-mode'),
		batchModeToggle: document.getElementById('sae-batch-mode-toggle'),
		singleModeWrap: document.getElementById('sae-single-mode-wrap'),
		batchModeWrap: document.getElementById('sae-batch-mode-wrap'),
		batchBundleName: document.getElementById('sae-batch-bundle-name'),
		batchList: document.getElementById('sae-batch-list'),
		batchAddUpdate: document.getElementById('sae-batch-add-update'),
		batchAddInsert: document.getElementById('sae-batch-add-insert'),
		batchAddRemove: document.getElementById('sae-batch-add-remove'),
		batchClear: document.getElementById('sae-batch-clear'),
		blockBrowser: document.getElementById('sae-block-browser'),
		blockBrowserStatus: document.getElementById('sae-block-browser-status'),
		blockBrowserList: document.getElementById('sae-block-browser-list'),
		blockBrowserRefresh: document.getElementById('sae-block-browser-refresh'),
		responseMode: document.getElementById('sae-response-mode'),
		preferAi: document.getElementById('sae-prefer-ai'),
		dryRunPreview: document.getElementById('sae-dry-run-preview'),
		generatePlan: document.getElementById('sae-generate-plan'),
		clear: document.getElementById('sae-clear'),
		refreshStatus: document.getElementById('sae-refresh-status'),
		refreshHistory: document.getElementById('sae-refresh-history'),
		appModeToggle: document.getElementById('sae-toggle-app-mode'),
		modeToggle: document.getElementById('sae-toggle-console-mode'),
		adminEscape: document.getElementById('sae-admin-escape'),
		composeStatus: document.getElementById('sae-compose-status'),
		rateLimitHint: document.getElementById('sae-rate-limit-hint'),
		reviewTitle: document.getElementById('sae-review-title'),
		reviewSubtitle: document.getElementById('sae-review-subtitle'),
		planEmpty: document.getElementById('sae-plan-empty'),
		planEmptyTitle: document.getElementById('sae-plan-empty-title'),
		planEmptySubtitle: document.getElementById('sae-plan-empty-subtitle'),
		planEmptyBody: document.getElementById('sae-plan-empty-body'),
		planContent: document.getElementById('sae-plan-content'),
		planRequest: document.getElementById('sae-plan-request'),
		simpleSummary: document.getElementById('sae-simple-summary'),
		simpleSummaryEyebrow: document.getElementById('sae-simple-summary-eyebrow'),
		simpleSummaryStatus: document.getElementById('sae-simple-summary-status'),
		simpleSummaryHeadline: document.getElementById('sae-simple-summary-headline'),
		simpleSummaryDetails: document.getElementById('sae-simple-summary-details'),
		simpleSummaryGlance: document.getElementById('sae-simple-summary-glance'),
		simpleSummarySignals: document.getElementById('sae-simple-summary-signals'),
		simpleSummaryConfidence: document.getElementById('sae-simple-summary-confidence'),
		simpleSummaryScope: document.getElementById('sae-simple-summary-scope'),
		simpleSummarySafety: document.getElementById('sae-simple-summary-safety'),
		simplePreview: document.getElementById('sae-simple-preview'),
		simplePreviewList: document.getElementById('sae-simple-preview-list'),
		planSummary: document.getElementById('sae-plan-summary'),
		planBadges: document.getElementById('sae-plan-badges'),
		planWarnings: document.getElementById('sae-plan-warnings'),
		vectorSourcesWrap: document.getElementById('sae-vector-sources-wrap'),
		vectorSourcesList: document.getElementById('sae-vector-sources-list'),
		askWrap: document.getElementById('sae-ask-wrap'),
		askList: document.getElementById('sae-ask-list'),
		devDetails: document.getElementById('sae-dev-details'),
		planPayloadWrap: document.getElementById('sae-plan-payload-wrap'),
		planPayload: document.getElementById('sae-plan-payload'),
		planDryRun: document.getElementById('sae-plan-dry-run'),
		dryRunWrap: document.getElementById('sae-dry-run-wrap'),
		planDiffWrap: document.getElementById('sae-plan-diff-wrap'),
		planDiffList: document.getElementById('sae-plan-diff-list'),
		bundleWrap: document.getElementById('sae-bundle-wrap'),
		bundleMeta: document.getElementById('sae-bundle-meta'),
		bundleRows: document.getElementById('sae-bundle-rows'),
		bundleApprove: document.getElementById('sae-bundle-approve'),
		bundleApplyRemaining: document.getElementById('sae-bundle-apply-remaining'),
		bundleDismiss: document.getElementById('sae-bundle-dismiss'),
		bundleSkipped: document.getElementById('sae-bundle-skipped'),
		tokenWrap: document.getElementById('sae-token-wrap'),
		tokenValue: document.getElementById('sae-token-value'),
		tokenExpiry: document.getElementById('sae-token-expiry'),
		removeConfirmWrap: document.getElementById('sae-remove-confirm-wrap'),
		removeConfirmInput: document.getElementById('sae-remove-confirm-input'),
		planActions: document.getElementById('sae-plan-actions'),
		apply: document.getElementById('sae-apply'),
		cancelPlan: document.getElementById('sae-cancel-plan'),
		applyStatus: document.getElementById('sae-apply-status'),
		receipt: document.getElementById('sae-receipt'),
		consoleRoot: document.getElementById('sae-console-root') || document.getElementById('sae-gutenberg-host'),
		primaryGrid: document.getElementById('sae-primary-grid'),
		secondaryGrid: document.getElementById('sae-secondary-grid'),
		composePanel: document.getElementById('sae-compose-panel'),
		reviewPanel: document.getElementById('sae-review-panel'),
		reviewLoading: document.getElementById('sae-review-loading'),
		reviewLoadingTitle: document.getElementById('sae-review-loading-title'),
		reviewLoadingStage: document.getElementById('sae-review-loading-stage'),
		reviewLoadingTimeline: document.getElementById('sae-review-loading-timeline'),
		reviewLoadingRequest: document.getElementById('sae-review-loading-request'),
		reviewLoadingTarget: document.getElementById('sae-review-loading-target'),
		healthCards: document.getElementById('sae-health-cards'),
		killSwitchLabel: document.getElementById('sae-kill-switch-label'),
		killSwitchToggle: document.getElementById('sae-kill-switch-toggle'),
		historyBody: document.getElementById('sae-history-body'),
		historyFilter: document.getElementById('sae-history-action-filter'),
		historyHideReads: document.getElementById('sae-history-hide-reads'),
		historyControls: document.getElementById('sae-history-controls'),
		historyApplyFilter: document.getElementById('sae-history-apply-filter'),
		historyExportFormat: document.getElementById('sae-history-export-format'),
		historyExport: document.getElementById('sae-history-export'),
	};

	function getAllowlistedPostById(postId) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return null;
		}
		const posts = state.status && state.status.allowlist && Array.isArray(state.status.allowlist.posts)
			? state.status.allowlist.posts
			: [];
		return posts.find((post) => Number(post.post_id) === normalizedPostId) || null;
	}

	function getSelectedPostType() {
		const selectedPost = getAllowlistedPostById(els.postId && els.postId.value ? Number(els.postId.value) : 0);
		return selectedPost && selectedPost.post_type ? String(selectedPost.post_type) : '';
	}

	function getSelectedPostMeta() {
		const selectedPost = getAllowlistedPostById(els.postId && els.postId.value ? Number(els.postId.value) : 0);
		return {
			postType: selectedPost && selectedPost.post_type ? String(selectedPost.post_type) : '',
			postStatus: selectedPost && selectedPost.post_status ? String(selectedPost.post_status) : '',
			wordCount: selectedPost ? Number(selectedPost.word_count || 0) : 0,
		};
	}

	function getSelectedPostId() {
		return els.postId && els.postId.value ? Number(els.postId.value) : 0;
	}

	function formatPostStatusLabel(status) {
		const normalized = String(status || '').trim().toLowerCase();
		if (!normalized) {
			return 'Unknown';
		}
		if (normalized === 'publish') {
			return 'Published';
		}
		if (normalized === 'draft') {
			return 'Draft';
		}
		if (normalized === 'pending') {
			return 'Pending';
		}
		return normalized
			.split(/[_\s-]+/)
			.filter(Boolean)
			.map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
			.join(' ');
	}

	function getAllowlistedPostDisplayLabel(post, options = {}) {
		const includeId = !!options.includeId;
		const postId = Number(post && post.post_id ? post.post_id : 0);
		if (!postId) {
			return '';
		}
		const postTitle = String(post && post.post_title ? post.post_title : '').trim();
		const postSlug = String(post && post.post_slug ? post.post_slug : '').trim();
		const postType = String(post && post.post_type ? post.post_type : '').trim().toLowerCase();
		const title = postTitle || postSlug || `Post ${postId}`;
		const suffix = postType === 'post' ? 'Blog post' : 'Page';
		return includeId ? `${title} · ${suffix} · ID ${postId}` : `${title}`;
	}

	function buildAllowlistedPostOptionMarkup(post) {
		const postId = Number(post && post.post_id ? post.post_id : 0);
		if (!postId) {
			return '';
		}
		const postType = String(post && post.post_type ? post.post_type : '').trim().toLowerCase();
		const postTitle = String(post && post.post_title ? post.post_title : '').trim();
		const postSlug = String(post && post.post_slug ? post.post_slug : '').trim();
		const postStatus = String(post && post.post_status ? post.post_status : '').trim();
		const wordCount = Number(post && post.word_count ? post.word_count : 0);
		const suffix = postType === 'post' ? ' [Blog]' : '';
		const label = `${postId} — ${postTitle || postSlug || 'Untitled'}${suffix}`;

		return `<option value="${escapeHtml(postId)}" data-post-type="${escapeHtml(postType)}" data-post-title="${escapeHtml(postTitle)}" data-post-slug="${escapeHtml(postSlug)}" data-word-count="${escapeHtml(String(wordCount))}" data-post-status="${escapeHtml(postStatus)}">${escapeHtml(label)}</option>`;
	}

	function upsertAllowlistedPost(post) {
		const postId = Number(post && post.post_id ? post.post_id : 0);
		if (!postId) {
			return false;
		}
		const postStatus = String(post && post.post_status ? post.post_status : '').trim().toLowerCase();
		if (postStatus && ['auto-draft', 'trash', 'inherit'].includes(postStatus)) {
			return false;
		}

		if (!state.status || typeof state.status !== 'object') {
			state.status = {};
		}
		if (!state.status.allowlist || typeof state.status.allowlist !== 'object') {
			state.status.allowlist = {};
		}
		if (!Array.isArray(state.status.allowlist.posts)) {
			state.status.allowlist.posts = [];
		}

		const posts = state.status.allowlist.posts;
		const existingIndex = posts.findIndex((item) => Number(item && item.post_id ? item.post_id : 0) === postId);
		const existing = existingIndex >= 0 ? posts[existingIndex] : null;
		const merged = Object.assign({}, existing || {}, post, {
			post_id: postId,
			post_type: String(post && post.post_type ? post.post_type : (existing && existing.post_type ? existing.post_type : 'page')).trim().toLowerCase(),
			post_status: String(post && post.post_status ? post.post_status : (existing && existing.post_status ? existing.post_status : 'draft')).trim().toLowerCase(),
			post_title: String(post && post.post_title ? post.post_title : (existing && existing.post_title ? existing.post_title : `Draft ${postId}`)).trim(),
			post_slug: String(post && post.post_slug ? post.post_slug : (existing && existing.post_slug ? existing.post_slug : '')).trim(),
			word_count: Number(post && post.word_count ? post.word_count : (existing && existing.word_count ? existing.word_count : 0)),
		});

		if (existingIndex >= 0) {
			posts[existingIndex] = merged;
		} else {
			posts.push(merged);
		}

		populatePostSelector(posts);
		return true;
	}

	function selectAllowlistedPost(post) {
		const postId = Number(post && post.post_id ? post.post_id : 0);
		if (!postId || !els.postId) {
			return false;
		}

		state.preserveAutoDetectSelection = false;
		state.autoDetectedPostId = 0;
		upsertAllowlistedPost(post);

		if (!els.postId.querySelector(`option[value="${postId}"]`)) {
			const fallbackMarkup = buildAllowlistedPostOptionMarkup(post);
			if (fallbackMarkup) {
				els.postId.insertAdjacentHTML('beforeend', fallbackMarkup);
			}
		}

		if (!els.postId.querySelector(`option[value="${postId}"]`)) {
			return false;
		}

		els.postId.value = String(postId);
		els.postId.dispatchEvent(new Event('change', { bubbles: true }));
		return true;
	}

	function selectAutoDetectPostContext() {
		state.autoDetectedPostId = 0;
		if (!els.postId || !els.postId.value) {
			state.preserveAutoDetectSelection = true;
			return;
		}
		state.preserveAutoDetectSelection = true;
		els.postId.value = '';
		els.postId.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function getAdaptiveQuickstartChips(postMeta) {
		const postType = String(postMeta && postMeta.postType ? postMeta.postType : '').trim().toLowerCase();
		const postStatus = String(postMeta && postMeta.postStatus ? postMeta.postStatus : '').trim().toLowerCase();
		const wordCount = Number(postMeta && postMeta.wordCount ? postMeta.wordCount : 0);

		if (postType === 'post') {
			const chips = [
				{ label: 'Improve intro', prompt: 'Rewrite the intro paragraph to hook the reader in 2 sentences.' },
				{ label: 'Suggest title', prompt: 'Give me 5 stronger headline options for this blog post.' },
				{ label: 'Add CTA', prompt: 'Add a stronger call-to-action near the end of this blog post.' },
			];

			if (wordCount > 2000) {
				chips.push({ label: 'Add subheadings', prompt: 'Add clear subheadings to improve scannability in this long blog post.' });
				chips.push({ label: 'Trim length', prompt: 'Trim this blog post to around 1500 words while preserving key points.' });
			} else if (wordCount > 0 && wordCount < 500) {
				chips.push({ label: 'Expand', prompt: 'Expand this short blog post with more supporting details and examples.' });
				chips.push({ label: 'Add examples', prompt: 'Add one practical example to strengthen this blog post.' });
			}

			if (postStatus === 'publish') {
				chips.push({ label: 'Refresh for 2026', prompt: 'Refresh this blog post for 2026 with updated references, wording, and examples.' });
				chips.push({ label: 'SEO refresh', prompt: 'Suggest an improved SEO title and meta description for this published blog post.' });
			}

			return chips.slice(0, 6);
		}

		return [
			{ label: 'Change a headline', prompt: 'Update the hero headline to "Best Pricing Ever".' },
			{ label: 'Suggest CTA ideas', prompt: 'Give me 3 ideas for the CTA button text.' },
			{ label: 'Update button text', prompt: 'Update the primary button text to "Start For Free".' },
			{ label: 'Remove a block', prompt: 'Remove the testimonial block.' },
		];
	}

	function renderAdaptiveQuickstartChips() {
		if (!els.quickstartDynamic) {
			return;
		}
		const chips = getAdaptiveQuickstartChips(getSelectedPostMeta());
		els.quickstartDynamic.innerHTML = chips
			.map((chip) => `<button type="button" class="sae-quickstart__chip" data-chip="${escapeHtml(chip.prompt)}">${escapeHtml(chip.label)}</button>`)
			.join('');
	}

	function updateQuickstartVisibility() {
		const requestText = (els.request.value || '').trim();
		const hasSelectedPost = !!getSelectedPostId();
		const shouldShow = !state.batchMode && !state.plan && !state.receiptPayload && !requestText && (!isSimpleMode() || !hasSelectedPost);
		const selectedPostType = getSelectedPostType();
		const isBlog = selectedPostType === 'post';
		if (els.quickActions) {
			els.quickActions.hidden = state.batchMode || !isSimpleMode();
		}
		if (els.quickActionsBlog) {
			els.quickActionsBlog.hidden = !isBlog || state.batchMode || !isSimpleMode();
		}
		if (els.quickstart) {
			els.quickstart.hidden = !shouldShow;
			els.quickstart.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
		}
		if (shouldShow) {
			renderAdaptiveQuickstartChips();
		} else if (els.quickstartDynamic) {
			els.quickstartDynamic.innerHTML = '';
		}
	}

	function formatSelectedPostTypeLabel(postType) {
		return String(postType || '').trim().toLowerCase() === 'post' ? 'Blog post' : 'Page';
	}

	function getSelectedPostContext() {
		const selectedPostId = getSelectedPostId();
		const selectedPost = getAllowlistedPostById(selectedPostId);
		return {
			postId: selectedPostId,
			post: selectedPost,
			title: selectedPost ? String(selectedPost.post_title || selectedPost.post_slug || `Post ${selectedPostId}`) : '',
			typeLabel: formatSelectedPostTypeLabel(selectedPost && selectedPost.post_type ? selectedPost.post_type : ''),
			label: selectedPostId ? getPostLabelById(selectedPostId) : '--',
			postType: selectedPost && selectedPost.post_type ? String(selectedPost.post_type) : '',
		};
	}

	function isEditIntentRailOrigin(requestOrigin = '') {
		return ['intent_rail_do', 'scout_edit'].includes(String(requestOrigin || '').trim().toLowerCase());
	}

	function updateIntentRailHint() {
		if (!els.intentRailHint) {
			return;
		}
		const context = getSelectedPostContext();
		const mode = String(state.intentRailMode || '').trim().toLowerCase();
		if (mode === 'create-page') {
			els.intentRailHint.textContent = 'Creating a new page. Describe the page you want in one sentence.';
			return;
		}
		if (mode === 'create-blog') {
			els.intentRailHint.textContent = 'Creating a new blog post. Use a clear topic and the sections you want covered.';
			return;
		}
		if (mode === 'analyze' && context.postId) {
			els.intentRailHint.textContent = `Analyzing ${context.title || 'this page'}. Run a fresh audit to surface the best next fix.`;
			return;
		}
		if (mode === 'edit' && context.postId) {
			els.intentRailHint.textContent = `Editing ${context.title || 'this page'}. Use one concrete change like headline, paragraph, or CTA copy.`;
			return;
		}
		if (context.postId) {
			els.intentRailHint.textContent = `Editing ${context.title || 'this page'}. One concrete change — headline, paragraph, or CTA.`;
			return;
		}
		els.intentRailHint.textContent = 'Choose the kind of task first, then describe it in one sentence.';
	}

	function setIntentRailMode(mode = '') {
		state.intentRailMode = String(mode || '').trim().toLowerCase();
		if (els.intentRail) {
			const buttons = els.intentRail.querySelectorAll('[data-intent-rail]');
			buttons.forEach((button) => {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}
				const buttonMode = String(button.getAttribute('data-intent-rail') || '').trim().toLowerCase();
				const active = buttonMode === state.intentRailMode;
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		}
		updateIntentRailHint();
	}

	function getScoutAuditStorageMap() {
		try {
			const raw = window.sessionStorage.getItem(SCOUT_AUDIT_STORAGE_KEY);
			if (!raw) {
				return {};
			}
			const parsed = JSON.parse(raw);
			return parsed && typeof parsed === 'object' ? parsed : {};
		} catch (error) {
			return {};
		}
	}

	function normalizeScoutAuditIssue(issue, index = 0) {
		if (!issue || typeof issue !== 'object') {
			return null;
		}
		const title = String(issue.title || '').trim();
		const severity = String(issue.severity || '').trim().toLowerCase();
		const rationale = String(issue.rationale || '').trim();
		const sectionLabel = String(issue.section_label || '').trim();
		const category = String(issue.category || '').trim();
		const fixCommand = String(issue.fix_command || '').trim();
		if (!title && !rationale && !fixCommand) {
			return null;
		}
		return {
			index: Number.isInteger(index) ? index : 0,
			title,
			severity,
			rationale,
			section_label: sectionLabel,
			category,
			fix_command: fixCommand,
		};
	}

	function buildScoutIssueSignature(issue) {
		const normalized = normalizeScoutAuditIssue(issue);
		if (!normalized) {
			return '';
		}
		return [
			normalized.title,
			normalized.section_label,
			normalized.fix_command,
		]
			.map((value) => String(value || '').trim().toLowerCase())
			.filter(Boolean)
			.join('||');
	}

	function rankScoutAuditIssues(issues = []) {
		return (Array.isArray(issues) ? issues : [])
			.map((issue, index) => {
				const normalized = normalizeScoutAuditIssue(issue, index);
				if (!normalized) {
					return null;
				}
				return {
					issue: normalized,
					index: normalized.index,
					weight: getAuditSeverityWeight(normalized.severity),
				};
			})
			.filter(Boolean)
			.sort((left, right) => right.weight - left.weight || left.index - right.index);
	}

	function buildScoutNextActions(issues = [], limit = 3) {
		return rankScoutAuditIssues(issues)
			.slice(0, Math.max(0, Number(limit || 0) || 0))
			.map(({ issue, index }) => ({
				type: 'issue',
				index,
				label: issue.title || `Issue ${index + 1}`,
				subtitle: issue.section_label
					? `${formatAuditSeverityLabel(issue.severity)} priority · ${issue.section_label}`
					: `${formatAuditSeverityLabel(issue.severity)} priority issue`,
				command: buildPlannerSafeAuditCommand(issue, null),
				severity: String(issue.severity || '').trim().toLowerCase(),
			}));
	}

	function normalizeScoutAuditSnapshot(snapshot) {
		if (!snapshot || typeof snapshot !== 'object') {
			return null;
		}
		const post = snapshot.post && typeof snapshot.post === 'object' ? snapshot.post : {};
		const audit = snapshot.audit && typeof snapshot.audit === 'object' ? snapshot.audit : {};
		const issues = (Array.isArray(audit.issues) ? audit.issues : [])
			.map((issue, index) => normalizeScoutAuditIssue(issue, index))
			.filter(Boolean);
		const score = Number(audit.score || 0) || 0;
		const summary = String(audit.summary || '').trim();
		const issueCount = Number(audit.issue_count || issues.length) || issues.length;
		const nextActions = buildScoutNextActions(issues, 3);
		return {
			cached_at: Number(snapshot.cached_at || snapshot.analyzed_at || 0) || Date.now(),
			analyzed_at: Number(snapshot.analyzed_at || snapshot.cached_at || 0) || Date.now(),
			post: {
				post_id: Number(post.post_id || 0) || 0,
				post_title: String(post.post_title || '').trim(),
				post_slug: String(post.post_slug || '').trim(),
				post_type: String(post.post_type || '').trim().toLowerCase(),
			},
			audit: {
				score,
				summary,
				issue_count: issueCount,
				issues,
			},
			next_actions: nextActions,
			best_next_action: nextActions[0] || {
				type: 'analyze',
				index: -1,
				label: 'Analyze this page',
				subtitle: 'Run a fresh analysis to surface the best next change.',
				command: '',
			},
		};
	}

	function buildScoutAuditSnapshotFromPlan(plan) {
		if (!isAuditPlan(plan)) {
			return null;
		}
		const post = plan && plan.post && typeof plan.post === 'object' ? plan.post : {};
		const postId = Number(post.post_id || 0);
		if (!postId) {
			return null;
		}
		const issues = getAuditIssues(plan).slice(0, 8);
		return normalizeScoutAuditSnapshot({
			cached_at: Date.now(),
			analyzed_at: Date.now(),
			post: {
				post_id: postId,
				post_title: String(post.post_title || '').trim(),
				post_slug: String(post.post_slug || '').trim(),
				post_type: String(post.post_type || '').trim().toLowerCase(),
			},
			audit: {
				score: Number(plan.audit && plan.audit.score ? plan.audit.score : 0) || 0,
				summary: String(plan.audit && plan.audit.summary ? plan.audit.summary : '').trim(),
				issue_count: issues.length,
				issues,
			},
		});
	}

	function writeScoutAuditStorageMap(cache) {
		try {
			window.sessionStorage.setItem(SCOUT_AUDIT_STORAGE_KEY, JSON.stringify(cache || {}));
		} catch (error) {
			/* storage may be unavailable */
		}
	}

	function deleteScoutAuditSnapshot(postId) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return;
		}
		const cache = getScoutAuditStorageMap();
		const key = String(normalizedPostId);
		if (!Object.prototype.hasOwnProperty.call(cache, key)) {
			return;
		}
		delete cache[key];
		writeScoutAuditStorageMap(cache);
	}

	function readScoutAuditSnapshot(postId) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return null;
		}
		const cache = getScoutAuditStorageMap();
		const key = String(normalizedPostId);
		const entry = cache[key];
		if (!entry || typeof entry !== 'object') {
			return null;
		}
		const cachedAt = Number(entry.cached_at || 0);
		if (!cachedAt || (Date.now() - cachedAt) > SCOUT_AUDIT_TTL_MS) {
			delete cache[key];
			writeScoutAuditStorageMap(cache);
			return null;
		}
		return normalizeScoutAuditSnapshot(entry);
	}

	function writeScoutAuditSnapshot(plan) {
		const snapshot = buildScoutAuditSnapshotFromPlan(plan);
		if (!snapshot || !snapshot.post || !snapshot.post.post_id) {
			return;
		}
		const cache = getScoutAuditStorageMap();
		cache[String(snapshot.post.post_id)] = snapshot;
		const keys = Object.keys(cache);
		if (keys.length > 20) {
			keys
				.sort((a, b) => Number(cache[b] && cache[b].cached_at ? cache[b].cached_at : 0) - Number(cache[a] && cache[a].cached_at ? cache[a].cached_at : 0))
				.slice(20)
				.forEach((key) => {
					delete cache[key];
				});
		}
		writeScoutAuditStorageMap(cache);
	}

	function getTemplateRegistryCacheEntry() {
		try {
			const raw = window.sessionStorage.getItem(TEMPLATE_REGISTRY_STORAGE_KEY);
			if (!raw) {
				return null;
			}
			const parsed = JSON.parse(raw);
			return parsed && typeof parsed === 'object' ? parsed : null;
		} catch (_error) {
			return null;
		}
	}

	function readTemplateRegistryCache() {
		const entry = getTemplateRegistryCacheEntry();
		if (!entry) {
			return [];
		}
		const cachedAt = Number(entry.cached_at || 0);
		if (!cachedAt || (Date.now() - cachedAt) > TEMPLATE_REGISTRY_TTL_MS) {
			try {
				window.sessionStorage.removeItem(TEMPLATE_REGISTRY_STORAGE_KEY);
			} catch (_error) {
				/* ignore storage cleanup errors */
			}
			return [];
		}
		return Array.isArray(entry.templates) ? entry.templates : [];
	}

	function writeTemplateRegistryCache(templates) {
		const normalized = Array.isArray(templates) ? templates : [];
		state.templateRegistryItems = normalized;
		state.templateRegistryLoaded = true;
		try {
			window.sessionStorage.setItem(TEMPLATE_REGISTRY_STORAGE_KEY, JSON.stringify({
				cached_at: Date.now(),
				templates: normalized,
			}));
		} catch (_error) {
			/* ignore storage write errors */
		}
	}

	function getTemplateRegistryItemsForHints() {
		if (Array.isArray(state.templateRegistryItems) && state.templateRegistryItems.length) {
			return state.templateRegistryItems;
		}
		const cached = readTemplateRegistryCache();
		if (cached.length) {
			state.templateRegistryItems = cached;
			state.templateRegistryLoaded = true;
			return cached;
		}
		return [];
	}

	async function refreshTemplateRegistryCache(options = {}) {
		if (!rest.templates) {
			return [];
		}
		const force = !!options.force;
		if (state.templateRegistryLoading) {
			return Array.isArray(state.templateRegistryItems) ? state.templateRegistryItems : [];
		}
		const cached = !force ? readTemplateRegistryCache() : [];
		if (cached.length) {
			state.templateRegistryItems = cached;
			state.templateRegistryLoaded = true;
			return cached;
		}

		state.templateRegistryLoading = true;
		try {
			const response = await requestJson(buildUrl(rest.templates, {
				include_hidden: 0,
				_ts: force ? Date.now() : '',
			}), {
				cache: force ? 'no-store' : undefined,
			});
			const templates = Array.isArray(response && response.templates) ? response.templates : [];
			writeTemplateRegistryCache(templates);
			return templates;
		} catch (_error) {
			return cached.length ? cached : [];
		} finally {
			state.templateRegistryLoading = false;
		}
	}

	function warmTemplateRegistryCache() {
		if (!rest.templates || state.templateRegistryLoading) {
			return;
		}
		if (state.templateRegistryLoaded && Array.isArray(state.templateRegistryItems) && state.templateRegistryItems.length) {
			return;
		}
		window.setTimeout(() => {
			void refreshTemplateRegistryCache({ force: false });
		}, 0);
	}

	function noteTemplateRegistryChanged() {
		state.templateRegistryLoaded = false;
		window.setTimeout(() => {
			void refreshTemplateRegistryCache({ force: true });
		}, 0);
	}

	function getPatternRegistryCacheEntry() {
		try {
			const raw = window.sessionStorage.getItem(PATTERN_REGISTRY_STORAGE_KEY);
			if (!raw) {
				return null;
			}
			const parsed = JSON.parse(raw);
			return parsed && typeof parsed === 'object' ? parsed : null;
		} catch (_error) {
			return null;
		}
	}

	function readPatternRegistryCache() {
		const entry = getPatternRegistryCacheEntry();
		if (!entry) {
			return [];
		}
		const cachedAt = Number(entry.cached_at || 0);
		if (!cachedAt || (Date.now() - cachedAt) > PATTERN_REGISTRY_TTL_MS) {
			try {
				window.sessionStorage.removeItem(PATTERN_REGISTRY_STORAGE_KEY);
			} catch (_error) {
				/* ignore storage cleanup errors */
			}
			return [];
		}
		return Array.isArray(entry.patterns) ? entry.patterns : [];
	}

	function writePatternRegistryCache(patterns) {
		const normalized = Array.isArray(patterns) ? patterns : [];
		state.patternRegistryItems = normalized;
		state.patternRegistryLoaded = true;
		try {
			window.sessionStorage.setItem(PATTERN_REGISTRY_STORAGE_KEY, JSON.stringify({
				cached_at: Date.now(),
				patterns: normalized,
			}));
		} catch (_error) {
			/* ignore storage write errors */
		}
	}

	async function refreshPatternRegistryCache(options = {}) {
		if (!rest.patterns) {
			return [];
		}
		const force = !!options.force;
		const includeHidden = !!options.includeHidden;
		// Hidden-inclusive reads back the pattern library and should never be
		// downgraded to the warm visible-only cache path.
		if (state.patternRegistryLoading && !includeHidden) {
			return Array.isArray(state.patternRegistryItems) ? state.patternRegistryItems : [];
		}
		const cached = !force && !includeHidden ? readPatternRegistryCache() : [];
		if (cached.length) {
			state.patternRegistryItems = cached;
			state.patternRegistryLoaded = true;
			return cached;
		}

		state.patternRegistryLoading = true;
		try {
			const response = await requestJson(buildUrl(rest.patterns, {
				include_hidden: includeHidden ? 1 : 0,
				_ts: force ? Date.now() : '',
			}), {
				cache: force ? 'no-store' : undefined,
			});
			const patterns = Array.isArray(response && response.patterns) ? response.patterns : [];
			if (!includeHidden) {
				writePatternRegistryCache(patterns);
			} else {
				state.patternRegistryItems = patterns;
			}
			return patterns;
		} catch (_error) {
			return cached.length ? cached : [];
		} finally {
			state.patternRegistryLoading = false;
		}
	}

	function warmPatternRegistryCache() {
		if (!rest.patterns || state.patternRegistryLoading) {
			return;
		}
		if (state.patternRegistryLoaded && Array.isArray(state.patternRegistryItems) && state.patternRegistryItems.length) {
			return;
		}
		window.setTimeout(() => {
			void refreshPatternRegistryCache({ force: false });
		}, 0);
	}

	function notePatternRegistryChanged() {
		state.patternRegistryLoaded = false;
		window.setTimeout(() => {
			void refreshPatternRegistryCache({ force: true });
		}, 0);
	}

	function setPlanEmptyState(title, subtitle, bodyText = '', options = {}) {
		const showBody = !!bodyText && !!els.planEmptyBody;
		if (els.planEmptyTitle) {
			els.planEmptyTitle.textContent = String(title || '');
			els.planEmptyTitle.hidden = !!options.hideText;
		}
		if (els.planEmptySubtitle) {
			els.planEmptySubtitle.textContent = String(subtitle || '');
			els.planEmptySubtitle.hidden = !!options.hideText || !String(subtitle || '');
		}
		if (els.planEmptyBody) {
			els.planEmptyBody.textContent = showBody ? String(bodyText) : '';
			els.planEmptyBody.hidden = !showBody;
		}
	}

	function ensureScoutSupportData(postId) {
		if (!isSimpleMode() || state.plan || (els.receipt && !els.receipt.hidden)) {
			return;
		}
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return;
		}
		if (!state.historyLoaded && !state.historyLoading) {
			window.setTimeout(() => {
				loadAudit({ force: false });
			}, 0);
		}
		warmTemplateRegistryCache();
		warmPatternRegistryCache();
	}

	function runScoutAuditAnalysis() {
		const selectedPostId = getSelectedPostId();
		if (!selectedPostId) {
			setComposeStatus('Select a page first to analyze it.', 'warn');
			return;
		}
		const nextRequest = buildQuickActionRequest('audit');
		if (!nextRequest) {
			return;
		}
		clearPendingPlanContext();
		clearReceipt();
		state.lastRequestOrigin = 'audit';
		setIntentRailMode('analyze');
		smoothFillComposer(nextRequest);
		setComposeStatus(isSimpleMode() ? 'Analyzing this page…' : 'Running page analysis…', 'loading');
		window.setTimeout(() => handleGeneratePlan(), 0);
	}

	function runScoutIssueAction(index, planFromFinding = false) {
		const selectedPostId = getSelectedPostId();
		const snapshot = readScoutAuditSnapshot(selectedPostId);
		const post = snapshot && snapshot.post ? snapshot.post : null;
		const issues = snapshot && snapshot.audit && Array.isArray(snapshot.audit.issues) ? snapshot.audit.issues : [];
		const issue = issues[Number(index)] || null;
		const fixCommand = buildPlannerSafeAuditCommand(issue, post);
		if (!fixCommand) {
			setComposeStatus('No saved fix is available for that issue yet. Refresh analysis and try again.', 'warn');
			return;
		}
		if (post) {
			selectAllowlistedPost(post);
			state.pendingResolvedPostId = Number(post.post_id || 0) || 0;
		}
		clearReceipt();
		state.lastRequestOrigin = planFromFinding ? 'audit_apply' : 'audit';
		setIntentRailMode('edit');
		smoothFillComposer(fixCommand);
		if (planFromFinding) {
			setComposeStatus(isSimpleMode() ? 'Generating a plan from the top recommendation…' : 'Generating plan from top recommendation…', 'loading');
			window.setTimeout(() => handleGeneratePlan(), 0);
			return;
		}
		resetPlanState();
		setComposeStatus(isSimpleMode() ? 'Top recommendation added. Press Plan changes when ready.' : 'Top recommendation added to composer.', 'info');
	}

	function seedScoutCreateRequest(kind) {
		const normalized = String(kind || '').trim().toLowerCase();
		const seed = normalized === 'blog'
			? 'Create a new blog post about '
			: 'Create a new page about ';
		selectAutoDetectPostContext();
		clearPendingPlanContext();
		clearReceipt();
		resetPlanState();
		state.lastRequestOrigin = 'manual';
		setIntentRailMode(normalized === 'blog' ? 'create-blog' : 'create-page');
		smoothFillComposer(seed);
		setComposeStatus(normalized === 'blog' ? 'Blog creation starter added.' : 'Page creation starter added.', 'info');
	}

	function seedSelectedPageEditRequest() {
		const context = getSelectedPostContext();
		if (!context.postId || !context.post) {
			setComposeStatus('Select a page first to edit it.', 'warn');
			return;
		}
		clearPendingPlanContext();
		clearReceipt();
		resetPlanState();
		state.lastRequestOrigin = 'intent_rail_do';
		setIntentRailMode('edit');
		if (!String(els.request && els.request.value ? els.request.value : '').trim()) {
			smoothFillComposer('Change the headline to "".');
			if (els.request && typeof els.request.focus === 'function') {
				window.requestAnimationFrame(() => {
					els.request.focus();
					const cursorAt = 'Change the headline to "'.length;
					if (typeof els.request.setSelectionRange === 'function') {
						els.request.setSelectionRange(cursorAt, cursorAt);
					}
				});
			}
		} else if (els.request && typeof els.request.focus === 'function') {
			els.request.focus();
		}
		setComposeStatus(`Editing ${context.title || 'the selected page'}. Describe one specific change.`, 'info');
	}

	function handleIntentRailAction(action) {
		const normalizedAction = String(action || '').trim().toLowerCase();
		if (!normalizedAction) {
			return;
		}
		if (normalizedAction === 'create-page') {
			seedScoutCreateRequest('page');
			return;
		}
		if (normalizedAction === 'create-blog') {
			seedScoutCreateRequest('blog');
			return;
		}
		setIntentRailMode(normalizedAction);
		if (normalizedAction === 'analyze') {
			const nextRequest = buildQuickActionRequest('audit');
			if (nextRequest && els.request && !String(els.request.value || '').trim()) {
				smoothFillComposer(nextRequest);
			}
		}
		if (els.request && typeof els.request.focus === 'function') {
			els.request.focus();
		}
	}

	function updateMissionBriefFlow() {
		const simple = isSimpleMode();
		const context = getSelectedPostContext();
		const hasPost = !!context.postId && !!context.post;
		const requestText = els.request ? String(els.request.value || '').trim() : '';
		const collapseTarget = simple && hasPost && !state.stepTargetExpanded;
		if (els.stepTarget) {
			els.stepTarget.classList.toggle('is-collapsed', collapseTarget);
			els.stepTarget.removeAttribute('aria-expanded');
		}
		if (els.stepTargetSummary) {
			els.stepTargetSummary.hidden = !(simple && hasPost);
		}
		if (els.stepTargetBody) {
			setNodeInert(els.stepTargetBody, collapseTarget);
		}
		if (els.stepTargetChange) {
			els.stepTargetChange.hidden = !(simple && hasPost);
			els.stepTargetChange.setAttribute('aria-expanded', String(!!state.stepTargetExpanded));
		}
		if (els.stepTargetPicked && hasPost) {
			const status = formatPostStatusLabel(context.post.post_status);
			els.stepTargetPicked.textContent = `${context.title || 'Untitled'} · ${context.typeLabel} · ${status} · ID ${context.postId}`;
			els.stepTargetPicked.hidden = !collapseTarget;
		}
		if (els.stepTargetTag) {
			const isAuto = hasPost
				&& state.autoDetectedPostId > 0
				&& Number(context.postId) === Number(state.autoDetectedPostId);
			els.stepTargetTag.hidden = !collapseTarget || !isAuto;
		}
		const awaitingRequest = simple && !requestText && !state.planRequestActive;
		if (els.stepPlan) {
			els.stepPlan.classList.toggle('is-ghosted', awaitingRequest);
		}
		if (els.generatePlan) {
			els.generatePlan.classList.toggle('is-awaiting', awaitingRequest);
		}
		renderScoutLanding();
	}

	async function runReceiptAuditFollowUp(baseReceiptPayload, previousSnapshot, options = {}) {
		const postId = Number(options.postId || (baseReceiptPayload && baseReceiptPayload.post_id) || 0);
		const token = String(options.token || '').trim();
		if (!postId || !token) {
			return;
		}

		try {
			const auditRequest = buildQuickActionRequest('audit');
			if (!auditRequest) {
				throw new Error('Audit request is unavailable.');
			}
			const auditPlan = await requestPlan(
				{
					request: auditRequest,
					dry_run_preview: false,
					prefer_ai: !!els.preferAi.checked,
					verbose: false,
					compact: true,
					intent: 'audit',
					plan: { post_id: postId },
				},
				{},
				null,
				{
					disableStreaming: true,
					timeoutMs: REQUEST_TIMEOUT_MS * 2,
				}
			);
			if (!isActiveReceiptAuditFollowUp(token)) {
				return;
			}
			if (!isAuditPlan(auditPlan)) {
				throw new Error('Follow-up audit did not return a valid audit result.');
			}
			writeScoutAuditSnapshot(auditPlan);
			const nextSnapshot = readScoutAuditSnapshot(postId) || buildScoutAuditSnapshotFromPlan(auditPlan);
			if (!isActiveReceiptAuditFollowUp(token)) {
				return;
			}
			renderReceipt(Object.assign({}, cloneJsonLike(baseReceiptPayload), {
				audit_follow_up_token: token,
				audit_follow_up: buildAuditFollowUpState(previousSnapshot, nextSnapshot),
			}));
			setComposeStatus('Fresh audit ready. Review the next best action below.', 'info');
		} catch (_error) {
			if (!isActiveReceiptAuditFollowUp(token)) {
				return;
			}
			renderReceipt(Object.assign({}, cloneJsonLike(baseReceiptPayload), {
				audit_follow_up_token: token,
				audit_follow_up: buildAuditFollowUpErrorState(),
			}));
			setComposeStatus('Saved to WordPress, but follow-up analysis needs a manual refresh.', 'warn');
		}
	}

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function getScoutLiveLine(hasAnalysis, scoreTone, issueCount) {
		if (!hasAnalysis) {
			return 'No analysis yet — Plan will analyze first.';
		}
		const count = Number(issueCount || 0);
		if (!count) {
			return scoreTone === 'critical' || scoreTone === 'needs-work'
				? 'Needs work — nothing specific is flagged yet.'
				: 'Good shape — nothing flagged.';
		}
		const things = count === 1 ? 'one thing worth a look.' : `${count === 2 ? 'two' : count} things worth a look.`;
		if (scoreTone === 'critical' || scoreTone === 'needs-work') {
			return `Needs work — ${things}`;
		}
		return `Good shape — ${things}`;
	}

	function typeScoutLiveLine(text) {
		const next = String(text || '');
		if (!els.scoutLiveText) {
			return;
		}
		if (state.scoutTypeTimer) {
			window.clearInterval(state.scoutTypeTimer);
			state.scoutTypeTimer = 0;
		}
		if (prefersReducedMotion() || state.scoutLineText === next) {
			els.scoutLiveText.textContent = next;
			state.scoutLineText = next;
			return;
		}
		state.scoutLineText = next;
		let index = 0;
		els.scoutLiveText.textContent = '';
		state.scoutTypeTimer = window.setInterval(() => {
			index += 1;
			els.scoutLiveText.textContent = next.slice(0, index);
			if (index >= next.length) {
				window.clearInterval(state.scoutTypeTimer);
				state.scoutTypeTimer = 0;
			}
		}, 20);
	}

	function renderScoutLanding() {
		if (!els.scoutLive) {
			return;
		}
		if (!isSimpleMode()) {
			els.scoutLive.hidden = true;
			return;
		}
		if (els.receipt && !els.receipt.hidden) {
			els.scoutLive.hidden = true;
			return;
		}
		const context = getSelectedPostContext();
		if (!context.postId || !context.post) {
			els.scoutLive.hidden = true;
			return;
		}

		ensureScoutSupportData(context.postId);
		const snapshot = normalizeScoutAuditSnapshot(readScoutAuditSnapshot(context.postId));
		const hasAnalysis = !!snapshot && (Number(snapshot.analyzed_at || snapshot.cached_at) || 0) > 0;
		if (!hasAnalysis) {
			els.scoutLive.hidden = true;
			return;
		}
		const score = Number(snapshot && snapshot.audit ? snapshot.audit.score : 0) || 0;
		const scoreLabel = score > 0 ? `${score}/10` : '--';
		const scoreTone = getAuditScoreTone(score);
		const issues = snapshot && snapshot.audit && Array.isArray(snapshot.audit.issues) ? snapshot.audit.issues.slice(0, 3) : [];
		const issueCount = snapshot && snapshot.audit ? Number(snapshot.audit.issue_count || issues.length) || issues.length : 0;
		const scoreProgress = Math.max(0, Math.min(score, 10)) * 10;
		const issuesMarkup = issues.length
			? `<div class="sae-scout-issues">${issues.map((issue, index) => `
					<article class="sae-scout-issue">
						<p class="sae-scout-issue__title">${escapeHtml(issue.title || `Issue ${index + 1}`)}</p>
						<p class="sae-scout-issue__meta">${escapeHtml(`${formatAuditSeverityLabel(issue.severity)}${issue.section_label ? ` · ${issue.section_label}` : ''}`)}</p>
						<button type="button" class="button button-secondary" data-scout-action="review-issue" data-scout-issue-index="${index}" aria-label="${escapeHtml(`Review fix: ${issue.title || `Issue ${index + 1}`}`)}">Review fix</button>
					</article>
				`).join('')}</div>`
			: '<p class="sae-scout-card__empty">No issues cached yet.</p>';

		els.scoutLive.hidden = false;
		typeScoutLiveLine(getScoutLiveLine(hasAnalysis, scoreTone, issueCount));
		if (els.scoutLivePanel) {
			els.scoutLivePanel.innerHTML = `
				<div class="sae-scout-score-ring sae-scout-score-ring--${escapeHtml(scoreTone)}" style="--sae-score-progress: ${escapeHtml(scoreProgress)}%;" aria-hidden="true"${hasAnalysis ? '' : ' hidden'}>
					<p class="sae-scout-score sae-scout-score--${escapeHtml(scoreTone)}">${escapeHtml(scoreLabel)}</p>
				</div>
				${issuesMarkup}
			`;
		}
	}

	function updateEmptyStateCopy() {
		if (isSimpleMode()) {
			renderScoutLanding();
			if (!state.plan) {
				setPlanEmptyState(MODE_COPY.simple.emptyTitle, MODE_COPY.simple.reviewSubtitle);
			}
			return;
		}
		if (!els.planEmptySubtitle) {
			return;
		}
		setPlanEmptyState(
			MODE_COPY.dev.emptyTitle,
			'Create a plan from Request Composer to preview payload, confirmation token, and risk tags.'
		);
	}

	function relocateModeTogglesIntoAdvanced() {
		const advanced = els.advanced || document.querySelector('#sae-console-root details.sae-advanced');
		if (!advanced || !els.modeToggle || !els.appModeToggle) {
			return;
		}
		let row = advanced.querySelector('.sae-advanced-modes');
		if (!row) {
			row = document.createElement('div');
			row.className = 'sae-advanced-modes';
			const summary = advanced.querySelector('summary');
			advanced.insertBefore(row, summary ? summary.nextSibling : advanced.firstChild);
		}
		row.append(els.modeToggle, els.appModeToggle);
	}

	function updateModeCopy() {
		const copy = isSimpleMode() ? MODE_COPY.simple : MODE_COPY.dev;
		if (!copy) {
			return;
		}

		if (els.headerSubtitle) {
			els.headerSubtitle.textContent = copy.headerSubtitle;
		}
		const heading = document.querySelector('.sae-console__header h1');
		if (heading) {
			heading.textContent = isSimpleMode() ? 'Console' : 'Struo Console';
		}
		if (els.composeTitle) {
			els.composeTitle.textContent = copy.composeTitle;
		}
		if (els.composeSubtitle) {
			els.composeSubtitle.textContent = copy.composeSubtitle;
			els.composeSubtitle.hidden = !copy.composeSubtitle;
		}
		if (els.requestLabel) {
			els.requestLabel.textContent = copy.requestLabel;
		}
		if (els.request) {
			els.request.placeholder = copy.requestPlaceholder || els.request.placeholder;
		}
		if (els.postHelp) {
			els.postHelp.textContent = copy.postHelp;
		}
		if (els.reviewTitle) {
			els.reviewTitle.textContent = copy.reviewTitle;
		}
		if (els.reviewSubtitle) {
			els.reviewSubtitle.textContent = copy.reviewSubtitle;
		}
		if (els.planEmptyTitle) {
			els.planEmptyTitle.textContent = copy.emptyTitle;
		}
		if (els.cancelPlan) {
			els.cancelPlan.textContent = copy.cancelLabel;
		}
	}

	function escapeHtml(value) {
		return String(value || '')
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#39;');
	}

	function formatPlannerWarning(warning, simpleModeActive) {
		const text = String(warning || '').trim();
		if (!simpleModeActive) {
			return text;
		}
		if (/^target auto-resolved to first\s+/i.test(text)) {
			return 'We found the best matching section and prepared this change.';
		}
		return text
			.replaceAll('core/heading', 'headline')
			.replaceAll('core/paragraph', 'paragraph')
			.replaceAll('core/button', 'button');
	}

	function isTechnicalPlannerWarning(text) {
		const normalized = String(text || '').trim();
		if (!normalized) {
			return false;
		}
		return /ai_ask|candidate_value|schema|unexpected response|planner returned|not of type string|response shape/i.test(normalized);
	}

	function normalizePlannerWarnings(rawWarnings, simpleModeActive, intent) {
		const warnings = Array.isArray(rawWarnings) ? rawWarnings.filter(Boolean) : [];
		if (!simpleModeActive || intent !== 'ask') {
			return warnings;
		}
		return warnings.filter((warning) => !isTechnicalPlannerWarning(warning));
	}

	function formatPersuasionLabel(value) {
		const normalized = String(value || '')
			.trim()
			.replace(/[_\s]+/g, '-')
			.toLowerCase();
		if (!normalized) {
			return '';
		}
		return normalized
			.split('-')
			.filter(Boolean)
			.map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
			.join(' ');
	}

	function deriveCandidateValueFromCommand(command) {
		const text = String(command || '').trim();
		if (!text) {
			return '';
		}

		const patterns = [
			/\bto\s+["“]([^"”]+)["”]\s*\.?$/i,
			/\bto\s+'([^']+)'\s*\.?$/i,
			/\bto\s+`([^`]+)`\s*\.?$/i,
		];
		for (let index = 0; index < patterns.length; index += 1) {
			const match = text.match(patterns[index]);
			if (match && match[1]) {
				return String(match[1]).trim();
			}
		}

		return '';
	}

	function getSuggestionCandidateValue(suggestion) {
		if (!suggestion || typeof suggestion !== 'object') {
			return '';
		}
		const explicitValue = String(suggestion.candidate_value || '').trim();
		if (explicitValue) {
			return explicitValue;
		}
		return deriveCandidateValueFromCommand(suggestion.command);
	}

	function canAutoApplySuggestion(suggestion) {
		return !!getSuggestionCandidateValue(suggestion);
	}

	function isLikelyCreateIntentRequest(requestText) {
		const text = String(requestText || '');
		// Allow brief adjectives between the verb and "page"/"blog post"
		// so "Create a short blog post…" still counts as create.
		return /\b(create|build|make|generate|scaffold)\b[\s\S]{0,40}?\b(?:new\s+)?(?:landing\s*page|blog\s*post|page|post|article)\b/i.test(text);
	}

	function isCreateIntentRailMode() {
		const mode = String(state.intentRailMode || '').trim().toLowerCase();
		return mode === 'create-page' || mode === 'create-blog';
	}

	function shouldForceCreateIntent(requestText) {
		return isCreateIntentRailMode() || isLikelyCreateIntentRequest(requestText);
	}

	function getLikelyCreatePostType(requestText) {
		const mode = String(state.intentRailMode || '').trim().toLowerCase();
		if (mode === 'create-blog') {
			return 'post';
		}
		if (mode === 'create-page') {
			return 'page';
		}
		return /\b(blog\s*post|post|article)\b/i.test(String(requestText || '')) ? 'post' : 'page';
	}

	function hasExplicitDirectReplacementSignal(requestText) {
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText) {
			return false;
		}
		return /\bto\s+["“][^"”]+["”]/i.test(normalizedText)
			|| /\bto\s+'[^']+'/i.test(normalizedText)
			|| /\b(?:change|update|set|replace|rename)\b[\s\S]{0,96}\bto\b[\s\S]{1,120}$/i.test(normalizedText);
	}

	function hasDiscoveryAskSignal(requestText) {
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText) {
			return false;
		}
		return /^\s*(?:give|suggest|brainstorm|list|recommend|show)\b/i.test(normalizedText)
			|| /\b(ideas?|options?|suggest(?:ion|ions)?|recommend(?:ation|ations)?|alternatives?|examples?|brainstorm|seo title|meta description|headline ideas?|meta titles?)\b/i.test(normalizedText);
	}

	function hasQuestionAskSignal(requestText) {
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText) {
			return false;
		}
		return /^\s*(?:what|how|which|could|can|would|should|help)\b/i.test(normalizedText);
	}

	function hasDirectEditSignal(requestText) {
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText) {
			return false;
		}
		if (hasExplicitDirectReplacementSignal(normalizedText)) {
			return true;
		}
		const patterns = [
			/\b(update|change|set|replace|modify|rename|remove|delete|insert|add|append|apply|fix)\b/i,
			/^\s*(?:please\s+)?(?:rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust|improve)\b/i,
			/\b(?:tighten|clarify|polish|refresh|refine|strengthen|soften|simplify|shorten|improve)\b[\s\S]{0,48}\b(?:headline|heading|title|subheading|subtitle|body|paragraph|copy|content|cta|button|sentence|intro|conclusion|description)\b/i,
			/\bmake\b[\s\S]{0,32}\b(?:clearer|stronger|shorter|simpler|more compelling|more concise|more direct)\b/i,
		];
		return patterns.some((pattern) => pattern.test(normalizedText));
	}

	function isLikelyDirectEditIntentRequest(requestText, requestOrigin = 'manual') {
		const normalizedOrigin = String(requestOrigin || '').trim().toLowerCase();
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText) {
			return false;
		}
		if (isEditIntentRailOrigin(normalizedOrigin)) {
			return true;
		}
		if (shouldForceCreateIntent(normalizedText) || isLikelyAuditIntentRequest(normalizedText, requestOrigin)) {
			return false;
		}
		if (hasDiscoveryAskSignal(normalizedText)) {
			return false;
		}
		if (hasQuestionAskSignal(normalizedText) && !hasExplicitDirectReplacementSignal(normalizedText)) {
			return false;
		}
		return hasDirectEditSignal(normalizedText);
	}

	function isLikelyAuditIntentRequest(requestText, requestOrigin = 'manual') {
		const normalizedOrigin = String(requestOrigin || '').trim().toLowerCase();
		if (['audit', 'audit_apply'].includes(normalizedOrigin)) {
			return true;
		}
		const normalizedText = String(requestText || '');
		if (!normalizedText.trim() || shouldForceCreateIntent(normalizedText)) {
			return false;
		}
		return /\b(audit|review|score|issues?)\b/i.test(normalizedText);
	}

	function isLikelyAskIntentRequest(requestText, requestOrigin = 'manual') {
		const normalizedOrigin = String(requestOrigin || '').trim().toLowerCase();
		if (['suggestion', 'suggestion_apply'].includes(normalizedOrigin)) {
			return true;
		}
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText || shouldForceCreateIntent(normalizedText) || isLikelyAuditIntentRequest(normalizedText, requestOrigin)) {
			return false;
		}
		if (hasDiscoveryAskSignal(normalizedText)) {
			return true;
		}
		if (hasQuestionAskSignal(normalizedText) && !hasExplicitDirectReplacementSignal(normalizedText)) {
			return true;
		}
		if (isLikelyDirectEditIntentRequest(normalizedText, requestOrigin)) {
			return false;
		}
		if (/\b(update|change|set|replace|remove|delete|insert|add|apply|fix)\b/i.test(normalizedText)) {
			return false;
		}
		return hasQuestionAskSignal(normalizedText);
	}

	function buildPlannerRequestText(requestText, requestOrigin = 'manual') {
		const normalizedText = String(requestText || '').trim();
		if (!normalizedText || shouldForceCreateIntent(normalizedText) || isLikelyAuditIntentRequest(normalizedText, requestOrigin)) {
			return normalizedText;
		}
		const context = getSelectedPostContext();
		if (!context.postId || !context.post || !isLikelyDirectEditIntentRequest(normalizedText, requestOrigin)) {
			return normalizedText;
		}
		const postTypeLabel = String(context.postType || '').trim().toLowerCase() === 'post' ? 'blog post' : 'page';
		const postLabel = context.title || context.label || 'selected content';
		if (/^\s*update the selected (page|blog post|post)\b/i.test(normalizedText)) {
			return normalizedText;
		}
		return `Update the selected ${postTypeLabel} (${postLabel}): ${normalizedText}`;
	}

	function buildTemplateCandidateSearchText(template) {
		if (!template || typeof template !== 'object') {
			return '';
		}
		const metadata = template.metadata && typeof template.metadata === 'object' ? template.metadata : {};
		const sectionRecipe = Array.isArray(template.section_recipe) ? template.section_recipe : [];
		const postTypes = Array.isArray(template.post_types) ? template.post_types : [];
		const families = Array.isArray(template.families) ? template.families : [];
		const tags = Array.isArray(template.tags) ? template.tags : [];
		const editableFields = Array.isArray(metadata.editable_fields) ? metadata.editable_fields : [];
		const selectionNotes = Array.isArray(metadata.selection_notes) ? metadata.selection_notes : [];
		const acfModules = Array.isArray(metadata.acf_modules) ? metadata.acf_modules : [];
		return [
			template.label,
			template.description,
			metadata.family,
			metadata.variant,
			metadata.context,
			metadata.what_it_is,
			metadata.purpose,
			postTypes.join(' '),
			families.join(' '),
			tags.join(' '),
			sectionRecipe.join(' '),
			editableFields.join(' '),
			selectionNotes.join(' '),
			acfModules.join(' '),
		]
			.filter(Boolean)
			.join(' ')
			.toLowerCase();
	}

	function getLikelyTemplateCandidatesForRequest(requestText, postType = 'page', limit = 3) {
		const templates = getTemplateRegistryItemsForHints();
		if (!templates.length) {
			return [];
		}
		const normalizedPostType = String(postType || 'page').trim().toLowerCase() || 'page';
		const normalizedText = String(requestText || '').trim().toLowerCase();
		if (!normalizedText) {
			return [];
		}
		const tokens = Array.from(new Set(
			normalizedText
				.split(/[^a-z0-9]+/)
				.map((token) => token.trim())
				.filter((token) => token.length >= 3)
				.filter((token) => !['with', 'that', 'this', 'page', 'post', 'create', 'build', 'make', 'generate', 'draft', 'new', 'for', 'and', 'the'].includes(token))
		));
		if (!tokens.length) {
			return [];
		}

		return templates
			.filter((template) => {
				if (!template || typeof template !== 'object') {
					return false;
				}
				const status = String(template.status || 'approved').trim().toLowerCase();
				if (status && status !== 'approved') {
					return false;
				}
				const postTypes = Array.isArray(template.post_types) ? template.post_types.map((item) => String(item || '').trim().toLowerCase()) : [];
				return !postTypes.length || postTypes.includes(normalizedPostType);
			})
			.map((template) => {
				const haystack = buildTemplateCandidateSearchText(template);
				let score = 0;
				tokens.forEach((token) => {
					if (haystack.includes(token)) {
						score += 1;
					}
				});
				const label = String(template.label || '').trim().toLowerCase();
				if (label && normalizedText.includes(label)) {
					score += 4;
				}
				const description = String(template.description || '').trim().toLowerCase();
				if (description && normalizedText.includes(description)) {
					score += 2;
				}
				const metadata = template.metadata && typeof template.metadata === 'object' ? template.metadata : {};
				const family = String(metadata.family || '').trim().toLowerCase();
				if (family && normalizedText.includes(family)) {
					score += 2;
				}
				return Object.assign({}, template, { __candidateScore: score });
			})
			.filter((template) => Number(template.__candidateScore || 0) > 0)
			.sort((left, right) => Number(right.__candidateScore || 0) - Number(left.__candidateScore || 0))
			.slice(0, limit);
	}

	function buildCreateLoadingTemplateChoice(requestText) {
		const postType = getLikelyCreatePostType(requestText);
		const candidates = getLikelyTemplateCandidatesForRequest(requestText, postType, 3);
		if (!candidates.length) {
			return {
				mode: '',
				label: 'Choosing template',
				rationale: 'Checking the saved and built-in templates that best fit this request.',
			};
		}
		const primary = candidates[0];
		const candidateLabels = candidates
			.slice(0, 3)
			.map((template) => String(template.label || '').trim())
			.filter(Boolean);
		const metadata = primary && primary.metadata && typeof primary.metadata === 'object' ? primary.metadata : {};
		const summary = String(metadata.purpose || metadata.what_it_is || primary.description || '').trim();
		let rationale = summary;
		if (!rationale && candidateLabels.length > 1) {
			rationale = `Shortlisting ${candidateLabels.join(', ')} while the planner checks the full structure.`;
		} else if (!rationale) {
			rationale = 'Checking the saved and built-in templates that best fit this request.';
		} else if (candidateLabels.length > 1) {
			rationale = `${rationale} Also checking ${candidateLabels.slice(1).join(' and ')}.`;
		}
		return {
			mode: 'known',
			label: String(primary.label || '').trim() || 'Choosing template',
			rationale,
		};
	}

	function buildQuickActionRequest(action) {
		const normalizedAction = String(action || '').trim().toLowerCase();
		const isBlog = getSelectedPostType() === 'post';
		const scope = isBlog ? 'this blog post' : 'this page';

		if (normalizedAction === 'rewrite') {
			return isBlog
				? 'Which headlines or key paragraphs in this blog post could be clearer and more compelling, and what rewrites do you suggest?'
				: 'Which headlines or key sections on this page could be clearer and more compelling, and what rewrites do you suggest?';
		}
		if (normalizedAction === 'shorten') {
			return `Which sections on ${scope} are too long, and how would you shorten them while preserving meaning?`;
		}
		if (normalizedAction === 'simplify') {
			return `Which parts of ${scope} are too complex or jargon-heavy, and how would you simplify them for readability?`;
		}
		if (normalizedAction === 'translate') {
			return isBlog
				? 'Translate the intro and CTA on this blog post into Spanish while keeping product names unchanged.'
				: 'Translate the hero headline and primary CTA on this page into Spanish while keeping product names unchanged.';
		}
		if (normalizedAction === 'seo_meta') {
			return isBlog
				? 'Give me 3 SEO title and meta description options for this blog post.'
				: 'Give me 3 SEO title and meta description options for this page.';
		}
		if (normalizedAction === 'improve_intro') {
			return isBlog
				? 'Rewrite the intro paragraph of this blog post to hook the reader in two sentences.'
				: 'Rewrite the opening section on this page so it is clearer and more compelling.';
		}
		if (normalizedAction === 'suggest_title') {
			return isBlog
				? 'Give me 5 stronger headline options for this blog post.'
				: 'Give me 5 stronger page headline options.';
		}
		if (normalizedAction === 'generate_excerpt') {
			return isBlog
				? 'Generate a concise excerpt (30-40 words) from this blog post.'
				: 'Generate a concise summary (30-40 words) from this page.';
		}
		if (normalizedAction === 'meta_description') {
			return isBlog
				? 'Generate 3 SEO meta description options (140-155 characters) for this blog post.'
				: 'Generate 3 SEO meta description options (140-155 characters) for this page.';
		}
		if (normalizedAction === 'featured_image_alt') {
			return isBlog
				? 'Generate featured image alt text for this blog post.'
				: 'Generate featured image alt text for this page.';
		}
		if (normalizedAction === 'audit') {
			return isBlog
				? 'Audit this blog post for clarity, structure, CTA strength, SEO, and accessibility.'
				: 'Audit this page for clarity, value proposition strength, CTA quality, SEO, and accessibility.';
		}
		return '';
	}

	function getQuickActionCrossField(action) {
		const normalizedAction = String(action || '').trim().toLowerCase();
		if (normalizedAction === 'generate_excerpt') {
			return 'excerpt';
		}
		if (normalizedAction === 'meta_description') {
			return 'meta_description';
		}
		if (normalizedAction === 'featured_image_alt') {
			return 'featured_image_alt';
		}
		return '';
	}

	function quickActionRequiresSelectedPost(action) {
		return String(action || '').trim().toLowerCase() === 'audit';
	}

	function queueQuickAction(action, options = {}) {
		const normalizedAction = String(action || '').trim().toLowerCase();
		if (!normalizedAction) {
			return false;
		}
		if (quickActionRequiresSelectedPost(normalizedAction) && !getSelectedPostId()) {
			setComposeStatus('Select a page first for this action.', 'warn');
			return false;
		}
		const nextRequest = buildQuickActionRequest(normalizedAction);
		if (!nextRequest) {
			return false;
		}
		state.pendingCrossField = getQuickActionCrossField(normalizedAction);
		state.pendingResolvedPostId = 0;
		state.lastRequestOrigin = normalizedAction === 'audit' ? 'audit' : 'quick_action';
		smoothFillComposer(nextRequest);
		setComposeStatus(
			String(options.statusMessage || (isSimpleMode() ? 'Got it. Press Plan changes when ready.' : 'Quick action added. Adjust if needed, then generate plan.')),
			'info'
		);
		updateQuickstartVisibility();
		return true;
	}

	function updateQuickActionAvailability() {
		if (!els.quickActions) {
			return;
		}

		const selectedPostId = getSelectedPostId();
		const hasSelectedPost = Number.isInteger(selectedPostId) && selectedPostId > 0;
		const buttons = els.quickActions.querySelectorAll('button[data-quick-action]');
		buttons.forEach((button) => {
			if (!(button instanceof HTMLButtonElement)) {
				return;
			}
			const action = button.getAttribute('data-quick-action') || '';
			const disabled = quickActionRequiresSelectedPost(action) && !hasSelectedPost;
			const enabledTitle = button.dataset.enabledTitle || button.getAttribute('title') || '';
			if (!button.dataset.enabledTitle && enabledTitle) {
				button.dataset.enabledTitle = enabledTitle;
			}
			button.disabled = disabled;
			button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
			button.title = disabled
				? 'Select a page first to audit it.'
				: (button.dataset.enabledTitle || enabledTitle);
		});
	}

	function updateIntentRailAvailability() {
		if (!els.intentRail) {
			updateIntentRailHint();
			return;
		}
		const selectedPostId = getSelectedPostId();
		const hasSelectedPost = Number.isInteger(selectedPostId) && selectedPostId > 0;
		const buttons = els.intentRail.querySelectorAll('[data-intent-rail]');
		buttons.forEach((button) => {
			if (!(button instanceof HTMLButtonElement)) {
				return;
			}
			const action = String(button.getAttribute('data-intent-rail') || '').trim().toLowerCase();
			const disabled = (action === 'edit' || action === 'analyze') && !hasSelectedPost;
			button.disabled = disabled;
			button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
			if (disabled) {
				button.classList.remove('is-active');
				button.setAttribute('aria-pressed', 'false');
			}
		});
		if (!hasSelectedPost && ['edit', 'analyze'].includes(String(state.intentRailMode || '').trim().toLowerCase())) {
			state.intentRailMode = '';
		} else if (hasSelectedPost && isSimpleMode() && !String(state.intentRailMode || '').trim()) {
			setIntentRailMode('edit');
			return;
		}
		updateIntentRailHint();
	}

		function smoothFillComposer(command) {
			const nextCommand = String(command || '').trim();
			if (!nextCommand) {
				return;
			}
			state.isAutofill = true;
			els.request.value = nextCommand;
			els.request.dispatchEvent(new Event('input', { bubbles: true }));
			state.isAutofill = false;
			window.requestAnimationFrame(() => {
			const maybeScrollIntoView = () => {
				if (!els.request || typeof els.request.getBoundingClientRect !== 'function') {
					return;
				}
				const rect = els.request.getBoundingClientRect();
				const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
				const buffer = 48;
				if (viewportHeight && (rect.top < buffer || rect.bottom > viewportHeight - buffer)) {
					els.request.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			};
			try {
				els.request.focus({ preventScroll: true });
			} catch (error) {
				els.request.focus();
			}
			if (typeof els.request.setSelectionRange === 'function') {
				const end = els.request.value.length;
				els.request.setSelectionRange(end, end);
			}
			maybeScrollIntoView();
		});
	}

	function selectResolvedPlanPost(plan) {
		const postId = plan && plan.post && plan.post.post_id ? Number(plan.post.post_id) : 0;
		if (!Number.isInteger(postId) || postId <= 0 || !els.postId) {
			return;
		}
		state.autoDetectedPostId = 0;
		if (!els.postId.querySelector(`option[value="${postId}"]`)) {
			return;
		}
		if (Number(els.postId.value || 0) === postId) {
			return;
		}
		els.postId.value = String(postId);
		els.postId.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function getPlanTargetPostId(plan) {
		return plan && plan.post && plan.post.post_id ? Number(plan.post.post_id) : 0;
	}

	function getReceiptTargetPostId(payload) {
		return payload && payload.post_id ? Number(payload.post_id) : 0;
	}

	function clearReviewStateForPostSwitch(nextPostId) {
		const normalizedPostId = Number(nextPostId || 0);
		const activePlanPostId = getPlanTargetPostId(state.plan);
		const activeReceiptPostId = getReceiptTargetPostId(state.receiptPayload);
		const shouldClearReview = (activePlanPostId > 0 && activePlanPostId !== normalizedPostId)
			|| (activeReceiptPostId > 0 && activeReceiptPostId !== normalizedPostId);
		const shouldCancelRequest = !!state.planRequestActive;
		const shouldDismissTextEntry = !!(state.textEntryModal && typeof state.textEntryModal.resolve === 'function');
		if (!shouldClearReview && !shouldCancelRequest && !shouldDismissTextEntry) {
			return;
		}
		discardActivePlanRequest();
		clearPendingPlanRecovery();
		clearPendingPlanContext();
		if (shouldDismissTextEntry) {
			dismissTextEntryModal(null);
		}
		if (state.receiptPayload) {
			clearReceipt();
		}
		if (state.plan) {
			resetPlanState();
		}
		setApplyStatus('');
		setComposeStatus(
			normalizedPostId > 0
				? 'Selected page changed. Previous review cleared.'
				: 'Page context cleared. Previous review was dismissed.',
			'info'
		);
	}

	function isAuditPlan(plan) {
		return !!plan
			&& plan.intent === 'audit'
			&& plan.audit
			&& typeof plan.audit === 'object';
	}

	function getAuditIssues(plan) {
		return isAuditPlan(plan) && Array.isArray(plan.audit.issues) ? plan.audit.issues : [];
	}

	function getAuditScoreTone(score) {
		if (score >= 9) {
			return 'strong';
		}
		if (score >= 7) {
			return 'good';
		}
		if (score >= 5) {
			return 'needs-work';
		}
		return 'critical';
	}

	function getAuditScoreLabel(score) {
		if (score >= 9) {
			return 'Strong';
		}
		if (score >= 7) {
			return 'Good foundation';
		}
		if (score >= 5) {
			return 'Needs work';
		}
		return 'High priority';
	}

	function formatAuditSeverityLabel(severity) {
		const normalized = String(severity || '').trim().toLowerCase();
		if (!normalized) {
			return 'Issue';
		}
		return `${normalized.charAt(0).toUpperCase()}${normalized.slice(1)}`;
	}

	function extractQuotedSegments(value) {
		const source = String(value || '');
		const matches = [];
		const patterns = [
			/"([^"]+)"/g,
			/'([^']+)'/g,
			/“([^”]+)”/g,
		];
		patterns.forEach((pattern) => {
			let match;
			while ((match = pattern.exec(source))) {
				const candidate = String(match[1] || '').trim();
				if (candidate) {
					matches.push(candidate);
				}
			}
		});
		return Array.from(new Set(matches));
	}

	function stripAuditCommandMachineSpeak(value) {
		return String(value || '')
			.replace(/\[[0-9.\s,]+\]/g, ' ')
			.replace(/\bindex(?:_path)?\b\s*[:=]?\s*(?:\[[^\]]+\]|[0-9]+(?:[.,][0-9]+)*)/gi, ' ')
			.replace(/\bblock(?:_id)?\b\s*[:=]?\s*[a-z0-9_-]+\b/gi, ' ')
			.replace(/\b(?:acf|core)\/[a-z0-9_-]+\b/gi, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function normalizeAuditCommandSentence(value) {
		const cleaned = stripAuditCommandMachineSpeak(value)
			.replace(/\s+([,.;:!?])/g, '$1')
			.trim();
		if (!cleaned) {
			return '';
		}
		const withoutTrailing = cleaned.replace(/[.;:,\s]+$/g, '');
		if (!withoutTrailing) {
			return '';
		}
		return `${withoutTrailing.charAt(0).toUpperCase()}${withoutTrailing.slice(1)}.`;
	}

	function inferAuditIssueTargetHint(issue) {
		const haystack = [
			issue && issue.title ? issue.title : '',
			issue && issue.section_label ? issue.section_label : '',
			issue && issue.category ? issue.category : '',
			issue && issue.rationale ? issue.rationale : '',
			issue && issue.fix_command ? issue.fix_command : '',
		].join(' ').toLowerCase();
		if (/\b(cta|button|trial|demo)\b/.test(haystack)) {
			return 'button_text';
		}
		if (/\b(subheading|subtitle|subhead)\b/.test(haystack)) {
			return 'subheading';
		}
		if (/\b(headline|heading|title|hero)\b/.test(haystack)) {
			return 'headline';
		}
		if (/\b(description|copy|body|paragraph|text)\b/.test(haystack)) {
			return 'description';
		}
		if (/\b(html|markup)\b/.test(haystack)) {
			return 'html';
		}
		return 'content';
	}

	function getAuditFieldPhrase(issue, targetHint) {
		const sectionLabel = String(issue && issue.section_label ? issue.section_label : '').trim();
		const normalizedSection = sectionLabel.toLowerCase();
		if (targetHint === 'button_text') {
			if (/\bcta\b/.test(normalizedSection)) {
				return `${sectionLabel} button text`;
			}
			if (/\bhero\b/.test(normalizedSection)) {
				return `${sectionLabel} CTA button text`;
			}
			return sectionLabel ? `${sectionLabel} button text` : 'button text';
		}
		if (targetHint === 'headline') {
			return sectionLabel ? `${sectionLabel} headline` : 'headline';
		}
		if (targetHint === 'subheading') {
			return sectionLabel ? `${sectionLabel} subheading` : 'subheading';
		}
		if (targetHint === 'description') {
			return sectionLabel ? `${sectionLabel} copy` : 'copy';
		}
		if (targetHint === 'html') {
			return sectionLabel ? `${sectionLabel} html` : 'html';
		}
		return sectionLabel ? `${sectionLabel} content` : 'content';
	}

	function looksLikeMachineSpeakAuditCommand(rawCommand) {
		return /\[[0-9.\s,]+\]/.test(rawCommand)
			|| /\bindex(?:_path)?\b/i.test(rawCommand)
			|| /\b(?:acf|core)\/[a-z0-9_-]+\b/i.test(rawCommand)
			|| /\b[A-Z][A-Z0-9 ]{5,}\b/.test(rawCommand);
	}

	function pickBestAuditReplacementValue(quotedValues, issue) {
		const values = Array.isArray(quotedValues)
			? quotedValues
				.map((value) => String(value || '').trim())
				.filter(Boolean)
			: [];
		if (!values.length) {
			return '';
		}
		if (values.length === 1) {
			return values[0];
		}
		const issueTitle = String(issue && issue.title ? issue.title : '').trim().toLowerCase();
		const sectionLabel = String(issue && issue.section_label ? issue.section_label : '').trim().toLowerCase();
		const preferred = values
			.filter((value) => {
				const normalized = value.toLowerCase();
				return normalized !== issueTitle && normalized !== sectionLabel;
			})
			.sort((left, right) => right.length - left.length);
		if (preferred.length) {
			return preferred[0];
		}
		return values.slice().sort((left, right) => right.length - left.length)[0];
	}

	function buildPlannerSafeAuditCommand(issue, post, options = {}) {
		const rawCommand = String(issue && issue.fix_command ? issue.fix_command : '').trim();
		if (!rawCommand) {
			return '';
		}
		const includePostHint = options.includePostHint !== false;
		const cleanedSentence = normalizeAuditCommandSentence(rawCommand);
		const targetHint = inferAuditIssueTargetHint(issue);
		const fieldPhrase = getAuditFieldPhrase(issue, targetHint);
		const postId = includePostHint && post && Number(post.post_id || 0) ? Number(post.post_id) : 0;
		const postFragment = postId ? ` on post ${postId}` : '';
		const quotedValues = extractQuotedSegments(rawCommand);
		const hasUpdateSignal = /\b(update|change|edit|set|replace|rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust|improve)\b/i.test(rawCommand);
		const hasInsertSignal = /\b(add|insert|append)\b/i.test(rawCommand);
		const hasRemoveSignal = /\b(remove|delete|erase|drop)\b/i.test(rawCommand);
		const hasMixedSignal = (hasUpdateSignal && hasInsertSignal) || (hasUpdateSignal && hasRemoveSignal) || (hasInsertSignal && hasRemoveSignal);

		if (quotedValues.length && (hasMixedSignal || looksLikeMachineSpeakAuditCommand(rawCommand))) {
			const nextValue = pickBestAuditReplacementValue(quotedValues, issue).replace(/["“”]/g, '\'').replace(/\s+/g, ' ').trim();
			if (nextValue) {
				return `Update the ${fieldPhrase}${postFragment} to "${nextValue}".`;
			}
		}

		if (!hasMixedSignal && cleanedSentence) {
			return cleanedSentence;
		}

		if (quotedValues.length) {
			const nextValue = pickBestAuditReplacementValue(quotedValues, issue).replace(/["“”]/g, '\'').replace(/\s+/g, ' ').trim();
			if (nextValue) {
				return `Update the ${fieldPhrase}${postFragment} to "${nextValue}".`;
			}
		}

		if (cleanedSentence) {
			return cleanedSentence;
		}

		return rawCommand;
	}

	function fillComposerFromAuditCommand(command, options = {}) {
		const nextCommand = String(command || '').trim();
		if (!nextCommand) {
			return;
		}
		const planFromFinding = !!options.planFromFinding;
		const plan = isAuditPlan(state.plan) ? state.plan : null;
		if (plan) {
			state.pendingResolvedPostId = Number(plan.post && plan.post.post_id ? plan.post.post_id : 0) || 0;
			selectResolvedPlanPost(plan);
		} else {
			state.pendingResolvedPostId = 0;
		}
		state.pendingPlanRecovery = planFromFinding && plan ? { plan, origin: 'audit_apply' } : null;
		state.pendingCrossField = '';
		state.lastRequestOrigin = planFromFinding ? 'audit_apply' : 'audit';
		resetPlanState();
		clearReceipt();
		smoothFillComposer(nextCommand);
		if (planFromFinding) {
			setComposeStatus(isSimpleMode() ? 'Generating a plan from this audit fix…' : 'Generating plan from audit fix…', 'loading');
			window.setTimeout(() => handleGeneratePlan(), 0);
			return;
		}
		setComposeStatus(
			options.customStatus || (isSimpleMode() ? 'Audit fix added. Press Plan changes when ready.' : 'Audit fix added to composer. Generate plan to continue.'),
			'info'
		);
	}

	function buildAuditFixAllCommand(plan) {
		const issues = getAuditIssues(plan)
			.map((issue) => buildPlannerSafeAuditCommand(issue, plan && plan.post ? plan.post : null))
			.filter(Boolean);
		if (!issues.length) {
			return '';
		}
		const post = plan && plan.post && typeof plan.post === 'object' ? plan.post : {};
		const scope = post.post_title || post.post_slug || (getSelectedPostType() === 'post' ? 'this blog post' : 'this page');
		return [
			`Use this audit checklist for ${scope}. Apply these fixes one at a time, not as a single bulk plan:`,
			...issues.map((command, index) => `${index + 1}. ${command}`),
		].join('\n');
	}

	function clearPendingPlanContext() {
		state.pendingCrossField = '';
		state.pendingResolvedPostId = 0;
	}

	function clearPendingPlanRecovery() {
		state.pendingPlanRecovery = null;
	}

	function isStalePlanRequest(requestId) {
		if (!requestId) {
			return true;
		}
		const activeRequestId = String(state.planStreamRequestId || '').trim();
		return !activeRequestId || String(requestId).trim() !== activeRequestId;
	}

	function invalidatePlanRequest() {
		state.planStreamRequestId = '';
	}

	function discardActivePlanRequest() {
		invalidatePlanRequest();
		if (state.planRequestController) {
			state.planRequestController.abort();
		}
		state.planRequestController = null;
		setPlanRequestActive(false);
		setReviewLoadingStage('');
		state.loadingRequestText = '';
		state.loadingTargetLabel = '';
		clearStreamedReviewPreview();
	}

	function shouldRestoreRecoveredPlan(error, requestOrigin) {
		const origin = String(requestOrigin || '').trim().toLowerCase();
		if (!['suggestion_apply', 'audit_apply'].includes(origin)) {
			return false;
		}
		const code = String(error && error.code ? error.code : '').trim();
		return ['sae_plan_missing_target', 'sae_invalid_target', 'sae_target_not_found', 'sae_target_ambiguous'].includes(code);
	}

	function getRecoveredPlanErrorMessage(error, requestOrigin) {
		const origin = String(requestOrigin || '').trim().toLowerCase();
		const fallback = getFriendlyErrorMessage(error, config.strings?.network_error || 'Request failed.');
		if (origin === 'suggestion_apply') {
			return "I couldn't auto-apply this suggestion on the selected DS page yet. Use \"Use this\" to refine it manually.";
		}
		if (origin === 'audit_apply') {
			return "I couldn't turn this audit fix into an editable plan for the selected page yet. Add it to the composer and refine it manually.";
		}
		return fallback;
	}

	function getPlanPostType(plan) {
		if (!plan || typeof plan !== 'object') {
			return '';
		}
		if (plan.post && typeof plan.post === 'object' && plan.post.post_type) {
			return String(plan.post.post_type).trim().toLowerCase();
		}
		if (plan.post_type) {
			return String(plan.post_type).trim().toLowerCase();
		}
		return '';
	}

	function annotatePlanRequestOrigin(plan, requestOrigin = '') {
		if (!plan || typeof plan !== 'object') {
			return plan;
		}
		const normalizedOrigin = String(requestOrigin || '').trim().toLowerCase();
		if (!normalizedOrigin) {
			return plan;
		}
		const nextPlan = Object.assign({}, plan);
		nextPlan.request_origin = normalizedOrigin;
		return nextPlan;
	}

	function shouldRejectAutoApplyInsert(plan, requestOrigin) {
		const origin = String(requestOrigin || '').trim().toLowerCase();
		if (!['suggestion_apply', 'audit_apply'].includes(origin)) {
			return false;
		}
		if (!plan || typeof plan !== 'object' || plan.operation !== 'insert') {
			return false;
		}
		const postType = getPlanPostType(plan);
		return postType !== 'post';
	}

	function getAutoApplyInsertMessage(requestOrigin) {
		const origin = String(requestOrigin || '').trim().toLowerCase();
		if (origin === 'audit_apply') {
			return "I can't auto-apply this audit fix as a new block on the selected DS page yet. Add it to the composer and refine it manually.";
		}
		return "I can't auto-apply this suggestion as a new block on the selected DS page yet. Use \"Use this\" to refine it manually.";
	}

	function normalizeSuggestionTargetHint(value) {
		const normalized = String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
		if (!normalized) {
			return '';
		}
		const aliasMap = {
			h1: 'headline',
			hero_heading: 'headline',
			hero_headline: 'headline',
			main_heading: 'headline',
			page_heading: 'headline',
			page_title: 'headline',
			cta: 'button_text',
			cta_text: 'button_text',
			button: 'button_text',
			button_label: 'button_text',
			headline: 'headline',
			title: 'headline',
			subtitle: 'subheading',
			subhead: 'subheading',
			body: 'description',
			copy: 'description',
		};
		return aliasMap[normalized] || normalized;
	}

	function buildPlannerSafeSuggestionCommand(suggestion, post) {
		const fallback = String(suggestion && suggestion.command ? suggestion.command : '').trim();
		if (!suggestion || typeof suggestion !== 'object') {
			return fallback;
		}

		const candidateValue = getSuggestionCandidateValue(suggestion);
		const inferredFromCommand = (() => {
			const commandText = fallback.toLowerCase();
			if (/\b(cta|button)\b/.test(commandText)) {
				return 'button_text';
			}
			if (/\b(subheading|subtitle|subhead)\b/.test(commandText)) {
				return 'subheading';
			}
			if (/\b(description|body|copy)\b/.test(commandText)) {
				return 'description';
			}
			if (/\b(html|markup)\b/.test(commandText)) {
				return 'html';
			}
			if (/\b(h1|hero\s+heading|hero\s+headline|main\s+heading|page\s+heading|page\s+title|header)\b/.test(commandText)) {
				return 'headline';
			}
			if (/\b(headline|title|heading)\b/.test(commandText)) {
				return 'headline';
			}
			return '';
		})();
		if (!candidateValue) {
			return fallback;
		}
		const targetHint = normalizeSuggestionTargetHint(suggestion.target_hint || inferredFromCommand) || 'content';

		const targetMap = {
			button_text: 'button text',
			headline: 'headline',
			subheading: 'subheading',
			description: 'description',
			content: 'content',
			html: 'html',
		};
		const fieldPhrase = targetMap[targetHint] || targetHint.replace(/_/g, ' ');
		const postId = post && Number(post.post_id || 0) ? Number(post.post_id) : 0;
		const postFragment = postId ? ` on post ${postId}` : '';
		const normalizedValue = candidateValue.replace(/["“”]/g, "'").replace(/\s+/g, ' ').trim();

		return `Update the ${fieldPhrase}${postFragment} to "${normalizedValue}".`;
	}

	function shouldRetryPlanWithoutStreaming(plan, payload) {
		if (!payload || !payload.dry_run_preview || !plan || typeof plan !== 'object') {
			return false;
		}
		if (plan.intent === 'ask' || isAuditPlan(plan) || isCreateOutlinePlan(plan)) {
			return false;
		}
		if (hasDurableEnvelope(plan)) {
			return false;
		}
		return ['update', 'insert', 'remove', 'batch', 'cross_field'].includes(String(plan.operation || '').trim());
	}

	function getPlannerBadgeLabel(planner) {
		const source = String((planner && planner.source) || '');
		if (source === 'ai_engine' || source === 'client' || source === 'openai') {
			return 'planner: ai-assisted';
		}
		if (source === 'ai_rewrite') {
			return 'planner: ai rewrite';
		}
		if (source === 'batch_composer') {
			return 'planner: batch composer';
		}
		if (source === 'deterministic' || source === 'direct' || source === 'inline_json') {
			return 'planner: deterministic';
		}
		return source ? `planner: ${source.replace(/_/g, ' ')}` : 'planner: deterministic';
	}

	function persistRequestHistoryEnabled() {
		return !!features.persist_request_history;
	}

	function loadRequestHistory() {
		if (!persistRequestHistoryEnabled()) {
			try {
				window.sessionStorage.removeItem(REQUEST_HISTORY_STORAGE_KEY);
			} catch (error) {
				/* no-op: storage may be blocked */
			}
			return [];
		}
		try {
			const raw = window.sessionStorage.getItem(REQUEST_HISTORY_STORAGE_KEY);
			if (!raw) {
				return [];
			}
			const decoded = JSON.parse(raw);
			if (!Array.isArray(decoded)) {
				return [];
			}
			return decoded
				.map((entry) => String(entry || '').trim())
				.filter(Boolean)
				.slice(0, REQUEST_HISTORY_LIMIT);
		} catch (error) {
			return [];
		}
	}

	function saveRequestHistory() {
		if (!persistRequestHistoryEnabled()) {
			try {
				window.sessionStorage.removeItem(REQUEST_HISTORY_STORAGE_KEY);
			} catch (error) {
				/* no-op: storage may be blocked */
			}
			return;
		}
		try {
			window.sessionStorage.setItem(REQUEST_HISTORY_STORAGE_KEY, JSON.stringify(state.requestHistory.slice(0, REQUEST_HISTORY_LIMIT)));
		} catch (error) {
			/* no-op: storage may be blocked */
		}
	}

	function clearRequestHistory() {
		state.requestHistory = [];
		resetRequestHistoryCursor();
		saveRequestHistory();
	}

	function resetRequestHistoryCursor() {
		state.requestHistoryCursor = -1;
	}

	function rememberRequest(requestText) {
		const next = String(requestText || '').trim();
		if (!next) {
			return;
		}
		const deduped = state.requestHistory.filter((entry) => entry !== next);
		state.requestHistory = [next, ...deduped].slice(0, REQUEST_HISTORY_LIMIT);
		saveRequestHistory();
		resetRequestHistoryCursor();
	}

	function cycleRequestHistory(direction) {
		const history = Array.isArray(state.requestHistory) ? state.requestHistory : [];
		if (!history.length) {
			return false;
		}

		if (direction < 0) {
			if (state.requestHistoryCursor < history.length - 1) {
				state.requestHistoryCursor += 1;
			}
		} else {
			if (state.requestHistoryCursor < 0) {
				return false;
			}
			if (state.requestHistoryCursor === 0) {
				state.requestHistoryCursor = -1;
				els.request.value = '';
				state.isCyclingRequestHistory = true;
				els.request.dispatchEvent(new Event('input', { bubbles: true }));
				state.isCyclingRequestHistory = false;
				return true;
			}
			state.requestHistoryCursor -= 1;
		}

		if (state.requestHistoryCursor >= 0 && state.requestHistoryCursor < history.length) {
			const next = history[state.requestHistoryCursor] || '';
			els.request.value = next;
			state.isCyclingRequestHistory = true;
			els.request.dispatchEvent(new Event('input', { bubbles: true }));
			state.isCyclingRequestHistory = false;
			if (typeof els.request.setSelectionRange === 'function') {
				const end = els.request.value.length;
				els.request.setSelectionRange(end, end);
			}
			return true;
		}

		return false;
	}

	function initializeFadeTargets() {
		[els.planEmpty, els.planContent, els.receipt].forEach((element) => {
			if (!element) {
				return;
			}
			element.classList.add('sae-fade');
			element.classList.toggle('is-visible', !element.hidden);
		});
	}

	function setFadeVisibility(element, visible) {
		if (!element) {
			return;
		}

		const timerKey = '__saeHideTimer';
		if (element[timerKey]) {
			window.clearTimeout(element[timerKey]);
			element[timerKey] = null;
		}

		if (visible) {
			element.hidden = false;
			window.requestAnimationFrame(() => {
				element.classList.add('is-visible');
			});
			return;
		}

		element.classList.remove('is-visible');
		element[timerKey] = window.setTimeout(() => {
			if (!element.classList.contains('is-visible')) {
				element.hidden = true;
			}
		}, PANEL_FADE_DURATION_MS);
	}

	function hasCachedScoutAnalysis() {
		const postId = getSelectedPostId();
		if (!postId) {
			return false;
		}
		const snapshot = normalizeScoutAuditSnapshot(readScoutAuditSnapshot(postId));
		return !!snapshot && (Number(snapshot.analyzed_at || snapshot.cached_at) || 0) > 0;
	}

	function updateConsoleUiStage(stageOverride = '') {
		if (!els.consoleRoot) {
			return;
		}

		let nextStage = String(stageOverride || '').trim().toLowerCase();
		if (!nextStage) {
			if (els.reviewPanel && els.reviewPanel.classList.contains('is-loading')) {
				nextStage = 'loading';
			} else if (state.plan) {
				nextStage = 'review';
			} else if (state.receiptPayload) {
				nextStage = 'receipt';
			} else {
				nextStage = 'idle';
			}
		}

		els.consoleRoot.dataset.uiStage = nextStage;
		const idleSurface = nextStage === 'idle' && isSimpleMode() && !!getSelectedPostId() && hasCachedScoutAnalysis() ? 'scout' : 'welcome';
		els.consoleRoot.dataset.idleSurface = idleSurface;
		if (els.composePanel) {
			els.composePanel.dataset.idleSurface = idleSurface;
		}
		if (els.reviewPanel) {
			els.reviewPanel.dataset.idleSurface = idleSurface;
		}
		if (els.primaryGrid) {
			els.primaryGrid.dataset.uiStage = nextStage;
		}
		if (els.secondaryGrid) {
			els.secondaryGrid.dataset.uiStage = nextStage;
		}
		updateQuickstartVisibility();
	}

	function clearProgressiveRenderTimers() {
		if (!Array.isArray(state.progressiveRenderTimers) || !state.progressiveRenderTimers.length) {
			state.progressiveRenderTimers = [];
			return;
		}
		state.progressiveRenderTimers.forEach((timerId) => {
			window.clearTimeout(timerId);
		});
		state.progressiveRenderTimers = [];
	}

	function appendHtmlCard(container, html) {
		if (!container || !html) {
			return;
		}
		const template = document.createElement('template');
		template.innerHTML = String(html || '').trim();
		const node = template.content.firstElementChild;
		if (!node) {
			return;
		}
		container.appendChild(node);
	}

	function clearStreamedReviewPreview() {
		state.streamPreview = null;
		if (!els.askList) {
			return;
		}
		delete els.askList.dataset.streamPreviewKind;
	}

	function beginStreamedReviewPreview(kind, meta = {}, requestId = '') {
		if (!els.askWrap || !els.askList) {
			return null;
		}
		const normalizedKind = String(kind || '').trim().toLowerCase();
		const normalizedRequestId = String(requestId || '').trim();
		if (!normalizedKind) {
			return null;
		}
		if (
			state.streamPreview
			&& state.streamPreview.kind === normalizedKind
			&& state.streamPreview.requestId === normalizedRequestId
		) {
			return state.streamPreview;
		}

		clearProgressiveRenderTimers();
		state.streamPreview = {
			kind: normalizedKind,
			count: 0,
			requestId: normalizedRequestId,
		};
		els.askWrap.hidden = false;
		els.askList.dataset.streamPreviewKind = normalizedKind;

		if (normalizedKind === 'suggestions') {
			setAskPanelCopy('Suggestions', 'Receiving ideas as they are ready.');
			els.askList.innerHTML = '<div class="sae-ask-list" data-stream-preview-list="suggestions"></div>';
		} else if (normalizedKind === 'audit') {
			const score = Number(meta && meta.score ? meta.score : 0);
			const summary = String(meta && meta.summary ? meta.summary : '').trim() || 'Audit complete.';
			const issueCount = Number(meta && meta.issue_count ? meta.issue_count : 0);
			const scoreTone = getAuditScoreTone(score);
			const scoreLabel = Number.isFinite(score) && score > 0 ? `${score}/10` : '--';
			setAskPanelCopy('Audit results', 'Receiving findings as they are ready.');
			els.askList.innerHTML = `
				<section class="sae-audit-results sae-audit-results--streaming">
					<header class="sae-audit-results__header">
						<div class="sae-audit-score-card sae-audit-score-card--${escapeHtml(scoreTone)}">
							<p class="sae-audit-score-card__eyebrow">Page score</p>
							<p class="sae-audit-score-card__value">${escapeHtml(scoreLabel)}</p>
							<p class="sae-audit-score-card__label">${escapeHtml(getAuditScoreLabel(score))}</p>
						</div>
						<div class="sae-audit-results__summary">
							<p class="sae-audit-results__eyebrow">${escapeHtml(`${issueCount} issue${issueCount === 1 ? '' : 's'} found`)}</p>
							<p class="sae-audit-results__text">${escapeHtml(summary)}</p>
						</div>
					</header>
					<div class="sae-audit-results__list" data-stream-preview-list="audit"></div>
				</section>
			`;
		} else if (normalizedKind === 'outline') {
			const title = String(meta && meta.title ? meta.title : '').trim() || 'Untitled draft';
			const structureSummary = buildTemplateChoiceMarkup({
				mode: meta && meta.template_mode ? String(meta.template_mode).trim().toLowerCase() : '',
				label: meta && meta.template_label ? String(meta.template_label).trim() : '',
				rationale: meta && meta.template_rationale ? String(meta.template_rationale).trim() : '',
			});
			setAskPanelCopy("Here's a suggested outline", 'Receiving sections as they are ready.');
			els.askList.innerHTML = `
				${structureSummary}
				<div class="sae-create-outline-controls">
					<p class="sae-help">Sections are streaming in. Final selection controls appear when the outline is complete.</p>
				</div>
				<div class="sae-create-outline-list" data-stream-preview-list="outline"></div>
				<p class="sae-create-outline-title">Draft title: ${escapeHtml(title)}</p>
			`;
		}

		setReviewLoadingStage('');
		updateConsoleUiStage('review');
		return state.streamPreview;
	}

	function primeReviewLoadingPreview(requestText, requestOrigin, requestId, options = {}) {
		if (state.plan) {
			return false;
		}
		const normalizedRequestId = String(requestId || '').trim();
		if (!normalizedRequestId) {
			return false;
		}
		const createIntent = !!options.createIntent;
		if (createIntent) {
			const templateChoice = buildCreateLoadingTemplateChoice(requestText);
			beginStreamedReviewPreview('outline', {
				title: String(options.createTitle || 'New draft').trim() || 'New draft',
				template_mode: String(templateChoice.mode || '').trim(),
				template_label: String(templateChoice.label || '').trim(),
				template_rationale: String(templateChoice.rationale || '').trim(),
			}, normalizedRequestId);
			return true;
		}
		if (isLikelyAuditIntentRequest(requestText, requestOrigin)) {
			beginStreamedReviewPreview('audit', {
				score: 0,
				summary: 'Scanning the page and lining up the first findings.',
				issue_count: 0,
			}, normalizedRequestId);
			return true;
		}
		if (isLikelyAskIntentRequest(requestText, requestOrigin)) {
			beginStreamedReviewPreview('suggestions', {}, normalizedRequestId);
			return true;
		}
		return false;
	}

	function prepareExistingPlanForRefresh() {
		if (!state.plan) {
			return;
		}
		if (els.planPayloadWrap) {
			els.planPayloadWrap.hidden = true;
		}
		if (els.planPayload) {
			els.planPayload.textContent = '';
		}
		if (els.dryRunWrap) {
			els.dryRunWrap.hidden = true;
		}
		if (els.planDryRun) {
			els.planDryRun.textContent = '';
		}
		if (els.planDiffWrap) {
			els.planDiffWrap.hidden = true;
		}
		if (els.planDiffList) {
			els.planDiffList.innerHTML = '';
		}
		if (els.tokenWrap) {
			els.tokenWrap.hidden = true;
		}
		if (els.tokenValue) {
			els.tokenValue.textContent = '';
		}
		if (els.tokenExpiry) {
			els.tokenExpiry.textContent = '';
		}
		if (els.planActions) {
			els.planActions.hidden = true;
		}
		clearTokenCountdown();
		updateApplyButtonState();
	}

	function appendStreamedReviewCard(kind, html, requestId = '') {
		if (!html || !els.askList) {
			return;
		}
		const normalizedKind = String(kind || '').trim().toLowerCase();
		const normalizedRequestId = String(requestId || '').trim();
		if (!normalizedKind) {
			return;
		}
		if (
			!state.streamPreview
			|| state.streamPreview.kind !== normalizedKind
			|| state.streamPreview.requestId !== normalizedRequestId
		) {
			beginStreamedReviewPreview(normalizedKind, {}, normalizedRequestId);
		}
		const list = els.askList.querySelector(`[data-stream-preview-list="${normalizedKind}"]`);
		if (!list) {
			return;
		}
		appendHtmlCard(list, html);
		if (state.streamPreview && state.streamPreview.kind === normalizedKind) {
			state.streamPreview.count += 1;
		}
	}

	function buildStreamedSuggestionCard(suggestion, index = 0) {
		const title = String(suggestion && suggestion.title ? suggestion.title : `Suggestion ${index + 1}`);
		const rationale = String(suggestion && suggestion.rationale ? suggestion.rationale : '').trim();
		const candidateValue = getSuggestionCandidateValue(suggestion);
		return `
			<article class="sae-suggestion-item sae-progressive-card" style="--sae-stagger-index:${Number(index) || 0}">
				<h4>${escapeHtml(title)}</h4>
				${rationale ? `<p>${escapeHtml(rationale)}</p>` : ''}
				${candidateValue ? `<pre class="sae-suggestion-candidate">${escapeHtml(candidateValue)}</pre>` : ''}
			</article>
		`;
	}

	function buildStreamedAuditIssueCard(issue, index = 0) {
		const severity = String(issue && issue.severity ? issue.severity : '').trim().toLowerCase();
		const title = String(issue && issue.title ? issue.title : `Issue ${index + 1}`);
		const rationale = String(issue && issue.rationale ? issue.rationale : '').trim();
		const sectionLabel = String(issue && issue.section_label ? issue.section_label : '').trim();
		const category = String(issue && issue.category ? issue.category : '').trim();
		const meta = [
			severity ? `<span class="sae-audit-issue__badge sae-audit-issue__badge--${escapeHtml(severity)}">${escapeHtml(formatAuditSeverityLabel(severity))}</span>` : '',
			category ? `<span class="sae-audit-issue__meta-chip">${escapeHtml(category)}</span>` : '',
			sectionLabel ? `<span class="sae-audit-issue__meta-chip">${escapeHtml(sectionLabel)}</span>` : '',
		].filter(Boolean).join('');
		return `
			<article class="sae-audit-issue sae-audit-issue--${escapeHtml(severity || 'low')} sae-progressive-card" style="--sae-stagger-index:${Number(index) || 0}">
				<div class="sae-audit-issue__header">
					<h4>${escapeHtml(title)}</h4>
					${meta ? `<div class="sae-audit-issue__meta">${meta}</div>` : ''}
				</div>
				<p class="sae-audit-issue__rationale">${escapeHtml(rationale || 'No rationale returned.')}</p>
			</article>
		`;
	}

	function buildStreamedOutlineSectionCard(section, index = 0) {
		const sectionTitle = section && section.section ? String(section.section) : `Section ${index + 1}`;
		const purpose = section && section.purpose ? String(section.purpose) : 'Purpose not provided.';
		const blockName = section && section.block_name ? String(section.block_name) : '';
		const reasoning = section && section.reasoning ? String(section.reasoning) : '';
		const simpleModeActive = isSimpleMode();
		const devMeta = !simpleModeActive && blockName
			? `<p class="sae-create-outline-item__meta"><code>${escapeHtml(blockName)}</code></p>`
			: '';
		const devReasoning = !simpleModeActive && reasoning
			? `<p class="sae-create-outline-item__reasoning">${escapeHtml(reasoning)}</p>`
			: '';
		return `
			<article class="sae-create-outline-item sae-progressive-card" style="--sae-stagger-index:${Number(index) || 0}">
				<span class="sae-create-outline-item__body">
					<p class="sae-create-outline-item__index">Section ${Number(index) + 1}</p>
					<h4>${escapeHtml(sectionTitle)}</h4>
					<p>${escapeHtml(purpose)}</p>
					${devMeta}
					${devReasoning}
				</span>
			</article>
		`;
	}

	function handlePlanStreamEvent(eventName, streamPayload, context = {}) {
		const requestId = String(context.requestId || '').trim();
		if (requestId && state.planStreamRequestId && requestId !== state.planStreamRequestId) {
			return;
		}
		const requestText = String(context.requestText || '').trim();
		const targetLabel = String(context.targetLabel || '').trim();
		const loadingContext = context.loadingContext && typeof context.loadingContext === 'object' ? context.loadingContext : {};
		if (eventName === 'stage') {
			const stageKey = streamPayload && streamPayload.stage === 'ai' ? 'ai' : 'parsing';
			setReviewLoadingStage(stageKey, {
				requestText,
				targetLabel,
				...loadingContext,
			});
			if (streamPayload && streamPayload.message) {
				setComposeStatus(String(streamPayload.message), 'loading');
			}
			return;
		}

		if (eventName === 'heartbeat') {
			if (streamPayload && streamPayload.message) {
				setComposeStatus(String(streamPayload.message), 'loading');
			}
			return;
		}

		if (eventName === 'suggestions_meta') {
			beginStreamedReviewPreview('suggestions', streamPayload, requestId);
			setComposeStatus(isSimpleMode() ? 'Drafting suggestion ideas…' : 'Receiving suggestions…', 'loading');
			return;
		}
		if (eventName === 'suggestion') {
			beginStreamedReviewPreview('suggestions', streamPayload, requestId);
			appendStreamedReviewCard('suggestions', buildStreamedSuggestionCard(streamPayload && streamPayload.suggestion, streamPayload && streamPayload.index ? Number(streamPayload.index) : 0), requestId);
			setComposeStatus(isSimpleMode() ? 'Drafting suggestion ideas…' : 'Receiving suggestions…', 'loading');
			return;
		}

		if (eventName === 'audit_meta') {
			beginStreamedReviewPreview('audit', streamPayload, requestId);
			setComposeStatus(isSimpleMode() ? 'Receiving audit findings…' : 'Receiving audit findings…', 'loading');
			return;
		}
		if (eventName === 'audit_issue') {
			beginStreamedReviewPreview('audit', streamPayload, requestId);
			appendStreamedReviewCard('audit', buildStreamedAuditIssueCard(streamPayload && streamPayload.issue, streamPayload && streamPayload.index ? Number(streamPayload.index) : 0), requestId);
			setComposeStatus(isSimpleMode() ? 'Receiving audit findings…' : 'Receiving audit findings…', 'loading');
			return;
		}

		if (eventName === 'outline_meta') {
			beginStreamedReviewPreview('outline', streamPayload, requestId);
			setComposeStatus(isSimpleMode() ? 'Receiving outline sections…' : 'Receiving outline sections…', 'loading');
			return;
		}
		if (eventName === 'outline_section') {
			beginStreamedReviewPreview('outline', streamPayload, requestId);
			appendStreamedReviewCard('outline', buildStreamedOutlineSectionCard(streamPayload && streamPayload.section, streamPayload && streamPayload.index ? Number(streamPayload.index) : 0), requestId);
			setComposeStatus(isSimpleMode() ? 'Receiving outline sections…' : 'Receiving outline sections…', 'loading');
		}
	}

	function progressivelyRenderHtmlCards(container, items, options = {}) {
		if (!container) {
			return;
		}
		clearProgressiveRenderTimers();
		container.innerHTML = '';
		const cards = Array.isArray(items) ? items.filter(Boolean) : [];
		if (!cards.length) {
			if (options.emptyHtml) {
				container.innerHTML = options.emptyHtml;
			}
			return;
		}
		const intervalMs = Math.max(0, Number(options.intervalMs || 80));
		cards.forEach((html, index) => {
			if (index === 0) {
				appendHtmlCard(container, html);
				return;
			}
			const timerId = window.setTimeout(() => {
				appendHtmlCard(container, html);
				state.progressiveRenderTimers = state.progressiveRenderTimers.filter((id) => id !== timerId);
			}, intervalMs * index);
			state.progressiveRenderTimers.push(timerId);
		});
	}

	function formatJson(value) {
		return JSON.stringify(value || {}, null, 2);
	}

	function buildUrl(base, params = {}) {
		const url = new URL(base, window.location.origin);
		Object.entries(params).forEach(([key, rawValue]) => {
			const value = rawValue === undefined || rawValue === null ? '' : String(rawValue);
			if (value !== '') {
				url.searchParams.set(key, value);
			}
		});
		return url.toString();
	}

	function getAuditCacheStorageKey(action = '', hideReadOnly = false) {
		const normalizedAction = String(action || '').trim().toLowerCase() || 'all';
		return `${AUDIT_CACHE_STORAGE_KEY}:${normalizedAction}:${hideReadOnly ? '1' : '0'}`;
	}

	function readAuditCache(action = '', hideReadOnly = false) {
		try {
			const raw = window.sessionStorage.getItem(getAuditCacheStorageKey(action, hideReadOnly));
			if (!raw) {
				return null;
			}
			const parsed = JSON.parse(raw);
			const savedAt = Number(parsed && parsed.saved_at ? parsed.saved_at : 0);
			const items = parsed && Array.isArray(parsed.items) ? parsed.items : [];
			if (!savedAt || (Date.now() - savedAt) > AUDIT_CACHE_TTL_MS) {
				window.sessionStorage.removeItem(getAuditCacheStorageKey(action, hideReadOnly));
				return null;
			}
			return items;
		} catch (_error) {
			return null;
		}
	}

	function writeAuditCache(action = '', hideReadOnly = false, items = []) {
		try {
			window.sessionStorage.setItem(
				getAuditCacheStorageKey(action, hideReadOnly),
				JSON.stringify({
					saved_at: Date.now(),
					items: Array.isArray(items) ? items : [],
				})
			);
		} catch (_error) {
			// Ignore storage failures.
		}
	}

	function getCachedPostBlocks(postId) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return null;
		}
		const entry = state.postBlocksCache[normalizedPostId];
		if (!entry || !Array.isArray(entry.blocks) || !entry.savedAt) {
			return null;
		}
		if ((Date.now() - entry.savedAt) > POST_BLOCKS_CACHE_TTL_MS) {
			delete state.postBlocksCache[normalizedPostId];
			return null;
		}
		return entry.blocks;
	}

	function cachePostBlocks(postId, blocks) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return [];
		}
		const normalizedBlocks = Array.isArray(blocks) ? blocks : [];
		state.postBlocksCache[normalizedPostId] = {
			blocks: normalizedBlocks,
			savedAt: Date.now(),
		};
		return normalizedBlocks;
	}

	async function fetchPostBlocks(postId, options = {}) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return [];
		}
		const force = !!options.force;
		if (!force) {
			const cachedBlocks = getCachedPostBlocks(normalizedPostId);
			if (cachedBlocks) {
				return cachedBlocks;
			}
			if (state.postBlocksRequests[normalizedPostId]) {
				return state.postBlocksRequests[normalizedPostId];
			}
		}

		const request = requestJson(buildPostBlocksEndpoint(normalizedPostId), {
			cache: force ? 'no-store' : 'default',
		})
			.then((response) => cachePostBlocks(normalizedPostId, Array.isArray(response.blocks) ? response.blocks : []))
			.finally(() => {
				delete state.postBlocksRequests[normalizedPostId];
			});
		state.postBlocksRequests[normalizedPostId] = request;
		return request;
	}

	function warmSelectedPostBlocks(postId) {
		const normalizedPostId = Number(postId || 0);
		if (!normalizedPostId) {
			return;
		}
		fetchPostBlocks(normalizedPostId).catch(() => {
			// Warm-cache only.
		});
	}

	function buildCreateTimelineMarkup(currentPhase = 'outline') {
		const activePhase = String(currentPhase || 'outline').trim().toLowerCase();
		const phaseIndex = activePhase === 'save' ? 2 : activePhase === 'content' ? 1 : 0;
		const steps = [
			{ key: 'outline', label: 'Outline' },
			{ key: 'content', label: 'Draft copy' },
			{ key: 'save', label: 'Save draft' },
		];
		return steps.map((step, index) => {
			const statusClass = index < phaseIndex ? ' is-done' : index === phaseIndex ? ' is-active' : '';
			return `<span class="sae-review-loading__timeline-step${statusClass}">${escapeHtml(step.label)}</span>`;
		}).join('');
	}

	function formatTemplateModeLabel(mode) {
		return String(mode || '').trim().toLowerCase() === 'custom' ? 'Custom structure' : 'Template';
	}

	function buildTemplateChoiceMarkup({ mode = '', label = '', rationale = '', templateKey = '', showKey = false } = {}) {
		const normalizedLabel = String(label || '').trim();
		const normalizedRationale = String(rationale || '').trim();
		const normalizedKey = String(templateKey || '').trim();
		if (!normalizedLabel && !normalizedRationale && !(showKey && normalizedKey)) {
			return '';
		}
		const badgeLabel = formatTemplateModeLabel(mode);
		const templateKeyMarkup = showKey && normalizedKey
			? `<p class="sae-template-choice__key"><code>${escapeHtml(normalizedKey)}</code></p>`
			: '';
		return `
			<div class="sae-template-choice sae-template-choice--${escapeHtml(String(mode || 'known').trim().toLowerCase() || 'known')}">
				<div class="sae-template-choice__header">
					<span class="sae-template-choice__badge">${escapeHtml(badgeLabel)}</span>
					${normalizedLabel ? `<p class="sae-template-choice__label">${escapeHtml(normalizedLabel)}</p>` : ''}
				</div>
				${normalizedRationale ? `<p class="sae-template-choice__rationale">${escapeHtml(normalizedRationale)}</p>` : ''}
				${templateKeyMarkup}
			</div>
		`;
	}

	function isTokenExpired() {
		return !!state.tokenExpiresAtMs && Date.now() >= state.tokenExpiresAtMs;
	}

	function requiresDestructiveConfirm(plan) {
		if (!plan || !plan.operation) {
			return false;
		}
		if (plan.operation === 'remove') {
			return true;
		}
		if (plan.operation === 'batch' && plan.payload && Array.isArray(plan.payload.operations)) {
			return plan.payload.operations.some((operation) => operation && operation.action === 'remove');
		}
		return false;
	}

	function hasPlanToken(plan) {
		const confirmation = plan && plan.dry_run && plan.dry_run.confirmation ? plan.dry_run.confirmation : null;
		return !!(confirmation && confirmation.token);
	}

	function getPlanEnvelopeId(plan) {
		const id = plan && (plan.plan_id || (plan.agent_plan && plan.agent_plan.id) || '');
		return /^[a-f0-9]{16}$/.test(String(id || '')) ? String(id) : '';
	}

	function getDurablePlanState(plan) {
		return String(plan && plan.plan_state ? plan.plan_state : 'planned').toLowerCase();
	}

	function hasDurableEnvelope(plan) {
		if (!plan || plan.intent === 'ask' || isAuditPlan(plan) || isCreateOutlinePlan(plan)) {
			return false;
		}
		return !!getPlanEnvelopeId(plan);
	}

	function isBundlePlan(plan) {
		return !!(plan && String(plan.payload_type || '') === 'bundle_v1' && Array.isArray(plan.children));
	}

	function notifyWorkQueueSelect(plan) {
		if (!plan || plan.intent === 'ask' || isAuditPlan(plan) || isCreateOutlinePlan(plan) || isBundlePlan(plan)) {
			return;
		}
		if (String(plan.payload_type || '') === 'page_spec_v1' || plan.intent === 'create') {
			return;
		}
		const planId = getPlanEnvelopeId(plan);
		if (!planId) {
			return;
		}
		window.dispatchEvent(new CustomEvent('struo-work-queue-select', { detail: { planId } }));
	}

	function requestWantsBundle(requestText) {
		return /\b(these pages|these posts|allowlisted pages)\b/i.test(String(requestText || ''));
	}

	function getBundlePostIds(requestText) {
		if (!requestWantsBundle(requestText)) {
			return [];
		}
		const posts = state.status && state.status.allowlist && Array.isArray(state.status.allowlist.posts)
			? state.status.allowlist.posts
			: [];
		const ids = [];
		posts.forEach((post) => {
			const postId = Number(post && post.post_id ? post.post_id : 0);
			if (postId > 0 && ids.indexOf(postId) === -1) {
				ids.push(postId);
			}
		});
		return ids.slice(0, 12);
	}

	function selectedBundleChildIds(plan) {
		const children = Array.isArray(plan && plan.children) ? plan.children : [];
		return children
			.filter((child) => child && child.selected !== false && /^[a-f0-9]{16}$/.test(String(child.plan_id || '')))
			.map((child) => String(child.plan_id));
	}

	function updateBundleActionState(plan) {
		const durableState = getDurablePlanState(plan);
		const selected = selectedBundleChildIds(plan);
		const children = Array.isArray(plan && plan.children) ? plan.children : [];
		const selectBusy = !!state.bundleSelectBusy || !!state.bundleSelectQueued;
		if (els.bundleApprove) {
			els.bundleApprove.disabled = durableState !== 'planned' || selected.length === 0 || selectBusy || !canApprovePlans();
		}
		if (els.bundleApplyRemaining) {
			els.bundleApplyRemaining.disabled = durableState !== 'approved' || selected.length === 0 || !canApplyPlans();
		}
		if (els.bundleDismiss) {
			els.bundleDismiss.disabled = durableState === 'applied' || durableState === 'cancelled';
		}
		if (els.bundleRows) {
			els.bundleRows.querySelectorAll('[data-bundle-apply]').forEach((button) => {
				const childId = String(button.getAttribute('data-bundle-apply') || '');
				const child = children.find((row) => row && String(row.plan_id || '') === childId);
				const applied = String((child && child.state) || '') === 'applied';
				const rowSelected = !!(child && child.selected !== false);
				button.disabled = applied || durableState !== 'approved' || !rowSelected || !canApplyPlans();
			});
			els.bundleRows.querySelectorAll('[data-bundle-select]').forEach((input) => {
				input.disabled = durableState !== 'planned' || selectBusy;
			});
		}
	}

	async function selectBundleChildren(planId, selectedIds) {
		const endpoint = agentPlansEndpoint(planId, 'select');
		if (!endpoint) {
			throw new Error('Missing bundle id.');
		}
		const revision = Number(state.plan && Number.isFinite(Number(state.plan.select_revision))
			? state.plan.select_revision
			: 0);
		return requestJson(endpoint, {
			method: 'POST',
			body: {
				selected_ids: selectedIds,
				select_revision: revision,
			},
		});
	}

	function applyBundleSelectResponse(response) {
		if (response && response.plan && isBundlePlan(response.plan)) {
			state.plan = response.plan;
			return;
		}
		if (!state.plan || !Array.isArray(state.plan.children) || !response) {
			return;
		}
		const selected = Array.isArray(response.selected_ids) ? response.selected_ids.map(String) : [];
		state.plan.children.forEach((child) => {
			if (child) {
				child.selected = selected.includes(String(child.plan_id || ''));
			}
		});
		if (Number.isFinite(Number(response.select_revision))) {
			state.plan.select_revision = Number(response.select_revision);
		}
	}

	async function flushBundleSelect(planId) {
		if (state.bundleSelectFlushing) {
			return;
		}
		state.bundleSelectFlushing = true;
		state.bundleSelectBusy = true;
		updateBundleActionState(state.plan);
		try {
			while (state.bundleSelectQueued) {
				const selectedIds = state.bundleSelectQueued;
				state.bundleSelectQueued = null;
				const response = await selectBundleChildren(planId, selectedIds);
				applyBundleSelectResponse(response);
			}
		} catch (error) {
			if (error && error.code === 'sae_bundle_select_conflict') {
				const serverPlan = error.payload && error.payload.data ? error.payload.data.plan : null;
				if (serverPlan && isBundlePlan(serverPlan)) {
					state.plan = serverPlan;
					state.bundleSelectQueued = null;
				}
			}
			throw error;
		} finally {
			state.bundleSelectFlushing = false;
			state.bundleSelectBusy = false;
			if (isBundlePlan(state.plan)) {
				renderBundleReview(state.plan, isSimpleMode());
			}
		}
	}

	function snapshotBundleSelection(plan) {
		const children = Array.isArray(plan && plan.children) ? plan.children : [];
		return children.map((child) => ({
			plan_id: String(child && child.plan_id ? child.plan_id : ''),
			selected: !!(child && child.selected !== false),
		}));
	}

	function restoreBundleSelection(plan, snapshot) {
		if (!plan || !Array.isArray(plan.children) || !Array.isArray(snapshot)) {
			return;
		}
		const selected = {};
		snapshot.forEach((row) => {
			if (row && row.plan_id) {
				selected[String(row.plan_id)] = !!row.selected;
			}
		});
		plan.children.forEach((child) => {
			if (!child) {
				return;
			}
			const id = String(child.plan_id || '');
			if (Object.prototype.hasOwnProperty.call(selected, id)) {
				child.selected = selected[id];
			}
		});
	}

	async function handleBundleSelectChange(event) {
		const input = event.target;
		if (!input || !input.matches || !input.matches('[data-bundle-select]')) {
			return;
		}
		if (!isBundlePlan(state.plan)) {
			return;
		}
		if (getDurablePlanState(state.plan) !== 'planned') {
			input.checked = !input.checked;
			setApplyStatus('Selection is locked after approve.', 'error');
			return;
		}
		const planId = getPlanEnvelopeId(state.plan);
		const childId = String(input.getAttribute('data-bundle-select') || '');
		const children = Array.isArray(state.plan.children) ? state.plan.children : [];
		const revertSnapshot = snapshotBundleSelection(state.plan);
		children.forEach((child) => {
			if (child && String(child.plan_id || '') === childId) {
				child.selected = !!input.checked;
			}
		});
		state.bundleSelectQueued = selectedBundleChildIds(state.plan);
		updateBundleActionState(state.plan);
		try {
			await flushBundleSelect(planId);
			setApplyStatus('Selected pages updated. Agents cannot apply.', 'info');
		} catch (error) {
			if (!(error && error.code === 'sae_bundle_select_conflict')) {
				restoreBundleSelection(state.plan, revertSnapshot);
				input.checked = revertSnapshot.some((row) => row.plan_id === childId && row.selected);
				if (isBundlePlan(state.plan)) {
					renderBundleReview(state.plan, isSimpleMode());
				}
			}
			setApplyStatus(getFriendlyErrorMessage(error, 'Could not update selected pages.'), 'error');
		}
	}

	async function handleBundleApprove() {
		if (!isBundlePlan(state.plan)) {
			return;
		}
		const planId = getPlanEnvelopeId(state.plan);
		try {
			if (state.bundleSelectQueued || state.bundleSelectBusy) {
				await flushBundleSelect(planId);
			}
			await approveDurablePlan(planId);
			setApplyStatus('Selected pages approved. Apply this page or apply remaining.', 'info');
		} catch (error) {
			setApplyStatus(getFriendlyErrorMessage(error, 'Could not approve the selected pages.'), 'error');
		}
	}

	async function handleBundleApplyThis(planId) {
		try {
			await applyDurablePlan(planId);
			if (state.plan && Array.isArray(state.plan.children)) {
				state.plan.children.forEach((child) => {
					if (child && String(child.plan_id || '') === planId) {
						child.state = 'applied';
					}
				});
			}
			renderBundleReview(state.plan, isSimpleMode());
			setApplyStatus('Saved to WordPress. Other selected pages are unchanged.', 'info');
			const appliedChild = state.plan && Array.isArray(state.plan.children)
				? state.plan.children.find((child) => child && String(child.plan_id || '') === planId)
				: null;
			notifyMutationApplied(Number((appliedChild && appliedChild.post_id) || 0));
		} catch (error) {
			setApplyStatus(getFriendlyErrorMessage(error, 'Could not apply that page.'), 'error');
		}
	}

	function summarizeBundleApplyResults(results) {
		const rows = Array.isArray(results) ? results : [];
		const applied = rows.filter((row) => row && row.outcome === 'newly_applied').length;
		const failed = rows.filter((row) => row && !row.ok);
		return { applied, failed, rows };
	}

	function applyBundleChildResults(results) {
		if (!state.plan || !Array.isArray(state.plan.children)) {
			return;
		}
		const rows = Array.isArray(results) ? results : [];
		state.plan.children.forEach((child) => {
			const childId = String((child && child.plan_id) || '');
			const row = rows.find((item) => item && String(item.plan_id || '') === childId);
			if (!row) {
				return;
			}
			if (row.ok) {
				child.state = String(row.state || 'applied');
			} else if (row.state) {
				child.state = String(row.state);
			}
		});
	}

	async function handleBundleApplyRemaining() {
		if (!isBundlePlan(state.plan)) {
			return;
		}
		const planId = getPlanEnvelopeId(state.plan);
		const endpoint = agentPlansEndpoint(planId, 'apply-remaining');
		if (!endpoint) {
			setApplyStatus('Missing bundle id.', 'error');
			return;
		}
		if (!canApplyPlans()) {
			setApplyStatus('You do not have permission to apply plans.', 'error');
			return;
		}
		setApplyStatus('Applying remaining pages…', 'loading');
		try {
			const response = await requestJson(endpoint, { method: 'POST', body: {} });
			if (response && response.plan && isBundlePlan(response.plan)) {
				state.plan = response.plan;
			} else {
				applyBundleChildResults(response && response.results);
			}
			renderBundleReview(state.plan, isSimpleMode());
			const summary = summarizeBundleApplyResults(response && response.results);
			if ((response && response.ok === false) || summary.failed.length) {
				setApplyStatus(
					`Applied ${summary.applied} page${summary.applied === 1 ? '' : 's'}. ${summary.failed.length} failed.`,
					'error'
				);
				return;
			}
			setApplyStatus(summary.applied ? `Applied ${summary.applied} page${summary.applied === 1 ? '' : 's'}.` : 'No remaining approved pages to apply.', 'info');
			notifyBundleApplyResults(response && response.results);
			await loadStatus();
		} catch (error) {
			const results = error && error.payload && error.payload.data && Array.isArray(error.payload.data.results)
				? error.payload.data.results
				: [];
			applyBundleChildResults(results);
			renderBundleReview(state.plan, isSimpleMode());
			const summary = summarizeBundleApplyResults(results);
			if (summary.applied) {
				notifyBundleApplyResults(results);
				setApplyStatus(
					`Stopped after applying ${summary.applied} page${summary.applied === 1 ? '' : 's'}. ${getFriendlyErrorMessage(error, 'Could not apply remaining pages.')}`,
					'error'
				);
				return;
			}
			setApplyStatus(getFriendlyErrorMessage(error, 'Could not apply remaining pages.'), 'error');
		}
	}

	async function handleBundleDismiss() {
		if (!isBundlePlan(state.plan)) {
			return;
		}
		await dismissAgentPlan(getPlanEnvelopeId(state.plan));
	}

	function hideBundleReview() {
		if (els.bundleWrap) {
			els.bundleWrap.hidden = true;
		}
		if (els.bundleRows) {
			els.bundleRows.innerHTML = '';
		}
		if (els.bundleMeta) {
			els.bundleMeta.textContent = '';
		}
		if (els.bundleSkipped) {
			els.bundleSkipped.innerHTML = '';
			els.bundleSkipped.hidden = true;
		}
	}

	function renderBundleReview(plan, simpleModeActive) {
		const children = Array.isArray(plan.children) ? plan.children : [];
		const selected = children.filter((child) => child && child.selected !== false);
		if (els.reviewTitle) {
			els.reviewTitle.textContent = 'Review pages';
		}
		if (els.reviewSubtitle) {
			els.reviewSubtitle.textContent = 'Same change, several allowlisted pages. Uncheck any row before apply.';
		}
		if (els.simpleSummary) {
			els.simpleSummary.hidden = !simpleModeActive;
		}
		if (simpleModeActive && els.simpleSummaryHeadline) {
			els.simpleSummaryHeadline.textContent = String(plan.request || 'One change across allowlisted pages.');
		}
		if (simpleModeActive && els.simpleSummaryDetails) {
			els.simpleSummaryDetails.textContent = `${children.length} pages proposed · ${selected.length} selected · agents cannot apply`;
		}
		if (els.askWrap) {
			els.askWrap.hidden = true;
		}
		if (els.planDiffWrap) {
			els.planDiffWrap.hidden = true;
		}
		if (els.planSummary) {
			els.planSummary.hidden = simpleModeActive;
		}
		if (els.planActions) {
			els.planActions.hidden = true;
		}
		if (els.bundleWrap) {
			els.bundleWrap.hidden = false;
		}
		if (els.bundleMeta) {
			els.bundleMeta.textContent = `${children.length} pages proposed from the allowlist · ${selected.length} selected · agents cannot apply`;
		}
		const durableState = getDurablePlanState(plan);
		const selectionLocked = durableState !== 'planned';
		if (els.bundleRows) {
			els.bundleRows.innerHTML = children.map((child) => {
				const title = String((child && child.post_title) || `Post ${child && child.post_id ? child.post_id : ''}`).trim() || 'Allowlisted page';
				const field = String((child && child.field) || 'content');
				const before = String((child && child.before) || '');
				const after = String((child && child.after) || '');
				const childId = String((child && child.plan_id) || '');
				const checked = child && child.selected === false ? '' : ' checked';
				const applied = String((child && child.state) || '') === 'applied';
				const disabled = selectionLocked ? ' disabled' : '';
				return `<tr>
					<td><label class="sae-bundle-include"><input type="checkbox" data-bundle-select="${escapeHtml(childId)}"${checked}${disabled} /> <span class="screen-reader-text">${escapeHtml(title)}</span></label></td>
					<td>${escapeHtml(title)}</td>
					<td>${escapeHtml(field)}</td>
					<td><span class="sae-bundle-before">${escapeHtml(before)}</span>${after ? `<br /><span class="sae-bundle-after">${escapeHtml(after)}</span>` : ''}</td>
					<td><button type="button" class="button button-secondary" data-bundle-apply="${escapeHtml(childId)}" ${applied ? 'disabled' : ''}>${applied ? 'Applied' : 'Apply this'}</button></td>
				</tr>`;
			}).join('');
		}
		const skipped = Array.isArray(plan.skipped) ? plan.skipped : [];
		if (els.bundleSkipped) {
			if (!skipped.length) {
				els.bundleSkipped.innerHTML = '';
				els.bundleSkipped.hidden = true;
			} else {
				els.bundleSkipped.innerHTML = skipped.map((row) => {
					const postId = row && row.post_id ? Number(row.post_id) : 0;
					const reason = String((row && (row.message || row.code)) || 'Skipped');
					const label = postId ? `Post ${postId}` : 'Page';
					return `<li><strong>${escapeHtml(label)}</strong> — ${escapeHtml(reason)}</li>`;
				}).join('');
				els.bundleSkipped.hidden = false;
			}
		}
		if (els.planPayloadWrap) {
			els.planPayloadWrap.hidden = simpleModeActive;
		}
		if (els.planPayload && !simpleModeActive) {
			els.planPayload.textContent = formatJson(plan);
		}
		if (els.dryRunWrap) {
			els.dryRunWrap.hidden = true;
		}
		updateBundleActionState(plan);
		updateApplyButtonState();
	}

	function hasPlanPreview(plan) {
		const dryRun = plan && plan.dry_run ? plan.dry_run : null;
		return !!(dryRun && (dryRun.change || dryRun.diff || dryRun.operations || dryRun.fields));
	}

	function canApprovePlans() {
		return !!(state.status && state.status.permissions && state.status.permissions.struo_approve);
	}

	function canApplyPlans() {
		return !!(state.status && state.status.permissions && state.status.permissions.struo_apply);
	}

	function isDurablePlanActionReady(plan) {
		if (!hasDurableEnvelope(plan)) {
			return false;
		}
		const planState = getDurablePlanState(plan);
		if ('planned' === planState) {
			return canApprovePlans();
		}
		if ('approved' === planState) {
			return canApplyPlans() && (isSimpleMode() ? true : isDestructivePhraseValid());
		}
		return false;
	}

	function isDestructivePhraseValid() {
		if (!requiresDestructiveConfirm(state.plan)) {
			return true;
		}
		if (isSimpleMode()) {
			return true;
		}
		const value = (els.removeConfirmInput.value || '').trim().toUpperCase();
		return value === REMOVE_CONFIRM_PHRASE;
	}

	function updateApplyButtonState() {
		const durableReady = state.plan && hasDurableEnvelope(state.plan) && isDurablePlanActionReady(state.plan);
		const ready = !state.planRequestActive && durableReady;
		els.apply.disabled = !ready;
		updateActionButtonHints();
	}

	function updateActionButtonHints() {
		if (els.generatePlan) {
			const requestText = els.request ? String(els.request.value || '').trim() : '';
			let generateHint = '';
			if (state.planRequestActive) {
				generateHint = isSimpleMode() ? 'Cancel the current request.' : 'Cancel the current planning request.';
			} else if (state.batchMode) {
				generateHint = 'Build a preview for the batch operations below.';
			} else if (!requestText) {
				generateHint = isSimpleMode()
					? 'Describe a change or ask a question first.'
					: 'Enter a request to generate a plan.';
			} else {
				generateHint = getGenerateButtonLabel();
			}
			els.generatePlan.title = generateHint;
		}

		if (els.apply) {
			let applyHint = '';
			if (!state.plan) {
				applyHint = 'Generate a plan before applying changes.';
			} else if (hasDurableEnvelope(state.plan)) {
				const planState = getDurablePlanState(state.plan);
				if ('planned' === planState && !canApprovePlans()) {
					applyHint = 'You need struo_approve to approve this plan.';
				} else if ('approved' === planState && !canApplyPlans()) {
					applyHint = 'You need struo_apply to apply this plan.';
				} else if ('planned' === planState) {
					applyHint = isSimpleMode() ? 'Approve this plan before applying.' : 'Approve the durable plan envelope before apply.';
				} else if ('approved' === planState && !isSimpleMode() && !isDestructivePhraseValid()) {
					applyHint = `Type "${REMOVE_CONFIRM_PHRASE}" to enable this apply.`;
				} else if ('approved' === planState) {
					applyHint = state.applyLabelBase || 'Apply this approved plan.';
				} else {
					applyHint = 'This plan is not ready to apply.';
				}
			} else {
				applyHint = state.plan.intent === 'ask'
					? 'Ask answers need a follow-up plan before anything can be applied.'
					: 'This preview is not ready to apply yet.';
			}
			els.apply.title = applyHint;
		}
		updateMissionBriefFlow();
	}

	function clearReceipt() {
		clearProgressiveRenderTimers();
		state.receiptPayload = null;
		els.receipt.className = 'sae-receipt sae-fade';
		els.receipt.innerHTML = '';
		setFadeVisibility(els.receipt, false);
		updateConsoleUiStage();
	}

	function toSafeHttpUrl(value) {
		const raw = String(value || '').trim();
		if (!raw) {
			return '';
		}
		try {
			const candidate = new URL(raw, window.location.origin);
			return /^(http|https):$/i.test(candidate.protocol) ? candidate.toString() : '';
		} catch (_error) {
			return '';
		}
	}

	function buildPostEditUrl(postId) {
		const numericId = Number(postId);
		if (!Number.isInteger(numericId) || numericId <= 0) {
			return '';
		}
		const base = typeof adminConfig.post_edit_base === 'string' && adminConfig.post_edit_base
			? adminConfig.post_edit_base
			: `${window.location.origin}/wp-admin/post.php`;
		try {
			const url = new URL(base, window.location.origin);
			url.searchParams.set('post', String(numericId));
			url.searchParams.set('action', 'edit');
			return toSafeHttpUrl(url.toString());
		} catch (_error) {
			return '';
		}
	}

	function buildSimpleReceiptMeta(details) {
		const list = Array.isArray(details) ? details : [];
		const summaryPairs = list
			.filter((item) => item && item.label && item.value && String(item.label).toLowerCase() !== 'page')
			.map((item) => `${item.label}: ${item.value}`);
		return summaryPairs.join(' · ');
	}

	function formatConfidenceLabel(level) {
		const normalized = String(level || '').trim().toLowerCase();
		if (normalized === 'high') {
			return 'High confidence';
		}
		if (normalized === 'low') {
			return 'Low confidence';
		}
		return 'Medium confidence';
	}

	function mapToneToStatusState(tone = 'info') {
		const normalized = String(tone || '').trim().toLowerCase();
		if (normalized === 'loading' || normalized === 'working' || normalized === 'active') {
			return 'working';
		}
		if (normalized === 'success' || normalized === 'applied') {
			return 'applied';
		}
		if (normalized === 'ready') {
			return 'ready';
		}
		if (normalized === 'warn' || normalized === 'warning') {
			return 'warn';
		}
		if (normalized === 'error' || normalized === 'danger') {
			return 'error';
		}
		return 'neutral';
	}

	function buildStatusDotMarkup(tone = 'info', label = '') {
		const stateClass = mapToneToStatusState(tone);
		const text = String(label || '').trim();
		return `
			<span class="sae-status-dot sae-status-dot--${escapeHtml(stateClass)}" aria-hidden="true"></span>
			${text ? `<span>${escapeHtml(text)}</span>` : ''}
		`;
	}

	function setStatusMessage(element, message, kind = 'info') {
		if (!element) {
			return;
		}
		const tone = kind || 'info';
		const text = String(message || '').trim();
		element.classList.remove('is-info', 'is-warn', 'is-error', 'is-loading', 'is-success');
		element.classList.add(`is-${tone}`);
		if (!text) {
			element.innerHTML = '';
			return;
		}
		element.innerHTML = buildStatusDotMarkup(tone, text);
	}

	function getPlanFieldChangeCount(plan, previewRows = []) {
		if (!plan || typeof plan !== 'object') {
			return 0;
		}
		if (Array.isArray(previewRows) && previewRows.length) {
			return previewRows.length;
		}
		if (plan.operation === 'cross_field' && plan.payload && plan.payload.field) {
			return 1;
		}
		if (plan.operation === 'update' && plan.payload && plan.payload.fields && typeof plan.payload.fields === 'object') {
			return Object.keys(plan.payload.fields).length;
		}
		if (plan.operation === 'batch' && plan.payload && Array.isArray(plan.payload.operations)) {
			return plan.payload.operations.length;
		}
		return 0;
	}

	function cloneJsonLike(value) {
		return JSON.parse(JSON.stringify(value));
	}

	function countResolvedPreviewTargets(previewRows = []) {
		if (!Array.isArray(previewRows) || !previewRows.length) {
			return 0;
		}
		return new Set(
			previewRows
				.map((item) => String(item && item.targetKey ? item.targetKey : '').trim())
				.filter(Boolean)
		).size;
	}

	function buildPlanCompletionSummary(plan, previewRows = [], postTitle = '') {
		const pageLabel = String(postTitle || 'this page').trim() || 'this page';
		const fieldCount = Array.isArray(previewRows) ? previewRows.length : 0;
		if (!plan || typeof plan !== 'object') {
			return `Changes applied on ${pageLabel}.`;
		}
		if (plan.operation === 'batch' && fieldCount) {
			const targetCount = countResolvedPreviewTargets(previewRows) || Math.max(1, getPlanFieldChangeCount(plan, previewRows));
			return `${fieldCount} field change${fieldCount === 1 ? '' : 's'} applied across ${targetCount} block${targetCount === 1 ? '' : 's'} on ${pageLabel}.`;
		}
		if ((plan.operation === 'update' || plan.operation === 'cross_field') && fieldCount) {
			return `${fieldCount} field${fieldCount === 1 ? '' : 's'} updated on ${pageLabel}.`;
		}
		if (plan.operation === 'insert') {
			return `1 block inserted on ${pageLabel}.`;
		}
		if (plan.operation === 'remove') {
			return `1 block removed on ${pageLabel}.`;
		}
		return `Changes applied on ${pageLabel}.`;
	}

	function buildPlanTransparency(plan, previewRows = []) {
		if (!plan || typeof plan !== 'object') {
			return {
				confidence: { level: 'medium', label: formatConfidenceLabel('medium') },
				scopeLine: '',
			};
		}

		const planner = plan.planner && typeof plan.planner === 'object' ? plan.planner : {};
		const rewriteMeta = planner.rewrite && typeof planner.rewrite === 'object' ? planner.rewrite : null;
		const warningCount = Array.isArray(planner.warnings) ? planner.warnings.filter(Boolean).length : 0;
		const post = plan.post && typeof plan.post === 'object' ? plan.post : {};
		const pageLabel = String(post.post_title || post.post_slug || (post.post_id ? `post ${post.post_id}` : 'this page')).trim() || 'this page';
		const fieldChangeCount = getPlanFieldChangeCount(plan, previewRows);
		const hasKnownBeforeState = Array.isArray(previewRows)
			? previewRows.some((item) => item && item.beforeText && item.beforeText !== '(current value)')
			: false;
		const hasExplicitField = !!(
			(plan.payload && typeof plan.payload === 'object' && plan.payload.field)
			|| (plan.payload && typeof plan.payload === 'object' && plan.payload.fields && typeof plan.payload.fields === 'object' && Object.keys(plan.payload.fields).length === 1)
			|| (rewriteMeta && rewriteMeta.field_name && !rewriteMeta.field_inferred)
		);

		let confidenceLevel = 'medium';
		let scopeLine = '';

		if (plan.intent === 'ask') {
			confidenceLevel = 'medium';
			scopeLine = 'Suggestion only. Nothing will change until you open a specific plan.';
		} else if (isAuditPlan(plan)) {
			confidenceLevel = 'medium';
			scopeLine = `Analysis only for ${pageLabel}. Nothing is changed until you review a fix.`;
		} else if (plan.intent === 'create') {
			confidenceLevel = warningCount ? 'low' : 'medium';
			scopeLine = 'Creates a new draft only. Existing pages and posts will not be modified.';
		} else if (plan.operation === 'batch') {
			confidenceLevel = warningCount ? 'low' : 'medium';
			scopeLine = `Applies ${fieldChangeCount || 0} planned change${fieldChangeCount === 1 ? '' : 's'} on ${pageLabel}. No other pages are touched.`;
		} else if (plan.operation === 'insert') {
			confidenceLevel = warningCount ? 'low' : 'medium';
			scopeLine = `Inserts 1 block on ${pageLabel}. No other sections or pages are touched.`;
		} else if (plan.operation === 'remove') {
			confidenceLevel = warningCount ? 'low' : 'medium';
			scopeLine = `Removes 1 block on ${pageLabel}. No other sections or pages are touched.`;
		} else if (plan.operation === 'cross_field' || plan.operation === 'update') {
			if (!warningCount && hasExplicitField && (hasKnownBeforeState || plan.operation === 'cross_field')) {
				confidenceLevel = 'high';
			} else if (warningCount || (rewriteMeta && rewriteMeta.field_inferred)) {
				confidenceLevel = 'low';
			}
			const count = Math.max(1, fieldChangeCount || 1);
			scopeLine = count === 1
				? `Changes 1 field on ${pageLabel}. No other fields or pages are touched.`
				: `Changes ${count} fields on ${pageLabel}. No other pages are touched.`;
		} else {
			confidenceLevel = warningCount ? 'low' : 'medium';
			scopeLine = `Targets ${pageLabel}. No other pages are touched.`;
		}

		return {
			confidence: {
				level: confidenceLevel,
				label: formatConfidenceLabel(confidenceLevel),
			},
			scopeLine,
		};
	}

	function buildUndoContextFromPlan(plan, previewRows = []) {
		if (!plan || typeof plan !== 'object' || !plan.endpoint) {
			return null;
		}

		const post = plan.post && typeof plan.post === 'object' ? plan.post : {};
		const postId = Number(post.post_id || 0);
		const pageLabel = String(post.post_title || post.post_slug || (postId ? `post ${postId}` : 'this page')).trim() || 'this page';

		if (plan.operation === 'cross_field') {
			const payload = plan.payload && typeof plan.payload === 'object' ? plan.payload : {};
			const dryRun = plan.dry_run && typeof plan.dry_run === 'object' ? plan.dry_run : {};
			const field = String(payload.field || '').trim();
			const previousValue = Object.prototype.hasOwnProperty.call(dryRun, 'old_value')
				? dryRun.old_value
				: Object.prototype.hasOwnProperty.call(payload, 'current_value')
					? payload.current_value
					: null;
			if (!field || previousValue === null || typeof previousValue === 'undefined') {
				return null;
			}
			return {
				request: `Undo the last field change on ${pageLabel}.`,
				endpoint: plan.endpoint,
				operation: 'cross_field',
				post: cloneJsonLike(post),
				payload: {
					field,
					value: previousValue,
				},
			};
		}

		if (plan.operation !== 'update') {
			return null;
		}

		const payload = plan.payload && typeof plan.payload === 'object' ? plan.payload : {};
		const fields = payload.fields && typeof payload.fields === 'object' ? payload.fields : null;
		const target = payload.target && typeof payload.target === 'object' ? payload.target : null;
		if (!fields || !target) {
			return null;
		}

		const inverseFields = {};
		Object.keys(fields).forEach((fieldName) => {
			const row = Array.isArray(previewRows)
				? previewRows.find((item) => item && item.fieldName === fieldName)
				: null;
			if (!row || !row.hasKnownBeforeValue) {
				return;
			}
			inverseFields[fieldName] = row.rawBeforeValue;
		});

		const fieldNames = Object.keys(fields);
		const inverseFieldNames = Object.keys(inverseFields);
		if (!fieldNames.length || inverseFieldNames.length !== fieldNames.length) {
			return null;
		}

		return {
			request: `Undo the last change on ${pageLabel}.`,
			endpoint: plan.endpoint,
			operation: 'update',
			post: cloneJsonLike(post),
			payload: {
				target: cloneJsonLike(target),
				fields: inverseFields,
			},
		};
	}

	async function handleReceiptUndo() {
		const receiptPayload = state.receiptPayload && typeof state.receiptPayload === 'object' ? state.receiptPayload : null;
		const undo = receiptPayload && receiptPayload.undo_context && typeof receiptPayload.undo_context === 'object'
			? receiptPayload.undo_context
			: null;
		if (!undo || !undo.endpoint || !undo.payload) {
			setComposeStatus('Undo is not available for this receipt.', 'warn');
			return;
		}

		clearReceipt();
		resetPlanState();
		setApplyStatus('');
		clearPendingPlanContext();

		const targetLabel = undo.post && (undo.post.post_title || undo.post.post_slug)
			? String(undo.post.post_title || undo.post.post_slug)
			: '';
		state.loadingRequestText = String(undo.request || 'Undo previous change').trim();
		state.loadingTargetLabel = targetLabel;
		setComposeStatus(isSimpleMode() ? 'Building undo preview…' : 'Generating undo preview…', 'loading');
		setReviewLoadingStage('planning', {
			requestText: state.loadingRequestText,
			targetLabel,
		});

		try {
			const responseMode = els.responseMode.value === 'verbose';
			const dryRun = await requestJson(toAbsoluteEndpoint(undo.endpoint), {
				method: 'POST',
				body: Object.assign({}, cloneJsonLike(undo.payload), {
					dry_run: true,
					verbose: responseMode,
					compact: !responseMode,
				}),
			});

			state.plan = {
				request: String(undo.request || 'Undo previous change'),
				planner: {
					source: 'undo_receipt',
					ai_used: false,
					warnings: [],
				},
				post: cloneJsonLike(undo.post || {}),
				operation: String(undo.operation || 'update'),
				endpoint: String(undo.endpoint),
				payload: cloneJsonLike(undo.payload),
				dry_run: dryRun,
			};

			renderPlan();
			flashComposerStageChipDone('Done');
			if (els.planContent && !els.planContent.hidden) {
				els.planContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			setComposeStatus(isSimpleMode() ? 'Undo preview ready. Review before applying.' : 'Undo preview generated. Review and apply to revert.', 'info');
		} catch (error) {
			setComposeStatus(getFriendlyErrorMessage(error, 'Could not build an undo preview.'), 'error');
			renderPlan();
		} finally {
			setReviewLoadingStage('');
			state.loadingRequestText = '';
			state.loadingTargetLabel = '';
		}
	}

	async function handleReceiptSaveTemplate() {
		const receiptPayload = state.receiptPayload && typeof state.receiptPayload === 'object'
			? state.receiptPayload
			: null;
		const isCreateReceipt = receiptPayload && receiptPayload.receipt_context === 'create';
		const idempotencyKey = receiptPayload && receiptPayload.idempotency_key
			? String(receiptPayload.idempotency_key).trim()
			: '';
		if (!isCreateReceipt || !idempotencyKey || !rest.save_created_template) {
			setComposeStatus('Save as template is not available for this receipt.', 'warn');
			return;
		}
		if (receiptPayload.saved_template_key) {
			setComposeStatus(`Saved as template: ${String(receiptPayload.saved_template_label || receiptPayload.saved_template_key).trim()}`, 'info');
			return;
		}

		const postTitle = receiptPayload && receiptPayload.post_title
			? String(receiptPayload.post_title).trim()
			: 'New Template';
		const defaultLabel = `${postTitle} Template`;
		const templateLabel = await requestTextEntry({
			title: 'Save this structure as template',
			description: 'Name the reusable template you want to save from this receipt.',
			defaultValue: defaultLabel,
			confirmLabel: 'Save template',
		});
		if (templateLabel === null) {
			return;
		}
		const normalizedLabel = String(templateLabel || '').trim();
		if (!normalizedLabel) {
			setComposeStatus('Template name is required.', 'warn');
			return;
		}

		setComposeStatus('Saving template…', 'loading');
		try {
			const response = await requestJson(rest.save_created_template, {
				method: 'POST',
				body: {
					idempotency_key: idempotencyKey,
					label: normalizedLabel,
					based_on_post_id: receiptPayload && receiptPayload.post_id ? Number(receiptPayload.post_id) : 0,
				},
			});
			const nextReceiptPayload = Object.assign({}, cloneJsonLike(receiptPayload), {
				saved_template_key: response && response.template_key ? String(response.template_key).trim() : '',
				saved_template_label: response && response.template_label ? String(response.template_label).trim() : normalizedLabel,
			});
			renderReceipt(nextReceiptPayload);
			noteTemplateRegistryChanged();
			setComposeStatus(`Saved template "${nextReceiptPayload.saved_template_label}".`, 'success');
		} catch (error) {
			setComposeStatus(getFriendlyErrorMessage(error, 'Could not save this template.'), 'error');
		}
	}

	async function promoteSelectedPageToTemplate() {
		const context = getSelectedPostContext();
		if (!context.postId || !context.post) {
			setComposeStatus('Select a page first to promote it into a template.', 'warn');
			return;
		}
		if (!rest.promote_page_template || !rest.templates) {
			setComposeStatus('Template registry endpoints are unavailable in this environment.', 'error');
			return;
		}

		const defaultLabel = `${context.title || (context.postType === 'post' ? 'Blog Post' : 'Page')} Template`;
		const templateLabel = await requestTextEntry({
			title: 'Promote this page to template',
			description: `Save ${context.title || 'the selected page'} as a reusable template.`,
			defaultValue: defaultLabel,
			confirmLabel: 'Promote template',
		});
		if (templateLabel === null) {
			return;
		}
		const normalizedLabel = String(templateLabel || '').trim();
		if (!normalizedLabel) {
			setComposeStatus('Template name is required.', 'warn');
			return;
		}

		setComposeStatus(`Promoting "${context.title || 'Selected page'}" into a template…`, 'loading');
		try {
			const preview = await requestJson(rest.promote_page_template, {
				method: 'POST',
				body: {
					post_id: Number(context.postId),
					label: normalizedLabel,
					status: 'approved',
				},
			});
			const previewTemplate = preview && preview.template && typeof preview.template === 'object'
				? preview.template
				: null;
			if (!previewTemplate) {
				throw { code: 'sae_template_preview_missing', message: 'Template preview was incomplete.' };
			}

			const saveResponse = await requestJson(rest.templates, {
				method: 'POST',
				body: {
					template: previewTemplate,
				},
			});
			const savedTemplate = saveResponse && saveResponse.template && typeof saveResponse.template === 'object'
				? saveResponse.template
				: previewTemplate;
			const savedLabel = String(savedTemplate.label || normalizedLabel).trim() || normalizedLabel;
			const sectionCount = Array.isArray(savedTemplate.structure && savedTemplate.structure.blocks)
				? savedTemplate.structure.blocks.length
				: 0;
			const warnings = Array.isArray(preview && preview.warnings) ? preview.warnings.filter(Boolean) : [];
			const warningMessage = warnings.length
				? ` Skipped ${warnings.length} unsupported ${warnings.length === 1 ? 'block' : 'blocks'}.`
				: '';
			noteTemplateRegistryChanged();
			setComposeStatus(
				`Saved template "${savedLabel}" with ${sectionCount} ${sectionCount === 1 ? 'section' : 'sections'}.${warningMessage}`,
				'success'
			);
		} catch (error) {
			setComposeStatus(getFriendlyErrorMessage(error, 'Could not promote this page to a template.'), 'error');
		}
	}

	function ensurePatternStudioModal() {
		if (state.patternStudioModal) {
			return state.patternStudioModal;
		}

		const overlay = document.createElement('div');
		overlay.className = 'sae-modal sae-pattern-studio';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-modal__dialog sae-pattern-studio__dialog" role="dialog" aria-modal="true" aria-labelledby="sae-pattern-studio-title">
				<div class="sae-pattern-studio__header">
					<div>
						<p class="sae-pattern-studio__eyebrow">Pattern studio</p>
						<h3 id="sae-pattern-studio-title">Promote section to pattern</h3>
						<p class="sae-modal__description sae-pattern-studio__subtitle"></p>
					</div>
					<button type="button" class="button button-secondary" data-pattern-studio-action="close">Close</button>
				</div>
				<div class="sae-pattern-studio__layout">
					<section class="sae-pattern-studio__panel">
						<div class="sae-pattern-studio__panel-header">
							<h4>Promotable sections</h4>
							<button type="button" class="button button-secondary" data-pattern-studio-action="refresh">Refresh blocks</button>
						</div>
						<p class="sae-pattern-studio__status"></p>
						<div class="sae-pattern-studio__list" role="listbox" aria-label="Promotable sections" tabindex="-1"></div>
					</section>
					<section class="sae-pattern-studio__panel">
						<div class="sae-pattern-studio__fields">
							<label class="sae-field">
								<span>Pattern name</span>
								<input type="text" class="sae-modal__input" data-pattern-studio-field="label" />
							</label>
							<label class="sae-field">
								<span>Description</span>
								<textarea rows="3" class="sae-modal__input" data-pattern-studio-field="description"></textarea>
							</label>
							<label class="sae-field">
								<span>Status</span>
								<select class="sae-modal__input" data-pattern-studio-field="status">
									<option value="draft">Draft</option>
									<option value="approved">Approved</option>
									<option value="hidden">Hidden</option>
								</select>
							</label>
						</div>
						<div class="sae-pattern-preview">
							<div class="sae-pattern-preview__body"></div>
						</div>
						<div class="sae-modal__actions">
							<button type="button" class="button button-secondary" data-pattern-studio-action="preview">Refresh preview</button>
							<button type="button" class="button button-primary" data-pattern-studio-action="save" disabled>Save pattern</button>
						</div>
					</section>
				</div>
			</div>
		`;
		document.body.appendChild(overlay);

		const modal = {
			overlay,
			subtitle: overlay.querySelector('.sae-pattern-studio__subtitle'),
			status: overlay.querySelector('.sae-pattern-studio__status'),
			list: overlay.querySelector('.sae-pattern-studio__list'),
			previewBody: overlay.querySelector('.sae-pattern-preview__body'),
			labelInput: overlay.querySelector('[data-pattern-studio-field="label"]'),
			descriptionInput: overlay.querySelector('[data-pattern-studio-field="description"]'),
			statusSelect: overlay.querySelector('[data-pattern-studio-field="status"]'),
			saveButton: overlay.querySelector('[data-pattern-studio-action="save"]'),
			previewButton: overlay.querySelector('[data-pattern-studio-action="preview"]'),
			refreshButton: overlay.querySelector('[data-pattern-studio-action="refresh"]'),
			closeButton: overlay.querySelector('[data-pattern-studio-action="close"]'),
			candidates: [],
			selectedBlockIndex: -1,
			selectedPostId: 0,
			selectedPostTitle: '',
			previewPattern: null,
		};

		const close = () => {
			hideModal(overlay);
			modal.previewPattern = null;
			modal.selectedBlockIndex = -1;
			modal.previewBody.innerHTML = '<p class="sae-pattern-preview__empty">Choose a section to preview the reusable pattern.</p>';
			modal.saveButton.disabled = true;
		};

		const syncPreviewFields = (candidate, options = {}) => {
			const force = !!options.force;
			if (!candidate) {
				modal.labelInput.value = '';
				modal.descriptionInput.value = '';
				return;
			}
			const defaultLabel = `${candidate.label} Pattern`;
			if (force || !String(modal.labelInput.value || '').trim()) {
				modal.labelInput.value = defaultLabel;
			}
			if (force || !String(modal.descriptionInput.value || '').trim()) {
				modal.descriptionInput.value = `Promoted from section ${candidate.blockIndex + 1} on "${modal.selectedPostTitle || 'selected page'}".`;
			}
		};

		const focusSelectedCandidate = () => {
			const selectedButton = modal.list.querySelector('.sae-pattern-studio__item.is-selected, .sae-pattern-studio__item');
			if (selectedButton && typeof selectedButton.focus === 'function') {
				selectedButton.focus();
			}
		};

		const renderCandidates = () => {
			if (!modal.candidates.length) {
				modal.status.textContent = 'No promotable top-level sections were found on this page yet.';
				modal.list.innerHTML = '';
				modal.previewPattern = null;
				modal.previewBody.innerHTML = '<p class="sae-pattern-preview__empty">Select a page section with reusable structure to create a pattern.</p>';
				modal.saveButton.disabled = true;
				return;
			}
			modal.status.textContent = `Showing ${modal.candidates.length} promotable section${modal.candidates.length === 1 ? '' : 's'}.`;
			modal.list.innerHTML = modal.candidates.map((candidate) => {
				const selected = candidate.blockIndex === modal.selectedBlockIndex;
				const meta = [];
				meta.push(`Section ${candidate.blockIndex + 1}`);
				if (candidate.fieldNames.length) {
					meta.push(candidate.fieldNames.join(', '));
				}
				return `
					<button type="button" class="sae-pattern-studio__item${selected ? ' is-selected' : ''}" data-pattern-block-index="${candidate.blockIndex}" role="option" aria-selected="${selected ? 'true' : 'false'}">
						<span class="sae-pattern-studio__item-title">${escapeHtml(candidate.label)}</span>
						<span class="sae-pattern-studio__item-meta">${escapeHtml(meta.join(' · '))}</span>
					</button>
				`;
			}).join('');
		};

		const loadCandidates = async (force = false) => {
			if (!modal.selectedPostId) {
				return;
			}
			modal.status.textContent = 'Loading promotable sections…';
			modal.list.innerHTML = '';
			try {
				const blocks = await fetchPostBlocks(modal.selectedPostId, { force });
				modal.candidates = getTopLevelPromotablePatternBlocks(blocks);
				if (!modal.candidates.some((candidate) => candidate.blockIndex === modal.selectedBlockIndex)) {
					modal.selectedBlockIndex = modal.candidates.length ? modal.candidates[0].blockIndex : -1;
				}
				renderCandidates();
				const selectedCandidate = modal.candidates.find((candidate) => candidate.blockIndex === modal.selectedBlockIndex) || null;
				syncPreviewFields(selectedCandidate);
				if (selectedCandidate) {
					await refreshPreview();
				}
				window.requestAnimationFrame(focusSelectedCandidate);
			} catch (error) {
				modal.status.textContent = `Unable to load sections: ${error.message || 'unknown error'}`;
				modal.list.innerHTML = '';
				modal.previewPattern = null;
				modal.saveButton.disabled = true;
			}
		};

		async function refreshPreview() {
			const candidate = modal.candidates.find((entry) => entry.blockIndex === modal.selectedBlockIndex) || null;
			if (!candidate) {
				modal.previewPattern = null;
				modal.previewBody.innerHTML = '<p class="sae-pattern-preview__empty">Choose a section to preview the reusable pattern.</p>';
				modal.saveButton.disabled = true;
				return;
			}
			syncPreviewFields(candidate);
			modal.previewBody.innerHTML = `<p class="sae-pattern-preview__status">${buildStatusDotMarkup('loading', 'Generating preview')}</p>`;
			modal.saveButton.disabled = true;
			try {
				const response = await requestJson(rest.promote_section_pattern, {
					method: 'POST',
					body: {
						post_id: modal.selectedPostId,
						block_index: candidate.blockIndex,
						label: String(modal.labelInput.value || '').trim(),
						description: String(modal.descriptionInput.value || '').trim(),
						status: String(modal.statusSelect.value || 'draft').trim(),
					},
				});
				const previewPattern = response && response.pattern && typeof response.pattern === 'object'
					? response.pattern
					: null;
				if (!previewPattern) {
					throw { code: 'sae_pattern_preview_missing', message: 'Pattern preview was incomplete.' };
				}
				modal.previewPattern = previewPattern;
				modal.previewBody.innerHTML = buildPatternPreviewSummaryMarkup(previewPattern);
				modal.saveButton.disabled = false;
			} catch (error) {
				modal.previewPattern = null;
				modal.previewBody.innerHTML = `<p class="sae-pattern-preview__empty">${escapeHtml(getFriendlyErrorMessage(error, 'Could not preview this pattern.'))}</p>`;
				modal.saveButton.disabled = true;
			}
		}

		const savePattern = async () => {
			if (!modal.previewPattern) {
				return;
			}
			modal.saveButton.disabled = true;
			try {
				const response = await requestJson(rest.patterns, {
					method: 'POST',
					body: {
						pattern: modal.previewPattern,
					},
				});
				const savedPattern = response && response.pattern && typeof response.pattern === 'object'
					? response.pattern
					: modal.previewPattern;
				notePatternRegistryChanged();
				setComposeStatus(`Saved pattern "${String(savedPattern.label || modal.previewPattern.label || 'Pattern').trim()}".`, 'success');
				close();
			} catch (error) {
				setComposeStatus(getFriendlyErrorMessage(error, 'Could not save this pattern.'), 'error');
				modal.saveButton.disabled = false;
			}
		};

		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close();
				return;
			}
			const target = event.target instanceof HTMLElement ? event.target.closest('[data-pattern-studio-action], [data-pattern-block-index]') : null;
			if (!target) {
				return;
			}
			if (target.hasAttribute('data-pattern-block-index')) {
				modal.selectedBlockIndex = Number(target.getAttribute('data-pattern-block-index') || -1);
				modal.previewPattern = null;
				modal.saveButton.disabled = true;
				const selectedCandidate = modal.candidates.find((candidate) => candidate.blockIndex === modal.selectedBlockIndex) || null;
				syncPreviewFields(selectedCandidate, { force: true });
				renderCandidates();
				void refreshPreview();
				window.requestAnimationFrame(focusSelectedCandidate);
				return;
			}
			const action = String(target.getAttribute('data-pattern-studio-action') || '').trim();
			if (action === 'close') {
				close();
				return;
			}
			if (action === 'refresh') {
				void loadCandidates(true);
				return;
			}
			if (action === 'preview') {
				void refreshPreview();
				return;
			}
			if (action === 'save') {
				void savePattern();
			}
		});

		overlay.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				close();
			}
		});

		[modal.labelInput, modal.descriptionInput, modal.statusSelect].forEach((input) => {
			if (!input) {
				return;
			}
			input.addEventListener('input', () => {
				modal.previewPattern = null;
				modal.saveButton.disabled = true;
			});
		});

		state.patternStudioModal = {
			open: async (postId, postTitle = '') => {
				modal.selectedPostId = Number(postId || 0);
				modal.selectedPostTitle = String(postTitle || '').trim();
				modal.candidates = [];
				modal.selectedBlockIndex = -1;
				modal.previewPattern = null;
				modal.subtitle.textContent = modal.selectedPostTitle
					? `Choose a top-level section from "${modal.selectedPostTitle}" and save it as a reusable pattern.`
					: 'Choose a top-level section and save it as a reusable pattern.';
				modal.labelInput.value = '';
				modal.descriptionInput.value = '';
				modal.statusSelect.value = 'draft';
				modal.previewBody.innerHTML = '<p class="sae-pattern-preview__empty">Choose a section to preview the reusable pattern.</p>';
				modal.saveButton.disabled = true;
				showModal(overlay, modal.closeButton);
				await loadCandidates(false);
				window.requestAnimationFrame(focusSelectedCandidate);
			},
			close,
			isOpen: () => !overlay.hidden,
		};

		return state.patternStudioModal;
	}

	function ensurePatternLibraryModal() {
		if (state.patternLibraryModal) {
			return state.patternLibraryModal;
		}

		const overlay = document.createElement('div');
		overlay.className = 'sae-modal sae-pattern-library';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-modal__dialog sae-pattern-library__dialog" role="dialog" aria-modal="true" aria-labelledby="sae-pattern-library-title">
				<div class="sae-pattern-library__header">
					<div>
						<p class="sae-pattern-studio__eyebrow">Pattern library</p>
						<h3 id="sae-pattern-library-title">Manage saved patterns</h3>
						<p class="sae-modal__description">Review saved and built-in patterns, then adjust status or copy for saved items.</p>
					</div>
					<div class="sae-pattern-library__actions">
						<button type="button" class="button button-secondary" data-pattern-library-action="refresh">Refresh</button>
						<button type="button" class="button button-secondary" data-pattern-library-action="close">Close</button>
					</div>
				</div>
				<p class="sae-pattern-library__status"></p>
				<div class="sae-pattern-library__list"></div>
			</div>
		`;
		document.body.appendChild(overlay);

		const modal = {
			overlay,
			status: overlay.querySelector('.sae-pattern-library__status'),
			list: overlay.querySelector('.sae-pattern-library__list'),
			patterns: [],
		};

		const renderPatterns = () => {
			if (!modal.patterns.length) {
				modal.status.textContent = 'No patterns available yet.';
				modal.list.innerHTML = '';
				return;
			}
			modal.status.textContent = `${modal.patterns.length} pattern${modal.patterns.length === 1 ? '' : 's'} available.`;
			modal.list.innerHTML = modal.patterns.map((pattern, index) => {
				const metadata = pattern && pattern.metadata && typeof pattern.metadata === 'object' ? pattern.metadata : {};
				const family = String(metadata.family || '').trim();
				const variant = String(metadata.variant || '').trim();
				const blockName = String(pattern && pattern.structure && pattern.structure.block_name ? pattern.structure.block_name : '').trim();
				const metaLine = [family, variant, blockName ? formatBlockNameForEditors(blockName) : ''].filter(Boolean).join(' · ');
				const isBuiltIn = String(pattern && pattern.source_type ? pattern.source_type : '').trim().toLowerCase() === 'built_in';
				return `
					<article class="sae-pattern-library__item">
						<div class="sae-pattern-library__item-header">
							<div>
								<p class="sae-pattern-library__badge">${escapeHtml(formatPatternSourceLabel(pattern))}</p>
								${metaLine ? `<p class="sae-pattern-library__meta">${escapeHtml(metaLine)}</p>` : ''}
							</div>
							${isBuiltIn ? `<span class="sae-pattern-library__readonly">Read only</span>` : ''}
						</div>
						<label class="sae-field">
							<span>Name</span>
							<input type="text" class="sae-modal__input" data-pattern-library-field="label" data-pattern-index="${index}" value="${escapeHtml(pattern.label || '')}" ${isBuiltIn ? 'disabled' : ''} />
						</label>
						<label class="sae-field">
							<span>Description</span>
							<textarea rows="2" class="sae-modal__input" data-pattern-library-field="description" data-pattern-index="${index}" ${isBuiltIn ? 'disabled' : ''}>${escapeHtml(pattern.description || '')}</textarea>
						</label>
						<div class="sae-pattern-library__item-footer">
							<label class="sae-field sae-pattern-library__status-field">
								<span>Status</span>
								<select class="sae-modal__input" data-pattern-library-field="status" data-pattern-index="${index}" ${isBuiltIn ? 'disabled' : ''}>
									<option value="draft"${String(pattern.status || '') === 'draft' ? ' selected' : ''}>Draft</option>
									<option value="approved"${String(pattern.status || '') === 'approved' ? ' selected' : ''}>Approved</option>
									<option value="hidden"${String(pattern.status || '') === 'hidden' ? ' selected' : ''}>Hidden</option>
								</select>
							</label>
							${isBuiltIn ? '' : `<button type="button" class="button button-primary" data-pattern-library-action="save" data-pattern-index="${index}">Save</button>`}
						</div>
					</article>
				`;
			}).join('');
		};

		const loadPatterns = async (force = false) => {
			modal.status.textContent = 'Loading patterns…';
			modal.list.innerHTML = '';
			const patterns = await refreshPatternRegistryCache({ force, includeHidden: true });
			modal.patterns = Array.isArray(patterns) ? patterns : [];
			renderPatterns();
		};

		const savePatternAtIndex = async (index) => {
			const pattern = modal.patterns[index];
			if (!pattern || String(pattern.source_type || '').trim().toLowerCase() === 'built_in') {
				return;
			}
			const container = modal.list.querySelector(`[data-pattern-index="${index}"]`);
			const item = container instanceof HTMLElement ? container.closest('.sae-pattern-library__item') : null;
			const labelInput = modal.list.querySelector(`[data-pattern-library-field="label"][data-pattern-index="${index}"]`);
			const descriptionInput = modal.list.querySelector(`[data-pattern-library-field="description"][data-pattern-index="${index}"]`);
			const statusInput = modal.list.querySelector(`[data-pattern-library-field="status"][data-pattern-index="${index}"]`);
			const nextPattern = Object.assign({}, cloneJsonLike(pattern), {
				label: String(labelInput && labelInput.value ? labelInput.value : pattern.label || '').trim(),
				description: String(descriptionInput && descriptionInput.value ? descriptionInput.value : pattern.description || '').trim(),
				status: String(statusInput && statusInput.value ? statusInput.value : pattern.status || 'draft').trim(),
			});
			if (item) {
				item.classList.add('is-saving');
			}
			try {
				const response = await requestJson(rest.patterns, {
					method: 'POST',
					body: {
						pattern: nextPattern,
					},
				});
				const savedPattern = response && response.pattern && typeof response.pattern === 'object'
					? response.pattern
					: nextPattern;
				modal.patterns[index] = savedPattern;
				renderPatterns();
				notePatternRegistryChanged();
				setComposeStatus(`Updated pattern "${String(savedPattern.label || nextPattern.label || 'Pattern').trim()}".`, 'success');
			} catch (error) {
				setComposeStatus(getFriendlyErrorMessage(error, 'Could not update this pattern.'), 'error');
			} finally {
				if (item) {
					item.classList.remove('is-saving');
				}
			}
		};

		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				hideModal(overlay);
				return;
			}
			const target = event.target instanceof HTMLElement ? event.target.closest('[data-pattern-library-action]') : null;
			if (!target) {
				return;
			}
			const action = String(target.getAttribute('data-pattern-library-action') || '').trim();
			if (action === 'close') {
				hideModal(overlay);
				return;
			}
			if (action === 'refresh') {
				void loadPatterns(true);
				return;
			}
			if (action === 'save') {
				const index = Number(target.getAttribute('data-pattern-index') || -1);
				if (index >= 0) {
					void savePatternAtIndex(index);
				}
			}
		});

		overlay.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				hideModal(overlay);
			}
		});

		state.patternLibraryModal = {
			open: async () => {
				showModal(overlay);
				await loadPatterns(false);
			},
			close: () => {
				hideModal(overlay);
			},
			isOpen: () => !overlay.hidden,
		};

		return state.patternLibraryModal;
	}

	async function promoteSelectedSectionToPattern() {
		const context = getSelectedPostContext();
		if (!context.postId || !context.post) {
			setComposeStatus('Select a page first to promote one of its sections into a pattern.', 'warn');
			return;
		}
		if (!rest.promote_section_pattern || !rest.patterns) {
			setComposeStatus('Pattern registry endpoints are unavailable in this environment.', 'error');
			return;
		}

		const studio = ensurePatternStudioModal();
		await studio.open(context.postId, context.title || '');
	}

	async function openPatternLibrary() {
		if (!rest.patterns) {
			setComposeStatus('Pattern registry endpoint is unavailable in this environment.', 'error');
			return;
		}
		const library = ensurePatternLibraryModal();
		await library.open();
	}

	function buildReceiptAuditFollowUpMarkup(followUp) {
		if (!followUp || typeof followUp !== 'object') {
			return '';
		}
		const stateLabel = String(followUp.state || 'ready').trim().toLowerCase();
		const headline = String(followUp.headline || '').trim();
		const summary = String(followUp.summary || '').trim();
		const scoreLabel = String(followUp.score_label || '').trim();
		const scoreTone = String(followUp.score_tone || 'needs-work').trim().toLowerCase();
		const improvements = Array.isArray(followUp.improvements) ? followUp.improvements.filter(Boolean).slice(0, 3) : [];
		const nextActions = Array.isArray(followUp.next_actions) ? followUp.next_actions.filter(Boolean).slice(0, 3) : [];
		const freshness = String(followUp.freshness_label || '').trim();

		let bodyMarkup = '';
		if (stateLabel === 'loading') {
			bodyMarkup = `
				<p class="sae-receipt-follow-up__status">${buildStatusDotMarkup('loading', 'Refreshing audit')}</p>
				<p class="sae-receipt-follow-up__text">${escapeHtml(summary || 'Checking what improved and what to fix next.')}</p>
			`;
		} else if (stateLabel === 'error') {
			bodyMarkup = `
				<p class="sae-receipt-follow-up__status">${buildStatusDotMarkup('warn', 'Manual refresh needed')}</p>
				<p class="sae-receipt-follow-up__text">${escapeHtml(summary || 'Refresh analysis to continue the audit loop.')}</p>
				<div class="sae-receipt-follow-up__actions">
					<button type="button" class="button button-secondary" data-receipt-action="follow-up-refresh">Refresh analysis</button>
				</div>
			`;
		} else {
			const improvementMarkup = improvements.length
				? `
					<ul class="sae-receipt-follow-up__improvements">
						${improvements.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}
					</ul>
				`
				: '';
			const nextActionsMarkup = nextActions.length
				? `
					<ul class="sae-receipt-follow-up__next-list">
						${nextActions.map((action) => `
							<li class="sae-receipt-follow-up__next-item">
								<div>
									<p class="sae-receipt-follow-up__next-label">${escapeHtml(action.label || 'Next action')}</p>
									<p class="sae-receipt-follow-up__next-meta">${escapeHtml(action.subtitle || 'Review the next recommended fix.')}</p>
								</div>
								<button type="button" class="button button-secondary" data-receipt-action="follow-up-review" data-receipt-follow-index="${Number(action.index || 0)}">Review fix</button>
							</li>
						`).join('')}
					</ul>
				`
				: `
					<p class="sae-receipt-follow-up__empty">No additional high-priority fixes were surfaced.</p>
					<div class="sae-receipt-follow-up__actions">
						<button type="button" class="button button-secondary" data-receipt-action="follow-up-refresh">Refresh analysis</button>
					</div>
				`;
			bodyMarkup = `
				${summary ? `<p class="sae-receipt-follow-up__text">${escapeHtml(summary)}</p>` : ''}
				${improvementMarkup}
				${nextActionsMarkup}
			`;
		}

		return `
			<section class="sae-receipt-follow-up sae-receipt-follow-up--${escapeHtml(stateLabel || 'ready')}">
				<div class="sae-receipt-follow-up__header">
					<div>
						<p class="sae-receipt-follow-up__eyebrow">Audit loop</p>
						${headline ? `<h4 class="sae-receipt-follow-up__headline">${escapeHtml(headline)}</h4>` : ''}
						${freshness ? `<p class="sae-receipt-follow-up__freshness">${escapeHtml(`Updated ${freshness}`)}</p>` : ''}
					</div>
					${scoreLabel ? `<div class="sae-receipt-follow-up__score sae-receipt-follow-up__score--${escapeHtml(scoreTone)}">${escapeHtml(scoreLabel)}</div>` : ''}
				</div>
				${bodyMarkup}
			</section>
		`;
	}

	function renderReceipt(payload) {
		const details = payload && payload.details ? payload.details : [];
		const simpleDetails = payload && Array.isArray(payload.simple_details) ? payload.simple_details : [];
		const activeDetails = isSimpleMode() && simpleDetails.length ? simpleDetails : details;
		const title = isSimpleMode() && payload && payload.friendly_title
			? payload.friendly_title
			: payload && payload.title
				? payload.title
				: 'Operation receipt';
		const message = isSimpleMode() && payload && payload.friendly_message
			? payload.friendly_message
			: payload && payload.message
				? payload.message
				: '';
		const detailRows = activeDetails
			.map((item) => {
				return `<dl><dt>${escapeHtml(item.label)}</dt><dd>${escapeHtml(item.value)}</dd></dl>`;
			})
			.join('');
		const safeViewUrl = toSafeHttpUrl(payload && payload.view_url ? payload.view_url : '');
		const postId = payload && payload.post_id ? Number(payload.post_id) : 0;
		const safeEditUrl = toSafeHttpUrl(payload && payload.edit_url ? payload.edit_url : '') || buildPostEditUrl(postId);
		const receiptContext = payload && payload.receipt_context ? String(payload.receipt_context).trim() : '';
		const isCreateReceipt = receiptContext === 'create';
		const receiptPostTitle = payload && payload.post_title
			? String(payload.post_title).trim()
			: (postId > 0 ? `Draft ${postId}` : '');
		const receiptPostType = payload && payload.post_type
			? String(payload.post_type).trim().toLowerCase()
			: 'page';
		const receiptPostStatus = payload && payload.status
			? String(payload.status).trim().toLowerCase()
			: 'draft';
		const canEditInConsole = isCreateReceipt && postId > 0;
		const canUndo = !isCreateReceipt && payload && payload.kind === 'success' && payload.undo_context && typeof payload.undo_context === 'object';
		const editConsoleButton = canEditInConsole
			? `<button type="button" class="button ${isCreateReceipt ? 'button-primary' : 'button-secondary'}" data-receipt-action="edit-created" data-receipt-post-id="${postId}" data-receipt-post-title="${escapeHtml(receiptPostTitle)}" data-receipt-post-type="${escapeHtml(receiptPostType)}" data-receipt-post-status="${escapeHtml(receiptPostStatus)}">Edit in Console</button>`
			: '';
		const undoButton = canUndo
			? '<button type="button" class="button button-secondary" data-receipt-action="undo">Undo</button>'
			: '';
		const runAnotherClass = isCreateReceipt ? 'button-secondary' : 'button-primary';
		const isSimpleSuccess = isSimpleMode() && payload && payload.kind === 'success';
		const simpleSummary = payload && payload.friendly_summary
			? String(payload.friendly_summary)
			: message;
		const simpleMeta = buildSimpleReceiptMeta(simpleDetails);
		const receiptConfidence = payload && payload.confidence && typeof payload.confidence === 'object' ? payload.confidence : null;
		const receiptScopeLine = payload && payload.scope_line ? String(payload.scope_line).trim() : '';
		const receiptTemplateMode = payload && payload.template_mode ? String(payload.template_mode).trim().toLowerCase() : '';
		const receiptTemplateLabel = payload && payload.template_label ? String(payload.template_label).trim() : '';
		const receiptTemplateRationale = payload && payload.template_rationale ? String(payload.template_rationale).trim() : '';
		const receiptTemplateKey = payload && payload.template_key ? String(payload.template_key).trim() : '';
		const receiptIdempotencyKey = payload && payload.idempotency_key ? String(payload.idempotency_key).trim() : '';
		const savedTemplateLabel = payload && payload.saved_template_label ? String(payload.saved_template_label).trim() : '';
		const receiptAuditFollowUp = payload && payload.audit_follow_up && typeof payload.audit_follow_up === 'object'
			? payload.audit_follow_up
			: null;
		const receiptTemplateChoice = isCreateReceipt
			? buildTemplateChoiceMarkup({
				mode: receiptTemplateMode,
				label: receiptTemplateLabel,
				rationale: receiptTemplateRationale,
				templateKey: receiptTemplateKey,
				showKey: !isSimpleMode(),
			})
			: '';
		const receiptSignals = receiptConfidence || receiptScopeLine
			? `
				<div class="sae-receipt__signals">
					${receiptConfidence ? `<span class="sae-confidence-pill sae-confidence-pill--${receiptConfidence.level || 'medium'}">${escapeHtml(receiptConfidence.label || formatConfidenceLabel(receiptConfidence.level || 'medium'))}</span>` : ''}
					${receiptScopeLine ? `<p class="sae-receipt__scope">${escapeHtml(receiptScopeLine)}</p>` : ''}
				</div>
			`
			: '';
		const saveTemplateButton = isCreateReceipt && receiptIdempotencyKey && !savedTemplateLabel
			? '<button type="button" class="button button-secondary" data-receipt-action="save-template">Save as template</button>'
			: '';
		const savedTemplateNote = isCreateReceipt && savedTemplateLabel
			? `<p class="sae-receipt__template-save-note">Saved as template: <strong>${escapeHtml(savedTemplateLabel)}</strong></p>`
			: '';
		const receiptAuditFollowUpMarkup = buildReceiptAuditFollowUpMarkup(receiptAuditFollowUp);

		els.receipt.className = `sae-receipt sae-fade is-${escapeHtml(payload.kind || 'info')}${isSimpleSuccess ? ' sae-receipt--simple-success' : ''}`;
		state.receiptPayload = payload;
		const receiptState = payload && payload.kind === 'error'
			? 'error'
			: payload && payload.kind === 'warning'
				? 'warn'
				: 'applied';
		const receiptStatusLabel = payload && payload.kind === 'error'
			? 'Needs attention'
			: payload && payload.kind === 'warning'
				? 'Already applied'
				: 'Applied';
		const receiptStatusMarkup = `<p class="sae-receipt__status-line">${buildStatusDotMarkup(receiptState, receiptStatusLabel)}</p>`;
		if (isSimpleSuccess) {
			els.receipt.innerHTML = `
				${receiptStatusMarkup}
				<p class="sae-receipt__eyebrow">Success</p>
				<h3 class="sae-receipt__headline">Saved to WordPress</h3>
				<p class="sae-receipt__summary">${escapeHtml(simpleSummary)}</p>
				${simpleMeta ? `<p class="sae-receipt__meta">${escapeHtml(simpleMeta)}</p>` : ''}
				${receiptTemplateChoice}
				${savedTemplateNote}
				${receiptSignals}
				${receiptAuditFollowUpMarkup}
				<div class="sae-receipt__actions">
					${undoButton}
					${editConsoleButton}
					${saveTemplateButton}
					${safeViewUrl ? `<a class="button button-secondary" href="${escapeHtml(safeViewUrl)}" target="_blank" rel="noopener noreferrer">View page ↗</a>` : ''}
					${safeEditUrl ? `<a class="button button-secondary" href="${escapeHtml(safeEditUrl)}">Edit in WordPress</a>` : ''}
					<button type="button" class="button ${runAnotherClass}" data-receipt-action="run-another">Make another change</button>
				</div>
			`;
		} else {
			els.receipt.innerHTML = `
				${receiptStatusMarkup}
				<h3>${escapeHtml(title)}</h3>
				<p>${escapeHtml(message)}</p>
				${receiptTemplateChoice}
				${savedTemplateNote}
				${receiptSignals}
				${receiptAuditFollowUpMarkup}
				${detailRows}
				<div class="sae-receipt__actions">
					${undoButton}
					${editConsoleButton}
					${saveTemplateButton}
					<button type="button" class="button button-secondary" data-receipt-action="run-another">Run another edit</button>
					${safeViewUrl ? `<a class="button button-secondary" href="${escapeHtml(safeViewUrl)}" target="_blank" rel="noopener noreferrer">View page ↗</a>` : ''}
				</div>
			`;
		}
		setFadeVisibility(els.receipt, true);
		updateConsoleUiStage('receipt');
	}

	function confettiBurst(targetElement) {
		if (!targetElement || !window.requestAnimationFrame) {
			return;
		}
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}

		const rect = targetElement.getBoundingClientRect();
		const width = Math.max(1, Math.floor(rect.width));
		const height = Math.max(1, Math.floor(rect.height));
		const dpr = Math.max(1, Math.floor(window.devicePixelRatio || 1));
		const canvas = document.createElement('canvas');
		canvas.className = 'sae-confetti-canvas';
		canvas.width = width * dpr;
		canvas.height = height * dpr;
		canvas.style.width = `${width}px`;
		canvas.style.height = `${height}px`;

		const context = canvas.getContext('2d');
		if (!context) {
			return;
		}
		context.scale(dpr, dpr);
		targetElement.appendChild(canvas);

		const colors = [BRAND_PRIMARY, '#ffffff', BRAND_ACCENT];
		const particles = Array.from({ length: 52 }, () => ({
			x: width * 0.5,
			y: Math.max(18, height * 0.2),
			vx: (Math.random() - 0.5) * 9,
			vy: -2 - Math.random() * 5,
			size: 2 + Math.random() * 4,
			color: colors[Math.floor(Math.random() * colors.length)],
			spin: (Math.random() - 0.5) * 0.3,
			angle: Math.random() * Math.PI * 2,
		}));

		const durationMs = 1200;
		const gravity = 0.16;
		const start = performance.now();
		let rafId = 0;
		let cleaned = false;
		const cleanup = () => {
			if (cleaned) {
				return;
			}
			cleaned = true;
			if (rafId) {
				window.cancelAnimationFrame(rafId);
			}
			canvas.remove();
		};

		const drawFrame = (now) => {
			const elapsed = now - start;
			const progress = Math.min(1, elapsed / durationMs);
			context.clearRect(0, 0, width, height);

			particles.forEach((particle) => {
				particle.x += particle.vx;
				particle.y += particle.vy;
				particle.vy += gravity;
				particle.angle += particle.spin;
				const alpha = 1 - progress;
				context.save();
				context.translate(particle.x, particle.y);
				context.rotate(particle.angle);
				context.globalAlpha = alpha > 0 ? alpha : 0;
				context.fillStyle = particle.color;
				context.fillRect(-particle.size * 0.5, -particle.size * 0.5, particle.size, particle.size * 1.8);
				context.restore();
			});

			if (progress >= 1) {
				cleanup();
				return;
			}
			rafId = window.requestAnimationFrame(drawFrame);
		};

		rafId = window.requestAnimationFrame(drawFrame);
		window.setTimeout(cleanup, 1500);
	}

	function getFriendlyErrorMessage(error, fallbackMessage) {
		const code = error && error.code ? error.code : '';
		const friendly = ERROR_MESSAGES[code];
		if (friendly) {
			if (code === 'sae_rate_limit' && state.status && state.status.rate_limit) {
				const resetAt = Date.parse(state.status.rate_limit.reset_at || '');
				if (!Number.isNaN(resetAt)) {
					const waitSeconds = Math.max(0, Math.ceil((resetAt - Date.now()) / 1000));
					const waitMinutes = Math.ceil(waitSeconds / 60);
					return `${friendly.message} Please wait about ${waitMinutes} minute${waitMinutes === 1 ? '' : 's'} and try again.`;
				}
			}
			return `${friendly.message} ${friendly.action}`;
		}
		if (code === 'timeout') {
			return 'Request timed out. Please retry. If this keeps happening, check staging connectivity.';
		}
		if (error && error.message) {
			return `${error.message} (${code || 'unknown'})`;
		}
		return fallbackMessage || config.strings?.network_error || 'Request failed.';
	}

	function formatDurationShort(seconds) {
		const totalSeconds = Math.max(0, Number(seconds) || 0);
		const minutes = Math.floor(totalSeconds / 60);
		const remainder = totalSeconds % 60;
		if (!minutes) {
			return `${remainder}s`;
		}
		if (!remainder) {
			return `${minutes}m`;
		}
		return `${minutes}m ${remainder}s`;
	}

	function renderRateLimitHint(status = state.status) {
		if (!els.rateLimitHint) {
			return;
		}
		const rateLimit = status && status.rate_limit && typeof status.rate_limit === 'object'
			? status.rate_limit
			: null;
		if (!isSimpleMode() || !rateLimit || typeof rateLimit.remaining === 'undefined') {
			els.rateLimitHint.hidden = true;
			els.rateLimitHint.textContent = '';
			return;
		}

		const remaining = Math.max(0, Number(rateLimit.remaining) || 0);
		const max = Math.max(0, Number(rateLimit.max) || 0);
		const resetAtMs = Date.parse(rateLimit.reset_at || '');
		const resetSeconds = Number.isNaN(resetAtMs) ? 0 : Math.max(0, Math.ceil((resetAtMs - Date.now()) / 1000));
		const resetLabel = resetSeconds ? formatDurationShort(resetSeconds) : 'soon';
		const base = `${remaining} request${remaining === 1 ? '' : 's'} remaining of ${max || '—'}.`;
		els.rateLimitHint.textContent = remaining > 0
			? `${base} Window resets in about ${resetLabel}.`
			: `Rate limit reached. Please wait about ${resetLabel} before trying again.`;
		const spend = status && status.plan_spend && typeof status.plan_spend === 'object'
			? status.plan_spend
			: null;
		const spendCap = spend ? Math.max(0, Number(spend.cap) || 0) : 0;
		if (spendCap > 0) {
			const spendUsed = Math.max(0, Number(spend.used) || 0);
			const spendRemaining = spend.remaining === null || typeof spend.remaining === 'undefined'
				? Math.max(0, spendCap - spendUsed)
				: Math.max(0, Number(spend.remaining) || 0);
			els.rateLimitHint.textContent += spendRemaining > 0
				? ` Daily tokens: ${spendRemaining} of ${spendCap} remaining.`
				: ' Daily plan token cap reached.';
		}
		els.rateLimitHint.hidden = false;
	}

	function renderPlatformHint(status = state.status) {
		if (!els.platformHint) {
			return;
		}
		const platform = status && status.platform && typeof status.platform === 'object'
			? status.platform
			: null;
		const backend = status && status.ai_provider && status.ai_provider.backend
			? String(status.ai_provider.backend)
			: '';
		if (!platform) {
			els.platformHint.hidden = true;
			els.platformHint.textContent = '';
			return;
		}

		if (platform.ai_client && !platform.text_generation && backend && backend !== 'client') {
			els.platformHint.textContent = `WordPress AI Client has no text_generation model. Planning uses ${backend}.`;
			els.platformHint.hidden = false;
			return;
		}
		if (platform.ai_client && !platform.connectors) {
			els.platformHint.textContent = 'WordPress AI Client is present, but Connectors are not configured. Plan will fail until Settings → Connectors has a provider.';
			els.platformHint.hidden = false;
			return;
		}
		if (platform.ai_client && platform.connectors && backend === 'client') {
			els.platformHint.textContent = 'Planning through WordPress AI Client / Connectors. Apply still needs your approval.';
			els.platformHint.hidden = false;
			return;
		}
		const last = status && status.ai_provider && status.ai_provider.last && typeof status.ai_provider.last === 'object'
			? status.ai_provider.last
			: null;
		if (last && last.backend) {
			const lastBits = [`Last hop: ${last.backend}`];
			if (last.model) {
				lastBits.push(last.model);
			}
			if (Number.isFinite(Number(last.latency_ms))) {
				lastBits.push(`${Number(last.latency_ms)}ms`);
			}
			els.platformHint.textContent = lastBits.join(' · ');
			els.platformHint.hidden = false;
			return;
		}
		if (platform.abilities && !platform.ai_client) {
			els.platformHint.textContent = 'Abilities are on (WordPress 6.9). Upgrade to 7.0 for Connectors; planning still uses AI Engine or the OpenAI adapter.';
			els.platformHint.hidden = false;
			return;
		}

		els.platformHint.hidden = true;
		els.platformHint.textContent = '';
	}

	function setRequestValidationState(kind = '') {
		if (!els.request) {
			return;
		}
		const normalized = String(kind || '').trim().toLowerCase();
		if (!normalized) {
			els.request.setAttribute('aria-invalid', 'false');
			els.request.removeAttribute('data-validation-kind');
			return;
		}
		els.request.setAttribute('aria-invalid', 'true');
		els.request.setAttribute('data-validation-kind', normalized);
	}

	function validateRequestBeforePlan(requestText, options) {
		const normalized = String(requestText || '').replace(/\s+/g, ' ').trim();
		const normalizedLower = normalized.toLowerCase();
		const requestOrigin = options && typeof options === 'object' ? String(options.requestOrigin || '').trim() : '';
		const bypassGibberish = ['quick_action', 'quickstart', 'suggestion', 'suggestion_apply', 'audit', 'audit_apply'].includes(requestOrigin);
		if (!normalized) {
			return { ok: false, kind: 'error', message: 'Request is required.' };
		}
		if (normalized.length < 6) {
			return { ok: false, kind: 'warn', message: 'Add a little more detail so I can plan a safe change.' };
		}
		if (!/[a-z0-9]/i.test(normalized)) {
			return { ok: false, kind: 'warn', message: 'Request looks invalid. Add words describing the change you want.' };
		}
		const compact = normalized.replace(/\s+/g, '');
		if (/^([a-z0-9])\1{5,}$/i.test(compact)) {
			return { ok: false, kind: 'warn', message: 'Request looks like repeated characters. Please rewrite it in plain language.' };
		}
		if (/\b([a-z]{2,})\b(?:\s+\1\b){2,}/i.test(normalized)) {
			return { ok: false, kind: 'warn', message: 'Request looks repetitive. Describe one specific change in plain language.' };
		}
		const lettersOnly = normalized.toLowerCase().replace(/[^a-z]/g, '');
		if (!bypassGibberish && lettersOnly.length >= 10) {
			if (lettersOnly.length < 50) {
				const uniqueRatio = new Set(lettersOnly.split('')).size / lettersOnly.length;
				if (uniqueRatio < 0.24) {
					return { ok: false, kind: 'warn', message: 'Request looks unclear. Try a specific instruction like “update headline to …”.' };
				}
			}
		}
		if (/(delete|remove|wipe|clear)\s+(everything|all|the whole|entire)\b/i.test(normalized)) {
			return { ok: false, kind: 'warn', message: 'That request is too broad. Specify the exact block or field you want to change.' };
		}
		if (/\b(?:publish|unpublish|trash|archive|delete|remove)\b[\s\S]{0,24}\b(?:all|every)\b[\s\S]{0,48}\b(?:drafts?|posts?|pages?)\b/i.test(normalized)) {
			return { ok: false, kind: 'warn', message: 'That looks like a site-wide bulk action. Use one page or post at a time.' };
		}
		const hasCreateIntent = /\b(?:create|build|make|draft|write|generate)\b/.test(normalizedLower) && /\b(?:page|post|blog|article)\b/.test(normalizedLower);
		const hasAnalysisIntent = /\b(?:audit|analy[sz]e|assess|evaluate|review)\b/.test(normalizedLower);
		if (hasCreateIntent && hasAnalysisIntent) {
			return { ok: false, kind: 'warn', message: 'Use one intent at a time. Create first, then run analysis in a separate request.' };
		}
		const intentSignals = [
			/\b(?:create|build|make|draft|write|generate)\b/.test(normalizedLower),
			/\b(?:audit|analy[sz]e|assess|evaluate|review)\b/.test(normalizedLower),
			/\b(?:update|change|edit|set|replace|rewrite|rephrase|shorten|simplify|clarify|tighten|polish|refresh|refine|strengthen|soften|adapt|adjust)\b/.test(normalizedLower),
			/\b(?:add|insert|append)\b/.test(normalizedLower),
			/\b(?:remove|delete|erase|drop)\b/.test(normalizedLower),
		].filter(Boolean).length;
		if (!bypassGibberish && normalized.length >= 120 && intentSignals >= 3) {
			return { ok: false, kind: 'warn', message: 'That request has too many conflicting instructions. Try one concrete task at a time.' };
		}
		const vagueRemovePattern = /^\s*(?:please\s+)?(?:remove|delete)(?:\s+(?:this|that|it|them|something|stuff|content))?\s*[.!?]*\s*$/i;
		if (vagueRemovePattern.test(normalized)) {
			return { ok: false, kind: 'warn', message: 'Removal request is too broad. Mention the exact block, section, or field first.' };
		}
		return { ok: true };
	}

	function getAllowedBlocks() {
		if (!state.status || !state.status.allowlist || !Array.isArray(state.status.allowlist.blocks)) {
			return [];
		}
		return state.status.allowlist.blocks;
	}

	function flattenBlockSummary(blocks, output = []) {
		if (!Array.isArray(blocks)) {
			return output;
		}
		blocks.forEach((block) => {
			if (!block || typeof block !== 'object') {
				return;
			}
			output.push(block);
			if (Array.isArray(block.inner_blocks) && block.inner_blocks.length) {
				flattenBlockSummary(block.inner_blocks, output);
			}
		});
		return output;
	}

	function formatBlockNameForEditors(blockName) {
		const normalized = String(blockName || '').trim();
		if (!normalized) {
			return 'Unknown block';
		}
		const short = normalized.includes('/') ? normalized.split('/').pop() : normalized;
		return short
			.replace(/[-_]+/g, ' ')
			.replace(/\b\w/g, (char) => char.toUpperCase());
	}

	function getBlockFieldNames(block) {
		const fields = block && block.fields && typeof block.fields === 'object'
			? Object.keys(block.fields)
			: block && block.attrs && block.attrs.data && typeof block.attrs.data === 'object'
				? Object.keys(block.attrs.data).filter((name) => !String(name).startsWith('_'))
				: [];
		return fields
			.map((name) => String(name || '').trim())
			.filter(Boolean)
			.slice(0, 4);
	}

	function isPromotablePatternBlockName(blockName) {
		const normalized = String(blockName || '').trim().toLowerCase();
		if (!normalized) {
			return false;
		}
		if (normalized.startsWith('acf/')) {
			return true;
		}
		return normalized === 'core/group' || normalized === 'core/columns';
	}

	function getTopLevelPromotablePatternBlocks(blocks = []) {
		if (!Array.isArray(blocks)) {
			return [];
		}
		return blocks
			.filter((block) => block && Array.isArray(block.index_path) && block.index_path.length === 1)
			.filter((block) => isPromotablePatternBlockName(block.block_name || ''))
			.map((block, index) => ({
				block: cloneJsonLike(block),
				order: index,
				blockIndex: Array.isArray(block.index_path) ? Number(block.index_path[0] || 0) : -1,
				fieldNames: getBlockFieldNames(block),
				label: formatBlockNameForEditors(block.block_name || ''),
			}))
			.filter((entry) => entry.blockIndex >= 0);
	}

	function formatPatternSourceLabel(pattern) {
		const source = String(pattern && pattern.source_type ? pattern.source_type : '').trim().toLowerCase();
		if (source === 'built_in') {
			return 'Built-in';
		}
		if (source === 'saved') {
			return 'Saved';
		}
		return 'Pattern';
	}

	function formatPatternStatusLabel(status) {
		const normalized = String(status || '').trim().toLowerCase();
		if (normalized === 'approved') {
			return 'Approved';
		}
		if (normalized === 'hidden') {
			return 'Hidden';
		}
		return 'Draft';
	}

	function getPatternFieldCount(pattern) {
		const fields = pattern && pattern.structure && pattern.structure.fields && typeof pattern.structure.fields === 'object'
			? Object.keys(pattern.structure.fields)
			: [];
		return fields.length;
	}

	function buildPatternPreviewSummaryMarkup(pattern) {
		if (!pattern || typeof pattern !== 'object') {
			return '<p class="sae-pattern-preview__empty">Choose a section to preview the reusable pattern.</p>';
		}
		const metadata = pattern.metadata && typeof pattern.metadata === 'object' ? pattern.metadata : {};
		const family = String(metadata.family || '').trim();
		const variant = String(metadata.variant || '').trim();
		const purpose = String(metadata.purpose || metadata.what_it_is || pattern.description || '').trim();
		const fieldCount = getPatternFieldCount(pattern);
		const blockName = String(pattern.structure && pattern.structure.block_name ? pattern.structure.block_name : '').trim();
		const modules = Array.isArray(metadata.acf_modules) ? metadata.acf_modules.slice(0, 2).filter(Boolean) : [];
		const metaParts = [
			family,
			variant,
			fieldCount ? `${fieldCount} field${fieldCount === 1 ? '' : 's'}` : '',
			blockName ? formatBlockNameForEditors(blockName) : '',
		].filter(Boolean);

		return `
			<div class="sae-pattern-preview__card">
				<p class="sae-pattern-preview__eyebrow">${escapeHtml(formatPatternSourceLabel(pattern))} · ${escapeHtml(formatPatternStatusLabel(pattern.status || 'draft'))}</p>
				<h4 class="sae-pattern-preview__title">${escapeHtml(pattern.label || 'Pattern preview')}</h4>
				${metaParts.length ? `<p class="sae-pattern-preview__meta">${escapeHtml(metaParts.join(' · '))}</p>` : ''}
				${purpose ? `<p class="sae-pattern-preview__text">${escapeHtml(purpose)}</p>` : ''}
				${modules.length ? `<p class="sae-pattern-preview__modules">${escapeHtml(modules.join(' · '))}</p>` : ''}
			</div>
		`;
	}

	function renderBlockBrowserRows(blocks, postId) {
		if (!els.blockBrowser || !els.blockBrowserList || !els.blockBrowserStatus) {
			return;
		}
		const flattened = flattenBlockSummary(blocks, []).slice(0, BLOCK_BROWSER_MAX_ITEMS);
		if (!flattened.length) {
			els.blockBrowserStatus.textContent = 'No editable blocks were found for this page.';
			els.blockBrowserList.innerHTML = '';
			return;
		}

		els.blockBrowserStatus.textContent = `Showing ${flattened.length} editable block${flattened.length === 1 ? '' : 's'} on post ${postId}.`;
		els.blockBrowserList.innerHTML = flattened
			.map((block, index) => {
				const fieldNames = getBlockFieldNames(block);
				const targetPath = Array.isArray(block.index_path) ? block.index_path.join(',') : '--';
				return `
					<article class="sae-block-browser__item">
						<p class="sae-block-browser__title">#${index + 1} · ${escapeHtml(formatBlockNameForEditors(block.block_name || ''))}</p>
						<p class="sae-block-browser__meta">index_path: ${escapeHtml(targetPath)}</p>
						<p class="sae-block-browser__meta">Fields: ${escapeHtml(fieldNames.length ? fieldNames.join(', ') : 'No direct text fields')}</p>
					</article>
				`;
			})
			.join('');
	}

	async function loadBlockBrowser(force = false) {
		if (!els.blockBrowser || !els.blockBrowserStatus || !els.blockBrowserList) {
			return;
		}

		const showBrowser = isSimpleMode() && !state.batchMode;
		if (!showBrowser) {
			els.blockBrowser.hidden = true;
			els.blockBrowserList.innerHTML = '';
			els.blockBrowserStatus.textContent = '';
			return;
		}

		const postId = Number(els.postId && els.postId.value ? els.postId.value : 0);
		els.blockBrowser.hidden = false;
		if (!postId) {
			els.blockBrowserStatus.textContent = 'Select a page to load editable blocks.';
			els.blockBrowserList.innerHTML = '';
			state.blockBrowserLoadedPostId = 0;
			return;
		}

		if (!force && state.blockBrowserLoadedPostId === postId) {
			return;
		}

		const requestId = state.blockBrowserRequestId + 1;
		state.blockBrowserRequestId = requestId;
		els.blockBrowserStatus.textContent = 'Loading editable blocks…';
		els.blockBrowserList.innerHTML = '';

		try {
			const blocks = await fetchPostBlocks(postId, { force });
			if (state.blockBrowserRequestId !== requestId) {
				return;
			}
			renderBlockBrowserRows(blocks, postId);
			state.blockBrowserLoadedPostId = postId;
		} catch (error) {
			if (state.blockBrowserRequestId !== requestId) {
				return;
			}
			els.blockBrowserStatus.textContent = `Unable to load blocks: ${error.message || 'unknown error'}`;
			els.blockBrowserList.innerHTML = '';
		}
	}

	function isAppModeEnabled() {
		try {
			return window.localStorage.getItem(APP_MODE_STORAGE_KEY) === '1';
		} catch (error) {
			return false;
		}
	}

	function setAppMode(enabled, persist = true) {
		state.appMode = !!enabled;
		document.body.classList.toggle('sae-app-mode', state.appMode);

		if (els.appModeToggle) {
			els.appModeToggle.setAttribute('aria-pressed', state.appMode ? 'true' : 'false');
			els.appModeToggle.textContent = state.appMode ? 'Disable App Mode' : 'Enable App Mode';
		}
		if (els.adminEscape) {
			els.adminEscape.textContent = state.appMode ? 'Back to WP Admin' : 'WP Admin →';
		}

		if (persist) {
			try {
				window.localStorage.setItem(APP_MODE_STORAGE_KEY, state.appMode ? '1' : '0');
			} catch (error) {
				/* no-op: storage may be blocked */
			}
		}
	}

	function getStoredConsoleMode() {
		try {
			return window.localStorage.getItem(CONSOLE_MODE_STORAGE_KEY) === 'dev' ? 'dev' : 'simple';
		} catch (error) {
			return 'simple';
		}
	}

	function isSimpleMode() {
		return state.consoleMode !== 'dev';
	}

	function getGenerateButtonLabel() {
		if (state.batchMode) {
			return 'Preview Batch';
		}
		return isSimpleMode() ? 'Plan changes' : 'Generate Plan';
	}

	function updateGenerateButtonLabel() {
		if (!els.generatePlan) {
			return;
		}
		if (state.planRequestActive) {
			updateActionButtonHints();
			return;
		}
		const label = getGenerateButtonLabel();
		els.generatePlan.textContent = label;
		const shortcut = String(els.generatePlan.dataset.shortcut || '').trim();
		if (shortcut && isSimpleMode() && !state.batchMode) {
			els.generatePlan.setAttribute('aria-label', `${label} ${shortcut}`);
		} else {
			els.generatePlan.removeAttribute('aria-label');
		}
		updateActionButtonHints();
	}

	function setPlanRequestActive(active) {
		state.planRequestActive = !!active;
		updateApplyButtonState();
		if (!els.generatePlan) {
			updateActionButtonHints();
			return;
		}
		if (state.planRequestActive) {
			els.generatePlan.disabled = false;
			els.generatePlan.textContent = isSimpleMode() ? 'Cancel' : 'Cancel Plan';
			els.generatePlan.classList.add('is-cancel');
			updateActionButtonHints();
			return;
		}
		els.generatePlan.classList.remove('is-cancel');
		els.generatePlan.disabled = false;
		updateGenerateButtonLabel();
	}

	function getApplyLabelBase(plan) {
		if (hasDurableEnvelope(plan)) {
			if ('planned' === getDurablePlanState(plan)) {
				return isSimpleMode() ? 'Approve' : 'Approve Plan';
			}
		}
		if (!isSimpleMode()) {
			return hasDurableEnvelope(plan) ? 'Apply Plan' : 'Approve & Apply';
		}
		if (!plan || plan.intent === 'ask' || plan.intent === 'create') {
			return 'Apply Change';
		}
		if (plan.operation === 'batch') {
			return 'Apply Bundle';
		}
		if (plan.operation === 'remove') {
			return 'Apply Removal';
		}
		if (plan.operation === 'insert') {
			return 'Apply Insert';
		}
		if (plan.operation === 'cross_field') {
			return 'Apply Field Update';
		}
		return 'Apply Change';
	}

	function updateApplyButtonLabel(plan) {
		state.applyLabelBase = getApplyLabelBase(plan);
		if (els.apply) {
			els.apply.textContent = state.applyLabelBase;
		}
		updateActionButtonHints();
	}

	function isCreateOutlinePlan(plan) {
		// Outline selection step only — once a durable plan_id exists this is a page_spec review.
		return !!plan && plan.intent === 'create' && Array.isArray(plan.outline) && !getPlanEnvelopeId(plan);
	}

	function setAskPanelCopy(title, subtitle = '') {
		if (!els.askWrap) {
			return;
		}
		const heading = els.askWrap.querySelector('h3');
		const muted = els.askWrap.querySelector('.sae-muted');
		if (heading) {
			heading.textContent = String(title || '');
		}
		if (muted) {
			const text = String(subtitle || '');
			muted.textContent = text;
			muted.hidden = !text;
		}
	}

	function setConsoleMode(mode, persist = true) {
		const normalizedMode = mode === 'dev' ? 'dev' : 'simple';
		state.consoleMode = normalizedMode;
		const devModeEnabled = normalizedMode === 'dev';
		if (state.modeTransitionTimer) {
			window.clearTimeout(state.modeTransitionTimer);
			state.modeTransitionTimer = null;
		}

		document.body.classList.add('sae-console-mode-transitioning');

		document.body.classList.toggle('sae-console-mode-dev', devModeEnabled);
		document.body.classList.toggle('sae-console-mode-simple', !devModeEnabled);

		if (els.modeToggle) {
			els.modeToggle.setAttribute('aria-pressed', devModeEnabled ? 'true' : 'false');
			els.modeToggle.textContent = devModeEnabled ? 'Switch to Simple Mode' : 'Switch to Dev Mode';
		}

		if (persist) {
			try {
				window.localStorage.setItem(CONSOLE_MODE_STORAGE_KEY, normalizedMode);
			} catch (error) {
				/* no-op: storage may be blocked */
			}
		}

		if (!devModeEnabled && state.batchMode) {
			state.batchMode = false;
			if (els.batchMode) {
				els.batchMode.checked = false;
			}
			renderComposeMode();
		}

		updateModeCopy();
		updateEmptyStateCopy();
		updateGenerateButtonLabel();
		updateApplyButtonLabel(state.plan);
		renderRateLimitHint();
		renderPlan();
		if (devModeEnabled) {
			loadBlockBrowser(true);
		}
		if (!state.historyLoaded && !state.historyLoading) {
			renderHistoryDeferredState();
		}
		state.modeTransitionTimer = window.setTimeout(() => {
			document.body.classList.remove('sae-console-mode-transitioning');
			state.modeTransitionTimer = null;
		}, MODE_TRANSITION_DURATION_MS);
	}

	function humanizeFieldName(fieldName) {
		const normalized = String(fieldName || '').replace(/[_-]+/g, ' ').trim();
		if (!normalized) {
			return 'field';
		}
		return normalized
			.split(' ')
			.filter(Boolean)
			.map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
			.join(' ');
	}

	function truncateInlineText(value, maxLength = 78) {
		const text = String(value || '').replace(/\s+/g, ' ').trim();
		if (!text) {
			return '(empty)';
		}
		if (text.length <= maxLength) {
			return text;
		}
		return `${text.slice(0, Math.max(0, maxLength - 1))}…`;
	}

	function resolveCurrentFieldValue(fieldName, currentValues) {
		if (!currentValues || typeof currentValues !== 'object') {
			return { matchedField: '', value: null, found: false };
		}

		const normalizedField = String(fieldName || '').trim();
		if (!normalizedField) {
			return { matchedField: '', value: null, found: false };
		}
		if (Object.prototype.hasOwnProperty.call(currentValues, normalizedField)) {
			return {
				matchedField: normalizedField,
				value: currentValues[normalizedField],
				found: true,
			};
		}

		const preferredFieldMap = {
			headline: ['headline', 'heading', 'title', 'text', 'content'],
			subheading: ['subheading', 'subtitle', 'subhead', 'text', 'content'],
			description: ['description', 'copy', 'body', 'message', 'text', 'content', 'html'],
			content: ['content', 'text', 'headline', 'heading', 'title', 'description', 'copy', 'body', 'html'],
			button_text: ['button_text', 'cta_text', 'button_label', 'label', 'text', 'content'],
			html: ['html', 'content', 'text'],
		};

		const candidates = preferredFieldMap[normalizedField] || [normalizedField];
		for (const candidate of candidates) {
			if (Object.prototype.hasOwnProperty.call(currentValues, candidate)) {
				return {
					matchedField: candidate,
					value: currentValues[candidate],
					found: true,
				};
			}
		}

		return { matchedField: '', value: null, found: false };
	}

	function getTargetContextLabel(target = null, block = null) {
		if (!target || typeof target !== 'object') {
			return '';
		}
		const blockName = block && typeof block === 'object'
			? formatBlockNameForEditors(block.block_name || block.blockName || '')
			: '';
		const anchor = String(target.anchor || '').trim();
		if (anchor) {
			return blockName ? `${blockName} · Anchor: ${anchor}` : `Anchor: ${anchor}`;
		}
		const blockId = String(target.block_id || '').trim();
		if (blockId) {
			return blockName ? `${blockName} · Block ID: ${blockId}` : `Block ID: ${blockId}`;
		}
		const path = normalizePathForCompare(target.index_path);
		if (path.length) {
			const pathLabel = `Block ${path.map((value) => Number(value) + 1).join('.')}`;
			return blockName ? `${blockName} · ${pathLabel}` : pathLabel;
		}
		return blockName;
	}

	function getPlanTargetContextLabel(plan) {
		const target = plan && plan.payload && plan.payload.target && typeof plan.payload.target === 'object'
			? plan.payload.target
			: null;
		return getTargetContextLabel(target);
	}

	function getPreviewDisplayFieldName(plan, row) {
		const fallback = row && (row.displayFieldName || row.fieldName)
			? String(row.displayFieldName || row.fieldName)
			: '';
		const normalizedFallback = fallback.trim().toLowerCase();
		const genericFields = ['content', 'text', 'html', 'body', 'message'];
		if (!genericFields.includes(normalizedFallback)) {
			return fallback;
		}
		const planner = plan && plan.planner && typeof plan.planner === 'object' ? plan.planner : {};
		const rewriteMeta = planner.rewrite && typeof planner.rewrite === 'object' ? planner.rewrite : null;
		const rewriteLabel = rewriteMeta && (rewriteMeta.field_label || rewriteMeta.field_name)
			? String(rewriteMeta.field_label || rewriteMeta.field_name).trim()
			: '';
		if (rewriteLabel) {
			return rewriteLabel;
		}
		return fallback;
	}

	function buildPreviewDiffMarkup(beforeValue, afterValue) {
		if (typeof beforeValue !== 'string' || typeof afterValue !== 'string') {
			return null;
		}
		const beforeText = String(beforeValue || '').trim();
		const afterText = String(afterValue || '').trim();
		if (!beforeText || !afterText || beforeText === afterText) {
			return null;
		}
		const safeBefore = truncateInlineText(beforeText, 180);
		const safeAfter = truncateInlineText(afterText, 180);
		return buildInlineWordDiffHtml(safeBefore, safeAfter);
	}

	function getPreviewTone(beforeValue, afterValue, hasKnownBeforeValue = true) {
		if (!hasKnownBeforeValue) {
			return 'modify';
		}
		const beforeEmpty = beforeValue === null || typeof beforeValue === 'undefined' || String(beforeValue).trim() === '';
		const afterEmpty = afterValue === null || typeof afterValue === 'undefined' || String(afterValue).trim() === '';
		if (beforeEmpty && !afterEmpty) {
			return 'add';
		}
		if (!beforeEmpty && afterEmpty) {
			return 'remove';
		}
		return 'modify';
	}

	function buildSimplePreviewRows(plan, currentValues = null) {
		if (!plan || plan.intent === 'ask' || plan.intent === 'create' || isAuditPlan(plan)) {
			return [];
		}
		if (plan.operation === 'cross_field') {
			const payload = plan.payload && typeof plan.payload === 'object' ? plan.payload : {};
			const dryRun = plan.dry_run && typeof plan.dry_run === 'object' ? plan.dry_run : {};
			const fieldName = String(payload.field || '').trim();
			const beforeValue = Object.prototype.hasOwnProperty.call(dryRun, 'old_value')
				? dryRun.old_value
				: Object.prototype.hasOwnProperty.call(payload, 'current_value')
					? payload.current_value
					: null;
			const afterValue = Object.prototype.hasOwnProperty.call(dryRun, 'new_value')
				? dryRun.new_value
				: Object.prototype.hasOwnProperty.call(payload, 'value')
					? payload.value
					: null;
			if (!fieldName || String(afterValue || '').trim() === '') {
				return [];
			}
			const changed = JSON.stringify(beforeValue) !== JSON.stringify(afterValue);
			if (!changed) {
				return [];
			}
			const inlineDiff = buildPreviewDiffMarkup(beforeValue, afterValue);
			return [
				{
					fieldName,
					displayFieldName: getPreviewDisplayFieldName(plan, { fieldName }),
					targetContextLabel: getPlanTargetContextLabel(plan),
					changed,
					tone: getPreviewTone(beforeValue, afterValue, true),
					hasKnownBeforeValue: true,
					rawBeforeValue: beforeValue,
					rawAfterValue: afterValue,
					beforeText: formatDiffValue(beforeValue),
					afterText: formatDiffValue(afterValue),
					beforeHtml: inlineDiff ? inlineDiff.beforeHtml : '',
					afterHtml: inlineDiff ? inlineDiff.afterHtml : '',
					hasInlineDiff: !!inlineDiff,
				},
			];
		}
		if (plan.operation !== 'update') {
			return [];
		}
		const fields = plan.payload && plan.payload.fields && typeof plan.payload.fields === 'object'
			? plan.payload.fields
			: null;
		if (!fields || !Object.keys(fields).length) {
			return [];
		}

		return Object.entries(fields)
			.map(([fieldName, nextValue]) => {
				const resolvedCurrent = resolveCurrentFieldValue(fieldName, currentValues);
				const hasCurrent = resolvedCurrent.found;
				const previousValue = hasCurrent ? resolvedCurrent.value : null;
				const changed = hasCurrent
					? JSON.stringify(previousValue) !== JSON.stringify(nextValue)
					: true;
				const inlineDiff = hasCurrent ? buildPreviewDiffMarkup(previousValue, nextValue) : null;
				return {
					fieldName,
					displayFieldName: getPreviewDisplayFieldName(plan, { displayFieldName: resolvedCurrent.matchedField || fieldName, fieldName }),
					targetContextLabel: getPlanTargetContextLabel(plan),
					targetKey: 'single-target',
					changed,
					tone: getPreviewTone(previousValue, nextValue, hasCurrent),
					hasKnownBeforeValue: hasCurrent,
					rawBeforeValue: hasCurrent ? previousValue : null,
					rawAfterValue: nextValue,
					beforeText: hasCurrent ? formatDiffValue(previousValue) : '(current value)',
					afterText: formatDiffValue(nextValue),
					beforeHtml: inlineDiff ? inlineDiff.beforeHtml : '',
					afterHtml: inlineDiff ? inlineDiff.afterHtml : '',
					hasInlineDiff: !!inlineDiff,
				};
			})
			.filter((item) => item.changed);
	}

	function buildBatchPreviewRows(plan, blocks = null) {
		if (!plan || plan.operation !== 'batch') {
			return [];
		}
		const operations = plan.payload && Array.isArray(plan.payload.operations)
			? plan.payload.operations
			: [];
		if (!operations.length) {
			return [];
		}

		return operations.flatMap((operation, operationIndex) => {
			if (!operation || operation.action !== 'update') {
				return [];
			}
			const target = operation.target && typeof operation.target === 'object' ? operation.target : null;
			const fields = operation.fields && typeof operation.fields === 'object' ? operation.fields : null;
			if (!target || !fields || !Object.keys(fields).length) {
				return [];
			}

			const targetBlock = Array.isArray(blocks) ? findBlockByTarget(blocks, target) : null;
			const currentValues = targetBlock && targetBlock.fields && typeof targetBlock.fields === 'object'
				? targetBlock.fields
				: targetBlock && targetBlock.attrs && targetBlock.attrs.data && typeof targetBlock.attrs.data === 'object'
					? targetBlock.attrs.data
					: {};
			const targetContextLabel = getTargetContextLabel(target, targetBlock);
			const targetKey = JSON.stringify(target);

			return Object.entries(fields)
				.map(([fieldName, nextValue]) => {
					const resolvedCurrent = resolveCurrentFieldValue(fieldName, currentValues);
					const hasCurrent = resolvedCurrent.found;
					const previousValue = hasCurrent ? resolvedCurrent.value : null;
					const changed = hasCurrent
						? JSON.stringify(previousValue) !== JSON.stringify(nextValue)
						: true;
					const inlineDiff = hasCurrent ? buildPreviewDiffMarkup(previousValue, nextValue) : null;
					return {
						fieldName,
						displayFieldName: resolvedCurrent.matchedField || fieldName,
						targetContextLabel,
						targetKey,
						operationIndex,
						changed,
						tone: getPreviewTone(previousValue, nextValue, hasCurrent),
						hasKnownBeforeValue: hasCurrent,
						rawBeforeValue: hasCurrent ? previousValue : null,
						rawAfterValue: nextValue,
						beforeText: hasCurrent ? formatDiffValue(previousValue) : '(current value)',
						afterText: formatDiffValue(nextValue),
						beforeHtml: inlineDiff ? inlineDiff.beforeHtml : '',
						afterHtml: inlineDiff ? inlineDiff.afterHtml : '',
						hasInlineDiff: !!inlineDiff,
					};
				})
				.filter((item) => item.changed);
		});
	}

	function countPreviewTargets(previewRows = []) {
		if (!Array.isArray(previewRows) || !previewRows.length) {
			return 0;
		}
		return new Set(
			previewRows
				.map((item) => String(item && item.targetKey ? item.targetKey : '').trim())
				.filter(Boolean)
		).size;
	}

	function renderSimplePreview(plan, previewRows = []) {
		if (!els.simplePreview || !els.simplePreviewList) {
			return;
		}

		if (!isSimpleMode() || !plan || plan.intent === 'ask' || plan.intent === 'create' || isAuditPlan(plan)) {
			state.planPreviewRows = [];
			els.simplePreview.hidden = true;
			els.simplePreviewList.innerHTML = '';
			return;
		}

		const supportsFieldPreview = plan.operation === 'update' || plan.operation === 'batch';
		if (!previewRows.length) {
			if (!supportsFieldPreview) {
				state.planPreviewRows = [];
				els.simplePreview.hidden = true;
				els.simplePreviewList.innerHTML = '';
				return;
			}
			state.planPreviewRows = [];
			els.simplePreview.hidden = false;
			els.simplePreviewList.innerHTML = '<p class="sae-simple-preview-empty">Loading before/after preview…</p>';
			return;
		}

		state.planPreviewRows = Array.isArray(previewRows) ? previewRows.map((item) => Object.assign({}, item)) : [];
		els.simplePreviewList.innerHTML = previewRows
			.slice(0, 3)
			.map((item, index) => {
				const fieldLabel = humanizeFieldName(item.displayFieldName || item.fieldName);
				const targetContext = String(item.targetContextLabel || '').trim();
				const beforeMarkup = item.hasInlineDiff && item.beforeHtml
					? item.beforeHtml
					: escapeHtml(truncateInlineText(item.beforeText, 120));
				const afterMarkup = item.hasInlineDiff && item.afterHtml
					? item.afterHtml
					: escapeHtml(truncateInlineText(item.afterText, 120));
				return `
					<article class="sae-simple-preview-item sae-simple-preview-item--${escapeHtml(item.tone || 'modify')}${index === 0 ? ' is-featured' : ''} sae-progressive-card" style="--sae-row-index:${index};--sae-stagger-index:${index}">
						<div class="sae-simple-preview-item__header">
							<p class="sae-simple-preview-item__field">${escapeHtml(fieldLabel)}</p>
							${targetContext ? `<span class="sae-simple-preview-item__context">${escapeHtml(targetContext)}</span>` : ''}
						</div>
						<div class="sae-simple-preview-item__values">
							<span class="sae-simple-preview-item__before">${beforeMarkup}</span>
							<span class="sae-simple-preview-item__arrow">→</span>
							<span class="sae-simple-preview-item__after">${afterMarkup}</span>
						</div>
					</article>
				`;
			})
			.join('');
		els.simplePreview.hidden = false;
	}

	function getSimpleSummaryEyebrow(plan) {
		if (isAuditPlan(plan)) {
			return 'Audit at a glance';
		}
		if (plan && plan.intent === 'ask') {
			return 'Suggestions at a glance';
		}
		if (plan && plan.intent === 'create') {
			return 'Draft at a glance';
		}
		return 'Change at a glance';
	}

	function getSimpleSummaryGlanceItems(plan, previewRows = []) {
		if (!plan || typeof plan !== 'object') {
			return [];
		}
		const post = plan.post && typeof plan.post === 'object' ? plan.post : {};
		const pageLabel = post.post_title || post.post_slug || (post.post_id ? `Post ${post.post_id}` : '');
		const items = [];
		if (pageLabel) {
			items.push(`Target: ${pageLabel}`);
		}

		if (isAuditPlan(plan)) {
			const issueCount = getAuditIssues(plan).length;
			items.push(issueCount ? `${issueCount} issue${issueCount === 1 ? '' : 's'}` : 'No issues cached');
			return items.slice(0, 2);
		}
		if (plan.intent === 'ask') {
			const suggestionCount = Array.isArray(plan.suggestions) ? plan.suggestions.length : 0;
			items.push(`${suggestionCount || 0} suggestion${suggestionCount === 1 ? '' : 's'}`);
			return items.slice(0, 2);
		}
		if (plan.intent === 'create') {
			const postType = String(plan.post_type || '').trim().toLowerCase() === 'post' ? 'Blog draft' : 'Page draft';
			items.push(postType);
			return items.slice(0, 2);
		}
		if (plan.operation === 'batch') {
			const count = previewRows.length || (Array.isArray(plan.payload && plan.payload.operations) ? plan.payload.operations.length : 0);
			items.push(`${count || 0} coordinated change${count === 1 ? '' : 's'}`);
			return items.slice(0, 2);
		}
		if (plan.operation === 'update' || plan.operation === 'cross_field') {
			const count = previewRows.length || 1;
			items.push(`${count} field change${count === 1 ? '' : 's'}`);
			return items.slice(0, 2);
		}
		if (plan.operation === 'insert') {
			items.push('1 new block');
			return items.slice(0, 2);
		}
		if (plan.operation === 'remove') {
			items.push('1 block removed');
			return items.slice(0, 2);
		}
		return items.slice(0, 2);
	}

	function getSimpleSummarySafetyText(plan) {
		if (!plan || typeof plan !== 'object') {
			return '';
		}
		if (isAuditPlan(plan)) {
			return 'Analysis only. Nothing changes until you review a fix.';
		}
		if (plan.intent === 'ask') {
			return 'Suggestions only. Nothing changes until you turn one into a plan.';
		}
		if (plan.intent === 'create') {
			return 'This creates a new draft only. Existing pages stay untouched.';
		}
		return 'Nothing is live yet. Review the preview, then apply when ready.';
	}

	function renderSimpleSummary(plan, previewRows = []) {
		if (!els.simpleSummary || !els.simpleSummaryHeadline || !els.simpleSummaryDetails) {
			return;
		}

		if (!isSimpleMode() || !plan) {
			els.simpleSummary.hidden = true;
			if (els.simpleSummaryStatus) {
				els.simpleSummaryStatus.hidden = true;
				els.simpleSummaryStatus.innerHTML = '';
			}
			if (els.simpleSummaryEyebrow) {
				els.simpleSummaryEyebrow.textContent = 'Change at a glance';
			}
			els.simpleSummaryHeadline.textContent = '';
			els.simpleSummaryDetails.textContent = '';
			if (els.simpleSummaryGlance) {
				els.simpleSummaryGlance.hidden = true;
				els.simpleSummaryGlance.innerHTML = '';
			}
			if (els.simpleSummarySignals) {
				els.simpleSummarySignals.hidden = true;
			}
			if (els.simpleSummaryConfidence) {
				els.simpleSummaryConfidence.className = 'sae-confidence-pill';
				els.simpleSummaryConfidence.textContent = '';
			}
			if (els.simpleSummaryScope) {
				els.simpleSummaryScope.hidden = true;
				els.simpleSummaryScope.textContent = '';
			}
			if (els.simpleSummarySafety) {
				els.simpleSummarySafety.hidden = true;
				els.simpleSummarySafety.textContent = '';
			}
			return;
		}

		const post = plan.post || {};
		const pageLabel = post.post_title || post.post_slug || (post.post_id ? `post ${post.post_id}` : 'the selected page');
		let headline = 'Ready to review.';
		let details = 'Confirm this plan when you are ready.';
		let statusLabel = 'Review ready';
		let statusTone = 'ready';

		if (isAuditPlan(plan)) {
			const score = Number(plan.audit && plan.audit.score ? plan.audit.score : 0);
			const issues = getAuditIssues(plan);
			const toneLabel = Number.isFinite(score) && score > 0
				? `${score}/10 · ${getAuditScoreLabel(score)}`
				: 'Audit complete';
			headline = `Audit ready. ${toneLabel}.`;
			statusLabel = 'Audit ready';
			if (!issues.length && score >= 8) {
				details = `${pageLabel} is already strong. No high-priority fixes recommended.`;
			} else if (!issues.length) {
				details = `${pageLabel} has no actionable fixes yet. Review the audit summary and rerun if needed.`;
			} else {
				const highCount = issues.filter((issue) => String(issue && issue.severity ? issue.severity : '').toLowerCase() === 'high').length;
				details = highCount > 0
					? `${issues.length} issue${issues.length === 1 ? '' : 's'} found on ${pageLabel}, including ${highCount} high-priority item${highCount === 1 ? '' : 's'}.`
					: `${issues.length} issue${issues.length === 1 ? '' : 's'} found on ${pageLabel}. Start with the top recommendations below.`;
			}
		} else if (plan.intent === 'ask') {
			headline = 'Suggestions are ready.';
			details = 'Pick one suggestion to move to a ready-to-apply plan.';
			statusLabel = 'Suggestions ready';
		} else if (plan.operation === 'update' && previewRows.length) {
			const firstChange = previewRows[0];
			const extraCount = Math.max(0, previewRows.length - 1);
			headline = `Ready to apply. ${humanizeFieldName(firstChange.displayFieldName || firstChange.fieldName)} will change from "${truncateInlineText(firstChange.beforeText, 54)}" to "${truncateInlineText(firstChange.afterText, 54)}".`;
			details = extraCount > 0
				? `${extraCount} more field change${extraCount === 1 ? '' : 's'} will also be applied on ${pageLabel}.`
				: `This update will be applied on ${pageLabel}.`;
		} else if (plan.operation === 'insert') {
			headline = 'Ready to apply. A new block will be inserted.';
			details = `This insert will be applied on ${pageLabel}.`;
			statusLabel = 'Insert ready';
		} else if (plan.operation === 'remove') {
			headline = 'Ready to apply. A block will be removed.';
			details = `This removal will be applied on ${pageLabel}.`;
			statusLabel = 'Removal ready';
			statusTone = 'warn';
		} else if (plan.operation === 'batch') {
			const operationCount = Array.isArray(plan.payload && plan.payload.operations) ? plan.payload.operations.length : 0;
			const bundleName = plan.payload && plan.payload.bundle_name ? String(plan.payload.bundle_name).trim() : '';
			if (previewRows.length) {
				const targetCount = countPreviewTargets(previewRows);
				headline = `Ready to apply. ${previewRows.length} coordinated field change${previewRows.length === 1 ? '' : 's'} prepared.`;
				details = targetCount > 1
					? `These updates touch ${targetCount} blocks on ${pageLabel}.`
					: `These updates touch ${pageLabel}.`;
			} else {
				headline = `Ready to apply. ${operationCount || 0} batch operation${operationCount === 1 ? '' : 's'} prepared.`;
				details = bundleName
					? `Bundle "${bundleName}" is ready. Review the batch details, then apply to ${pageLabel}.`
					: `Review the batch details, then apply to ${pageLabel}.`;
			}
			statusLabel = 'Coordinated review';
		} else if (plan.operation === 'cross_field') {
			if (previewRows.length) {
				const firstChange = previewRows[0];
				headline = `Ready to apply. ${humanizeFieldName(firstChange.displayFieldName || firstChange.fieldName)} will update.`;
			} else {
				headline = 'Ready to apply. A field update is prepared.';
			}
			details = `Review this field update, then apply on ${pageLabel}.`;
			statusLabel = 'Field update ready';
		} else {
			headline = 'Ready to apply.';
			details = `This plan targets ${pageLabel}.`;
		}

		const transparency = buildPlanTransparency(plan, previewRows);
		const glanceItems = getSimpleSummaryGlanceItems(plan, previewRows);
		if (els.simpleSummaryEyebrow) {
			els.simpleSummaryEyebrow.textContent = getSimpleSummaryEyebrow(plan);
		}
		if (els.simpleSummaryStatus) {
			els.simpleSummaryStatus.innerHTML = buildStatusDotMarkup(statusTone, statusLabel);
			els.simpleSummaryStatus.hidden = false;
		}
		els.simpleSummaryHeadline.textContent = headline;
		els.simpleSummaryDetails.textContent = details;
		if (els.simpleSummaryGlance) {
			els.simpleSummaryGlance.innerHTML = glanceItems
				.map((item) => `<span class="sae-simple-summary__glance-chip">${escapeHtml(item)}</span>`)
				.join('');
			els.simpleSummaryGlance.hidden = !glanceItems.length;
		}
		if (els.simpleSummarySignals && els.simpleSummaryConfidence) {
			els.simpleSummaryConfidence.className = `sae-confidence-pill sae-confidence-pill--${transparency.confidence.level || 'medium'}`;
			els.simpleSummaryConfidence.textContent = transparency.confidence.label || formatConfidenceLabel('medium');
			els.simpleSummarySignals.hidden = false;
		}
		if (els.simpleSummaryScope) {
			els.simpleSummaryScope.textContent = transparency.scopeLine || '';
			els.simpleSummaryScope.hidden = !transparency.scopeLine;
		}
		if (els.simpleSummarySafety) {
			const safetyText = getSimpleSummarySafetyText(plan);
			els.simpleSummarySafety.textContent = safetyText;
			els.simpleSummarySafety.hidden = !safetyText;
		}
		els.simpleSummary.hidden = false;
	}

	function renderAuditPlan(plan) {
		if (!els.askWrap || !els.askList) {
			return;
		}
		clearProgressiveRenderTimers();
		writeScoutAuditSnapshot(plan);

		const simpleModeActive = isSimpleMode();
		const post = plan && plan.post && typeof plan.post === 'object' ? plan.post : {};
		const audit = plan && plan.audit && typeof plan.audit === 'object' ? plan.audit : {};
		const issues = getAuditIssues(plan);
		const score = Number(audit.score || 0);
		const summary = String(audit.summary || '').trim() || 'Audit complete.';
		const scoreTone = getAuditScoreTone(score);
		const scoreLabel = Number.isFinite(score) && score > 0
			? `${score}/10`
			: '--';
		const pageLabel = post.post_title || post.post_slug || (post.post_id ? `post ${post.post_id}` : 'the selected page');
		const fixAllCommand = buildAuditFixAllCommand(plan);

		setAskPanelCopy(
			simpleModeActive ? 'Audit results' : `Audit — ${pageLabel}`,
			simpleModeActive
				? 'Review the top issues, then send one fix into the planner.'
				: 'Review the structured findings, then turn any issue into a safe edit plan.'
		);

		if (!issues.length) {
			els.askList.innerHTML = `
				<article class="sae-audit-strong-state">
					<div class="sae-audit-score-card sae-audit-score-card--${escapeHtml(scoreTone)}">
						<p class="sae-audit-score-card__eyebrow">Page score</p>
						<p class="sae-audit-score-card__value">${escapeHtml(scoreLabel)}</p>
						<p class="sae-audit-score-card__label">${escapeHtml(getAuditScoreLabel(score))}</p>
					</div>
					<div class="sae-audit-strong-state__body">
						<p class="sae-audit-strong-state__summary">${escapeHtml(summary)}</p>
						<p class="sae-audit-strong-state__detail">This page is strong. No high-priority fixes recommended.</p>
					</div>
				</article>
			`;
			els.askWrap.hidden = false;
			return;
		}

				const cards = issues
					.map((issue, index) => {
						const severity = String(issue && issue.severity ? issue.severity : '').trim().toLowerCase();
						const title = String(issue && issue.title ? issue.title : `Issue ${index + 1}`);
						const rationale = String(issue && issue.rationale ? issue.rationale : '').trim();
						const sectionLabel = String(issue && issue.section_label ? issue.section_label : '').trim();
						const category = String(issue && issue.category ? issue.category : '').trim();
						const fixCommand = buildPlannerSafeAuditCommand(issue, plan && plan.post ? plan.post : null, { includePostHint: false });
						const meta = [
							severity ? `<span class="sae-audit-issue__badge sae-audit-issue__badge--${escapeHtml(severity)}">${escapeHtml(formatAuditSeverityLabel(severity))}</span>` : '',
							category ? `<span class="sae-audit-issue__meta-chip">${escapeHtml(category)}</span>` : '',
					sectionLabel ? `<span class="sae-audit-issue__meta-chip">${escapeHtml(sectionLabel)}</span>` : '',
				].filter(Boolean).join('');
				const codeMarkup = !simpleModeActive && fixCommand
					? `<code class="sae-audit-issue__command">${escapeHtml(fixCommand)}</code>`
					: '';
				return `
					<article class="sae-audit-issue sae-audit-issue--${escapeHtml(severity || 'low')} sae-progressive-card">
						<div class="sae-audit-issue__header">
							<h4>${escapeHtml(title)}</h4>
							${meta ? `<div class="sae-audit-issue__meta">${meta}</div>` : ''}
						</div>
						<p class="sae-audit-issue__rationale">${escapeHtml(rationale || 'No rationale returned.')}</p>
						${codeMarkup}
						<div class="sae-audit-issue__actions">
							<button type="button" class="button button-primary" data-audit-index="${index}" data-audit-action="apply">${escapeHtml(simpleModeActive ? 'Fix it' : 'Generate fix plan')}</button>
							<button type="button" class="button button-secondary" data-audit-index="${index}" data-audit-action="compose">Add to composer</button>
						</div>
					</article>
				`;
			});

		els.askList.innerHTML = `
			<section class="sae-audit-results">
				<header class="sae-audit-results__header">
					<div class="sae-audit-score-card sae-audit-score-card--${escapeHtml(scoreTone)}">
						<p class="sae-audit-score-card__eyebrow">Page score</p>
						<p class="sae-audit-score-card__value">${escapeHtml(scoreLabel)}</p>
						<p class="sae-audit-score-card__label">${escapeHtml(getAuditScoreLabel(score))}</p>
					</div>
					<div class="sae-audit-results__summary">
						<p class="sae-audit-results__eyebrow">${issues.length} issue${issues.length === 1 ? '' : 's'} found</p>
						<p class="sae-audit-results__text">${escapeHtml(summary)}</p>
						${fixAllCommand ? `<button type="button" class="button button-secondary sae-audit-results__compose-all" data-audit-action="compose-all">Queue fixes in composer</button>` : ''}
					</div>
				</header>
				<div class="sae-audit-results__list" data-progressive-list="audit"></div>
			</section>
		`;
		const auditList = els.askList.querySelector('[data-progressive-list="audit"]');
		progressivelyRenderHtmlCards(auditList, cards, {
			intervalMs: 110,
		});
		els.askWrap.hidden = false;
	}

	function createBatchOperation(action = 'update') {
		return {
			id: `op_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
			action,
			block_name: '',
			fields_text: '{\n  \n}',
			target_type: 'anchor',
			target_value: '',
			remove_all: false,
			position_type: 'append',
			position_target_type: 'anchor',
			position_target_value: '',
			parent_path_text: '',
		};
	}

	function normalizeBatchOperation(operation) {
		if (!operation || typeof operation !== 'object') {
			return createBatchOperation('update');
		}
		const normalized = Object.assign(createBatchOperation(operation.action || 'update'), operation);
		if (!['insert', 'update', 'remove'].includes(normalized.action)) {
			normalized.action = 'update';
		}
		if (typeof normalized.fields_text !== 'string' || normalized.fields_text.trim() === '') {
			normalized.fields_text = '{\n  \n}';
		}
		if (!['append', 'prepend', 'before', 'after'].includes(normalized.position_type)) {
			normalized.position_type = 'append';
		}
		if (!['anchor', 'index_path', 'block_id'].includes(normalized.target_type)) {
			normalized.target_type = 'anchor';
		}
		if (!['anchor', 'index_path', 'block_id'].includes(normalized.position_target_type)) {
			normalized.position_target_type = 'anchor';
		}
		normalized.remove_all = !!normalized.remove_all;
		return normalized;
	}

	function ensureBatchSeed() {
		if (!state.batchOperations.length) {
			state.batchOperations = [createBatchOperation('update')];
		}
	}

	function renderComposeMode() {
		const batchMode = !!state.batchMode;
		els.batchMode.checked = batchMode;
		els.singleModeWrap.hidden = batchMode;
		els.batchModeWrap.hidden = !batchMode;
		updateGenerateButtonLabel();
		if (batchMode) {
			ensureBatchSeed();
			renderBatchOperations();
		}
		loadBlockBrowser(true);
	}

	function renderTargetControls(prefix, operation) {
		const typeField = `${prefix}_type`;
		const valueField = `${prefix}_value`;
		return `
			<div class="sae-batch-target">
				<div class="sae-col">
					<label class="sae-field">Target type</label>
					<select data-batch-field="${typeField}">
						<option value="anchor" ${operation[typeField] === 'anchor' ? 'selected' : ''}>anchor</option>
						<option value="index_path" ${operation[typeField] === 'index_path' ? 'selected' : ''}>index_path</option>
						<option value="block_id" ${operation[typeField] === 'block_id' ? 'selected' : ''}>block_id</option>
					</select>
				</div>
				<div class="sae-col">
					<label class="sae-field">Target value</label>
					<input type="text" data-batch-field="${valueField}" value="${escapeHtml(operation[valueField] || '')}" placeholder="${operation[typeField] === 'index_path' ? '0,2,1' : operation[typeField] === 'block_id' ? 'acf_123abc' : 'hero'}" />
				</div>
			</div>
		`;
	}

	function renderBatchOperation(operation, index) {
		const allowedBlocks = getAllowedBlocks();
		const blockOptions = ['<option value="">Select block type…</option>']
			.concat(
				allowedBlocks.map((blockName) => `<option value="${escapeHtml(blockName)}" ${operation.block_name === blockName ? 'selected' : ''}>${escapeHtml(blockName)}</option>`)
			)
			.join('');

		const actionFields = [];
		if (operation.action === 'insert') {
			actionFields.push(`
				<div class="sae-col">
					<label class="sae-field">Block type</label>
					<select data-batch-field="block_name">${blockOptions}</select>
				</div>
				<div class="sae-col">
					<label class="sae-field">Position</label>
					<select data-batch-field="position_type">
						<option value="append" ${operation.position_type === 'append' ? 'selected' : ''}>append</option>
						<option value="prepend" ${operation.position_type === 'prepend' ? 'selected' : ''}>prepend</option>
						<option value="before" ${operation.position_type === 'before' ? 'selected' : ''}>before</option>
						<option value="after" ${operation.position_type === 'after' ? 'selected' : ''}>after</option>
					</select>
				</div>
				<div class="sae-col">
					<label class="sae-field">Parent path (optional)</label>
					<input type="text" data-batch-field="parent_path_text" value="${escapeHtml(operation.parent_path_text || '')}" placeholder="0,3" />
				</div>
			`);
			if (operation.position_type === 'before' || operation.position_type === 'after') {
				actionFields.push(renderTargetControls('position_target', operation));
			}
			actionFields.push(`
				<div class="sae-col sae-col--full">
					<label class="sae-field">Fields JSON</label>
					<textarea rows="4" data-batch-field="fields_text" spellcheck="false">${escapeHtml(operation.fields_text || '{\n  \n}')}</textarea>
				</div>
			`);
		} else if (operation.action === 'update') {
			actionFields.push(renderTargetControls('target', operation));
			actionFields.push(`
				<div class="sae-col sae-col--full">
					<label class="sae-field">Fields JSON</label>
					<textarea rows="4" data-batch-field="fields_text" spellcheck="false">${escapeHtml(operation.fields_text || '{\n  \n}')}</textarea>
				</div>
			`);
		} else {
			actionFields.push(`
				<div class="sae-col sae-col--full">
					<label class="sae-toggle">
						<input type="checkbox" data-batch-field="remove_all" ${operation.remove_all ? 'checked' : ''} />
						<span>Remove all blocks of one type</span>
					</label>
				</div>
			`);
			if (operation.remove_all) {
				actionFields.push(`
					<div class="sae-col">
						<label class="sae-field">Block type</label>
						<select data-batch-field="block_name">${blockOptions}</select>
					</div>
				`);
			} else {
				actionFields.push(renderTargetControls('target', operation));
			}
		}

		return `
			<div class="sae-batch-op" data-batch-id="${escapeHtml(operation.id)}">
				<div class="sae-batch-op__header">
					<span class="sae-batch-op__title">Operation ${index + 1}</span>
					<div class="sae-batch-op__controls">
						<select data-batch-field="action">
							<option value="update" ${operation.action === 'update' ? 'selected' : ''}>update</option>
							<option value="insert" ${operation.action === 'insert' ? 'selected' : ''}>insert</option>
							<option value="remove" ${operation.action === 'remove' ? 'selected' : ''}>remove</option>
						</select>
						<button type="button" class="button button-secondary" data-batch-action="move-up">↑</button>
						<button type="button" class="button button-secondary" data-batch-action="move-down">↓</button>
						<button type="button" class="button button-secondary" data-batch-action="delete">Remove</button>
					</div>
				</div>
				<div class="sae-batch-op__body">
					${actionFields.join('')}
				</div>
			</div>
		`;
	}

	function renderBatchOperations() {
		if (!state.batchMode) {
			return;
		}
		if (!state.batchOperations.length) {
			els.batchList.innerHTML = '<div class="sae-empty">No operations yet. Add one operation to start.</div>';
			return;
		}
		els.batchList.innerHTML = state.batchOperations
			.map((operation, index) => renderBatchOperation(operation, index))
			.join('');
	}

	function moveBatchOperation(operationId, direction) {
		const currentIndex = state.batchOperations.findIndex((operation) => operation.id === operationId);
		if (currentIndex < 0) {
			return;
		}
		const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
		if (targetIndex < 0 || targetIndex >= state.batchOperations.length) {
			return;
		}
		const next = state.batchOperations.slice();
		const temp = next[currentIndex];
		next[currentIndex] = next[targetIndex];
		next[targetIndex] = temp;
		state.batchOperations = next;
		renderBatchOperations();
	}

	function parseJsonObject(value, fieldLabel) {
		const raw = String(value || '').trim();
		if (!raw) {
			throw new Error(`${fieldLabel} is required.`);
		}
		let parsed;
		try {
			parsed = JSON.parse(raw);
		} catch (error) {
			throw new Error(`${fieldLabel} must be valid JSON.`);
		}
		if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
			throw new Error(`${fieldLabel} must be a JSON object.`);
		}
		return parsed;
	}

	function parseIndexPath(value, fieldLabel) {
		const raw = String(value || '').trim();
		if (!raw) {
			throw new Error(`${fieldLabel} is required.`);
		}
		const parts = raw
			.split(',')
			.map((part) => part.trim())
			.filter(Boolean);
		if (!parts.length) {
			throw new Error(`${fieldLabel} is required.`);
		}
		const path = parts.map((part) => Number(part));
		if (path.some((segment) => !Number.isInteger(segment) || segment < 0)) {
			throw new Error(`${fieldLabel} must be comma-separated non-negative integers.`);
		}
		return path;
	}

	function buildTargetFromType(type, value, fieldLabel) {
		const normalizedType = String(type || '').trim();
		const normalizedValue = String(value || '').trim();
		if (!normalizedValue) {
			throw new Error(`${fieldLabel} value is required.`);
		}
		if (normalizedType === 'anchor') {
			return { anchor: normalizedValue };
		}
		if (normalizedType === 'block_id') {
			return { block_id: normalizedValue };
		}
		if (normalizedType === 'index_path') {
			return { index_path: parseIndexPath(normalizedValue, `${fieldLabel} (index_path)`) };
		}
		throw new Error(`${fieldLabel} type is invalid.`);
	}

	function buildBatchOperationPayload(operation, index) {
		const opIndex = index + 1;
		if (operation.action === 'insert') {
			if (!operation.block_name) {
				throw new Error(`Operation ${opIndex}: block type is required for insert.`);
			}
			const payload = {
				action: 'insert',
				block_name: operation.block_name,
				fields: parseJsonObject(operation.fields_text, `Operation ${opIndex} fields`),
				position: { type: operation.position_type || 'append' },
			};
			if (payload.position.type === 'before' || payload.position.type === 'after') {
				payload.position.target = buildTargetFromType(
					operation.position_target_type,
					operation.position_target_value,
					`Operation ${opIndex} position target`
				);
			}
			if ((operation.parent_path_text || '').trim() !== '') {
				payload.parent_path = parseIndexPath(operation.parent_path_text, `Operation ${opIndex} parent_path`);
			}
			return payload;
		}
		if (operation.action === 'update') {
			const fields = parseJsonObject(operation.fields_text, `Operation ${opIndex} fields`);
			if (!Object.keys(fields).length) {
				throw new Error(`Operation ${opIndex}: fields cannot be empty for update.`);
			}
			return {
				action: 'update',
				target: buildTargetFromType(operation.target_type, operation.target_value, `Operation ${opIndex} target`),
				fields,
			};
		}
		if (operation.action === 'remove') {
			if (operation.remove_all) {
				if (!operation.block_name) {
					throw new Error(`Operation ${opIndex}: block type is required when remove_all is enabled.`);
				}
				return {
					action: 'remove',
					remove_all: true,
					block_name: operation.block_name,
				};
			}
			return {
				action: 'remove',
				target: buildTargetFromType(operation.target_type, operation.target_value, `Operation ${opIndex} target`),
			};
		}
		throw new Error(`Operation ${opIndex}: action is invalid.`);
	}

	function buildBatchPayload() {
		const postId = Number(els.postId.value || 0);
		if (!postId) {
			throw new Error('Target page is required for batch mode.');
		}
		if (!state.batchOperations.length) {
			throw new Error('At least one operation is required.');
		}
		if (state.batchOperations.length > Number(config.batch_max_operations || 20)) {
			throw new Error(`Batch exceeds max operations (${config.batch_max_operations || 20}).`);
		}
		const operations = state.batchOperations.map((operation, index) => buildBatchOperationPayload(operation, index));
		const bundleName = els.batchBundleName ? String(els.batchBundleName.value || '').trim().slice(0, 80) : '';
		return { postId, operations, bundleName };
	}

	function parseSseEventBlock(block) {
		const lines = String(block || '').split(/\r?\n/);
		let event = 'message';
		const dataLines = [];
		lines.forEach((line) => {
			if (!line) {
				return;
			}
			if (line.startsWith('event:')) {
				event = line.slice(6).trim() || 'message';
				return;
			}
			if (line.startsWith('data:')) {
				dataLines.push(line.slice(5).trimStart());
			}
		});
		if (!dataLines.length) {
			return null;
		}
		const rawData = dataLines.join('\n');
		let payload = rawData;
		try {
			payload = JSON.parse(rawData);
		} catch (_error) {
			payload = rawData;
		}
		return { event, payload };
	}

	async function requestPlanViaSse(url, payload, handlers = {}, signal = null, options = {}) {
		const headers = {
			'X-WP-Nonce': config.nonce || '',
			'Content-Type': 'application/json',
			Accept: 'text/event-stream',
		};
		const controller = new AbortController();
		const timeoutMs = Number.isFinite(Number(options.timeoutMs)) && Number(options.timeoutMs) > 0
			? Number(options.timeoutMs)
			: REQUEST_TIMEOUT_MS * 2;
		const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);
		const abortListener = () => controller.abort();
		if (signal && typeof signal.addEventListener === 'function') {
			signal.addEventListener('abort', abortListener, { once: true });
		}

		let response;
		try {
			response = await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers,
				body: JSON.stringify(payload || {}),
				signal: controller.signal,
			});
		} catch (error) {
			if (error && error.name === 'AbortError') {
				if (signal && signal.aborted) {
					throw { code: 'request_aborted', message: 'Plan request cancelled.' };
				}
				throw { code: 'timeout', message: `Request timed out after ${Math.floor(timeoutMs / 1000)}s.` };
			}
			throw { code: 'network_error', message: config.strings?.network_error || 'Network request failed.' };
		} finally {
			window.clearTimeout(timeoutId);
			if (signal && typeof signal.removeEventListener === 'function') {
				signal.removeEventListener('abort', abortListener);
			}
		}

		if (!response.ok) {
			let errorPayload = null;
			try {
				errorPayload = await response.json();
			} catch (_error) {
				errorPayload = null;
			}
			throw {
				code: errorPayload && errorPayload.code ? errorPayload.code : `http_${response.status}`,
				message: errorPayload && errorPayload.message ? errorPayload.message : `Request failed (${response.status}).`,
				status: response.status,
				payload: errorPayload,
			};
		}

		const contentType = String(response.headers.get('content-type') || '').toLowerCase();
		if (!contentType.includes('text/event-stream') || !response.body) {
			const fallbackPayload = await response.json();
			return fallbackPayload;
		}

		const reader = response.body.getReader();
		const decoder = new TextDecoder('utf-8');
		let buffer = '';
		let finalResponse = null;

		while (true) {
			const { done, value } = await reader.read();
			buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
			const blocks = buffer.split(/\r?\n\r?\n/);
			buffer = blocks.pop() || '';

			blocks.forEach((block) => {
				const parsed = parseSseEventBlock(block);
				if (!parsed) {
					return;
				}
				if (typeof handlers.onEvent === 'function') {
					handlers.onEvent(parsed.event, parsed.payload);
				}
				if (parsed.event === 'error') {
					const message = parsed.payload && parsed.payload.message ? parsed.payload.message : 'Streaming plan failed.';
					const code = parsed.payload && parsed.payload.code ? parsed.payload.code : 'stream_error';
					throw { code, message, payload: parsed.payload || null };
				}
				if (parsed.event === 'result') {
					finalResponse = parsed.payload && parsed.payload.response ? parsed.payload.response : parsed.payload;
				}
			});

			if (done) {
				if (buffer.trim()) {
					const parsed = parseSseEventBlock(buffer);
					if (parsed) {
						if (typeof handlers.onEvent === 'function') {
							handlers.onEvent(parsed.event, parsed.payload);
						}
						if (parsed.event === 'error') {
							const message = parsed.payload && parsed.payload.message ? parsed.payload.message : 'Streaming plan failed.';
							const code = parsed.payload && parsed.payload.code ? parsed.payload.code : 'stream_error';
							throw { code, message, payload: parsed.payload || null };
						}
						if (parsed.event === 'result') {
							finalResponse = parsed.payload && parsed.payload.response ? parsed.payload.response : parsed.payload;
						}
					}
				}
				break;
			}
		}

		if (!finalResponse || typeof finalResponse !== 'object') {
			throw { code: 'stream_incomplete', message: 'Streaming response ended before result payload.' };
		}
		return finalResponse;
	}

	async function requestPlan(payload, handlers = {}, signal = null, options = {}) {
		const streamEnabled = !!features.plan_streaming && !options.disableStreaming;
		const streamEndpoint = rest.plan_stream ? toAbsoluteEndpoint(rest.plan_stream) : '';
		if (streamEnabled && streamEndpoint) {
			try {
				return await requestPlanViaSse(streamEndpoint, payload, handlers, signal, options);
			} catch (error) {
				if (error && error.code === 'request_aborted') {
					throw error;
				}
				if (typeof handlers.onFallback === 'function') {
					handlers.onFallback(error);
				}
			}
		}
		return requestJson(rest.plan, { method: 'POST', body: payload, signal, timeoutMs: options.timeoutMs });
	}

	function setComposeStatus(message, kind = 'info') {
		setStatusMessage(els.composeStatus, message, kind);
	}

	function ensureComposerStageChip() {
		if (els.composeStageChip) {
			return els.composeStageChip;
		}
		if (!els.generatePlan) {
			return null;
		}
		const actions = els.generatePlan.closest('.sae-actions');
		if (!actions) {
			return null;
		}
		const chip = document.createElement('span');
		chip.id = 'sae-compose-stage-chip';
		chip.className = 'sae-stage-chip';
		chip.hidden = true;
		chip.setAttribute('aria-live', 'polite');
		actions.insertBefore(chip, els.clear || null);
		els.composeStageChip = chip;
		return chip;
	}

	function setComposerStageChip(stage = '', label = '') {
		const chip = ensureComposerStageChip();
		if (!chip) {
			return;
		}
		if (state.composeStageChipTimer) {
			window.clearTimeout(state.composeStageChipTimer);
			state.composeStageChipTimer = null;
		}
		const normalized = String(stage || '').trim().toLowerCase();
		state.composeStageChipState = normalized;
		chip.classList.remove('is-parsing', 'is-planning', 'is-done');
		if (!normalized) {
			chip.hidden = true;
			chip.textContent = '';
			return;
		}
		chip.hidden = false;
		chip.classList.add(`is-${normalized}`);
		chip.textContent = label || (normalized === 'done' ? 'Done' : normalized === 'planning' ? 'Planning…' : 'Parsing…');
	}

	function flashComposerStageChipDone(label = 'Done') {
		setComposerStageChip('done', label);
		state.composeStageChipTimer = window.setTimeout(() => {
			setComposerStageChip('', '');
		}, COMPOSER_STAGE_DONE_DURATION_MS);
	}

	function setApplyStatus(message, kind = 'info') {
		setStatusMessage(els.applyStatus, message, kind);
	}

	function ensureReviewLoadingUi() {
		if (!els.reviewPanel) {
			return;
		}
		if (els.reviewLoading && els.reviewLoadingTitle && els.reviewLoadingStage && els.reviewLoadingTimeline && els.reviewLoadingRequest && els.reviewLoadingTarget) {
			return;
		}

		const overlay = document.createElement('div');
		overlay.id = 'sae-review-loading';
		overlay.className = 'sae-review-loading';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-review-loading__card" aria-live="polite">
				<p class="sae-review-loading__title" id="sae-review-loading-title">Working on it…</p>
				<p class="sae-review-loading__stage" id="sae-review-loading-stage"></p>
				<div class="sae-review-loading__bar" aria-hidden="true"><span></span></div>
				<div class="sae-review-loading__timeline" id="sae-review-loading-timeline" hidden></div>
				<p class="sae-review-loading__request" id="sae-review-loading-request"></p>
				<p class="sae-review-loading__target" id="sae-review-loading-target"></p>
			</div>
		`;
		els.reviewPanel.appendChild(overlay);
		els.reviewLoading = overlay;
		els.reviewLoadingTitle = overlay.querySelector('#sae-review-loading-title');
		els.reviewLoadingStage = overlay.querySelector('#sae-review-loading-stage');
		els.reviewLoadingTimeline = overlay.querySelector('#sae-review-loading-timeline');
		els.reviewLoadingRequest = overlay.querySelector('#sae-review-loading-request');
		els.reviewLoadingTarget = overlay.querySelector('#sae-review-loading-target');
	}

	function setReviewLoadingStage(stage = '', context = {}) {
		if (!els.reviewPanel) {
			return;
		}
		ensureReviewLoadingUi();

		els.reviewPanel.classList.remove('is-loading', 'is-loading-parsing', 'is-loading-ai');
		els.reviewPanel.removeAttribute('data-loading-stage');

		if (!stage) {
			if (els.reviewLoading) {
				els.reviewLoading.hidden = true;
			}
			if (els.reviewLoadingTitle) {
				els.reviewLoadingTitle.textContent = '';
			}
			if (els.reviewLoadingStage) {
				els.reviewLoadingStage.textContent = '';
			}
			if (els.reviewLoadingTimeline) {
				els.reviewLoadingTimeline.hidden = true;
				els.reviewLoadingTimeline.innerHTML = '';
			}
			if (els.reviewLoadingRequest) {
				els.reviewLoadingRequest.textContent = '';
			}
			if (els.reviewLoadingTarget) {
				els.reviewLoadingTarget.textContent = '';
			}
			if (state.composeStageChipState === 'parsing' || state.composeStageChipState === 'planning') {
				setComposerStageChip('', '');
			}
			state.loadingRequestText = '';
			state.loadingTargetLabel = '';
			updateConsoleUiStage();
			return;
		}

		const normalized = stage === 'ai' ? 'ai' : 'parsing';
		const createFlow = String(context.flow || '').trim().toLowerCase() === 'create';
		const createPhase = createFlow ? String(context.createPhase || 'outline').trim().toLowerCase() : '';
		setComposerStageChip(normalized === 'ai' ? 'planning' : 'parsing', createFlow ? 'Creating…' : '');
		const stageLabel = createFlow
			? (createPhase === 'save'
				? 'Create · Step 3 of 3 · Saving draft'
				: createPhase === 'content'
					? 'Create · Step 2 of 3 · Generating draft copy'
					: 'Create · Step 1 of 3 · Planning outline')
			: normalized === 'ai'
				? 'Step 2 of 2 · Asking the AI planner'
				: 'Step 1 of 2 · Parsing your request';
		const requestText = String(context.requestText || state.loadingRequestText || '').trim();
		const requestLabel = requestText ? `Request: “${truncateInlineText(requestText, 140)}”` : '';
		const targetLabel = String(context.targetLabel || state.loadingTargetLabel || '').trim();

		els.reviewPanel.classList.add('is-loading', normalized === 'ai' ? 'is-loading-ai' : 'is-loading-parsing');
		els.reviewPanel.setAttribute('data-loading-stage', normalized === 'ai' ? 'Waiting for AI response…' : 'Parsing request…');
		if (els.reviewLoadingTitle) {
			els.reviewLoadingTitle.textContent = createFlow ? 'Building your draft…' : 'Working on it…';
		}
		if (els.reviewLoadingStage) {
			els.reviewLoadingStage.textContent = stageLabel;
		}
		if (els.reviewLoadingTimeline) {
			if (createFlow) {
				els.reviewLoadingTimeline.hidden = false;
				els.reviewLoadingTimeline.innerHTML = buildCreateTimelineMarkup(createPhase || 'outline');
			} else {
				els.reviewLoadingTimeline.hidden = true;
				els.reviewLoadingTimeline.innerHTML = '';
			}
		}
		if (els.reviewLoadingRequest) {
			els.reviewLoadingRequest.textContent = requestLabel;
		}
		if (els.reviewLoadingTarget) {
			els.reviewLoadingTarget.textContent = targetLabel ? `On: ${targetLabel}` : '';
		}
		if (els.reviewLoading) {
			els.reviewLoading.hidden = false;
		}
		updateConsoleUiStage('loading');
	}

		function clearTokenCountdown() {
			if (state.tokenInterval) {
				window.clearInterval(state.tokenInterval);
				state.tokenInterval = null;
			}
			state.tokenExpiresAtMs = 0;
			if (els.apply && state.applyLabelBase) {
				els.apply.textContent = state.applyLabelBase;
			}
			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = '';
			}
		}

		function renderTokenCountdown() {
			if (!state.tokenExpiresAtMs) {
				if (els.tokenExpiry) {
					els.tokenExpiry.textContent = '';
				}
				if (els.apply && state.applyLabelBase) {
					els.apply.textContent = state.applyLabelBase;
				}
				updateApplyButtonState();
				return;
			}

			const remainingMs = Math.max(0, state.tokenExpiresAtMs - Date.now());
			const seconds = Math.floor(remainingMs / 1000);
			const minutes = Math.floor(seconds / 60);
			const remainder = seconds % 60;
			const countdown = `${minutes}:${String(remainder).padStart(2, '0')}`;
			const text = `Expires in ${countdown}`;
			const color = seconds <= 15 ? '#ef4444' : seconds <= 60 ? BRAND_ACCENT : BRAND_PRIMARY;

			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = text;
				els.tokenExpiry.style.color = color;
			}
			if (els.apply && state.applyLabelBase) {
				els.apply.textContent = isSimpleMode() ? state.applyLabelBase : `${state.applyLabelBase} (${countdown})`;
			}

			if (seconds <= 0) {
				setApplyStatus(isSimpleMode() ? 'This change expired. Start a new one.' : config.strings?.token_expired || 'Confirmation token expired. Generate a new plan.', 'warn');
				clearTokenCountdown();
			}

		updateApplyButtonState();
	}

	function startTokenCountdown(expiresAtIso) {
		clearTokenCountdown();
		if (!expiresAtIso) {
			return;
		}
		const expiresAtMs = Date.parse(expiresAtIso);
		if (Number.isNaN(expiresAtMs)) {
			return;
		}
		state.tokenExpiresAtMs = expiresAtMs;
		renderTokenCountdown();
		state.tokenInterval = window.setInterval(renderTokenCountdown, 1000);
	}

	function classifyRisk(operation) {
		if (operation === 'remove') {
			return { label: 'destructive', className: 'sae-badge--danger' };
		}
		if (operation === 'batch') {
			return { label: 'structure-sensitive', className: 'sae-badge--warn' };
		}
		return { label: 'content-only', className: '' };
	}

	function getPostLabelById(postId) {
		const numericPostId = Number(postId);
		if (!numericPostId) {
			return '--';
		}

		const post = getAllowlistedPostById(numericPostId);

		if (!post) {
			return String(numericPostId);
		}

		return `${post.post_title || post.post_slug || 'Untitled'} (${numericPostId})`;
	}

	function summarizeHistoryDetails(details) {
		if (!details || typeof details !== 'object') {
			return '--';
		}

		const summary = [];
		if (Object.prototype.hasOwnProperty.call(details, 'score') && Number.isFinite(Number(details.score))) {
			summary.push(`score: ${Number(details.score)}/10`);
		}
		if (Object.prototype.hasOwnProperty.call(details, 'issue_count') && Number.isFinite(Number(details.issue_count))) {
			summary.push(`issues: ${Number(details.issue_count)}`);
		}
		if (details.block_name) {
			summary.push(`block: ${details.block_name}`);
		}
		if (typeof details.delta !== 'undefined') {
			summary.push(`delta: ${details.delta}`);
		}
		if (details.target) {
			summary.push(`target: ${JSON.stringify(details.target)}`);
		}
		if (details.origin) {
			summary.push(`origin: ${details.origin}`);
		}
		if (details.agent_plan_id) {
			summary.push(`agent_plan: ${details.agent_plan_id}`);
		}
		if (details.endpoint) {
			summary.push(`endpoint: ${details.endpoint}`);
		}

		if (summary.length) {
			return summary.slice(0, 3).join(' · ');
		}

		return Object.keys(details)
			.slice(0, 2)
			.map((key) => `${key}: ${JSON.stringify(details[key])}`)
			.join(' · ');
	}

	function getHistoryActionLabel(action) {
		const normalized = String(action || '').trim().toLowerCase();
		const labels = {
			plan: 'Plan generated',
			agent_plan_preview: 'Agent plan previewed',
			agent_plan_dismiss: 'Agent plan dismissed',
			plan_audit: 'Page audited',
			plan_cross_field: 'Field update planned',
			update: 'Content updated',
			insert: 'Block inserted',
			remove: 'Block removed',
			batch: 'Batch applied',
			field_update: 'Field updated',
			dry_run_update: 'Update previewed',
			dry_run_insert: 'Insert previewed',
			dry_run_remove: 'Removal previewed',
			catalog_read: 'Catalog viewed',
			post_blocks_read: 'Page blocks viewed',
		};
		if (labels[normalized]) {
			return labels[normalized];
		}
		if (!normalized) {
			return 'Activity';
		}
		return normalized
			.replace(/_/g, ' ')
			.replace(/\b\w/g, (char) => char.toUpperCase());
	}

	function buildSimpleHistoryMeta(details, item) {
		const meta = [];
		if (details && Object.prototype.hasOwnProperty.call(details, 'score') && Number.isFinite(Number(details.score))) {
			meta.push(`Score: ${Number(details.score)}/10`);
		}
		if (details && Object.prototype.hasOwnProperty.call(details, 'issue_count') && Number.isFinite(Number(details.issue_count))) {
			meta.push(`Issues: ${Number(details.issue_count)}`);
		}
		if (details && details.field) {
			meta.push(`Field: ${humanizeFieldName(details.field)}`);
		}
		if (details && Object.prototype.hasOwnProperty.call(details, 'delta') && Number.isFinite(Number(details.delta))) {
			const delta = Number(details.delta);
			const deltaLabel = delta > 0 ? `+${delta}` : `${delta}`;
			meta.push(`Blocks: ${deltaLabel}`);
		}
		if (details && details.bundle_name) {
			meta.push(`Bundle: ${String(details.bundle_name).trim()}`);
		}
		if (item && (item.user_label || item.user_id)) {
			meta.push(`By ${item.user_label || item.user_id}`);
		}
		return meta.filter(Boolean).join(' · ');
	}

	function formatDiffValue(value) {
		if (typeof value === 'undefined' || value === null) {
			return '(empty)';
		}
		if (typeof value === 'string') {
			return value.length > 140 ? `${value.slice(0, 137)}...` : value;
		}
		return JSON.stringify(value);
	}

	function normalizePathForCompare(value) {
		if (Array.isArray(value)) {
			return value
				.map((item) => Number(item))
				.filter((item) => Number.isFinite(item));
		}
		if (typeof value === 'string') {
			const trimmed = value.trim();
			if (!trimmed) {
				return [];
			}
			try {
				const parsed = JSON.parse(trimmed);
				if (Array.isArray(parsed)) {
					return parsed
						.map((item) => Number(item))
						.filter((item) => Number.isFinite(item));
				}
			} catch (error) {
				/* fall through to delimiter parsing */
			}
			return trimmed
				.replace(/^\[|\]$/g, '')
				.split(',')
				.map((part) => Number(String(part).trim()))
				.filter((item) => Number.isFinite(item));
		}
		return [];
	}

	function samePath(left, right) {
		const normalizedLeft = normalizePathForCompare(left);
		const normalizedRight = normalizePathForCompare(right);
		if (!normalizedLeft.length || !normalizedRight.length) {
			return false;
		}
		if (normalizedLeft.length !== normalizedRight.length) {
			return false;
		}
		for (let index = 0; index < normalizedLeft.length; index += 1) {
			if (normalizedLeft[index] !== normalizedRight[index]) {
				return false;
			}
		}
		return true;
	}

	function findBlockByTarget(blocks, target = {}) {
		if (!Array.isArray(blocks) || !target || typeof target !== 'object') {
			return null;
		}
		const targetPath = normalizePathForCompare(target.index_path);
		const targetAnchor = String(target.anchor || '').trim();
		const targetBlockId = String(target.block_id || '').trim();

		for (const block of blocks) {
			if (!block || typeof block !== 'object') {
				continue;
			}
			const blockAttrs = block.attrs && typeof block.attrs === 'object' ? block.attrs : {};
			if (targetPath.length && samePath(block.index_path, targetPath)) {
				return block;
			}
			if (targetAnchor && String(block.anchor || blockAttrs.anchor || '').trim() === targetAnchor) {
				return block;
			}
			if (targetBlockId && String(block.block_id || blockAttrs.id || '').trim() === targetBlockId) {
				return block;
			}
			const nested = findBlockByTarget(Array.isArray(block.inner_blocks) ? block.inner_blocks : [], target);
			if (nested) {
				return nested;
			}
		}

		return null;
	}

	function buildInlineWordDiffHtml(beforeText, afterText) {
		const before = String(beforeText || '');
		const after = String(afterText || '');
		if (!before || !after || before === after) {
			return {
				beforeHtml: escapeHtml(before),
				afterHtml: escapeHtml(after),
			};
		}

		const beforeParts = before.split(/\s+/);
		const afterParts = after.split(/\s+/);
		let start = 0;
		while (start < beforeParts.length && start < afterParts.length && beforeParts[start] === afterParts[start]) {
			start += 1;
		}

		let endBefore = beforeParts.length - 1;
		let endAfter = afterParts.length - 1;
		while (endBefore >= start && endAfter >= start && beforeParts[endBefore] === afterParts[endAfter]) {
			endBefore -= 1;
			endAfter -= 1;
		}

		const prefix = beforeParts.slice(0, start).join(' ');
		const suffix = beforeParts.slice(endBefore + 1).join(' ');
		const removed = beforeParts.slice(start, endBefore + 1).join(' ');
		const added = afterParts.slice(start, endAfter + 1).join(' ');

		const assemble = (head, middleHtml, tail) => {
			return [head ? escapeHtml(head) : '', middleHtml || '', tail ? escapeHtml(tail) : '']
				.filter(Boolean)
				.join(' ')
				.trim();
		};

		return {
			beforeHtml: assemble(prefix, removed ? `<span class="sae-diff-removed">${escapeHtml(removed)}</span>` : '', suffix),
			afterHtml: assemble(prefix, added ? `<span class="sae-diff-added">${escapeHtml(added)}</span>` : '', suffix),
		};
	}

	async function renderPlanDiff(plan) {
			els.planDiffWrap.hidden = true;
			els.planDiffList.innerHTML = '';
			hideBundleReview();

		if (!plan || !['update', 'batch'].includes(plan.operation)) {
			return;
		}

		const postId = Number(plan.post && plan.post.post_id ? plan.post.post_id : 0);
		const target = plan.payload && plan.payload.target && typeof plan.payload.target === 'object'
			? plan.payload.target
			: null;
		const targetPath = target ? normalizePathForCompare(target.index_path) : [];
		const targetAnchor = target ? String(target.anchor || '').trim() : '';
		const targetBlockId = target ? String(target.block_id || '').trim() : '';
		const fields = plan.payload && plan.payload.fields && typeof plan.payload.fields === 'object'
			? plan.payload.fields
			: null;
		const batchOperations = plan.payload && Array.isArray(plan.payload.operations)
			? plan.payload.operations
			: [];

			if (
				!postId
				|| (
					plan.operation === 'update'
						? (!target || (!targetPath.length && !targetAnchor && !targetBlockId) || !fields || !Object.keys(fields).length)
						: !batchOperations.length
				)
			) {
				return;
			}

		const currentRequestId = state.planDiffRequestId + 1;
		state.planDiffRequestId = currentRequestId;
		els.planDiffWrap.hidden = false;
		els.planDiffList.innerHTML = '<p class="sae-diff-empty">Loading current values…</p>';

		try {
			const blocks = await fetchPostBlocks(postId);
			if (state.planDiffRequestId !== currentRequestId || plan !== state.plan) {
				return;
			}
			const targetBlock = plan.operation === 'update' ? findBlockByTarget(blocks, target) : null;
			const currentValues = targetBlock && targetBlock.fields && typeof targetBlock.fields === 'object'
				? targetBlock.fields
				: targetBlock && targetBlock.attrs && targetBlock.attrs.data && typeof targetBlock.attrs.data === 'object'
					? targetBlock.attrs.data
					: {};
			const simplePreviewRows = plan.operation === 'batch'
				? buildBatchPreviewRows(plan, blocks)
				: buildSimplePreviewRows(plan, currentValues);
			state.planPreviewRows = Array.isArray(simplePreviewRows) ? simplePreviewRows.map((item) => Object.assign({}, item)) : [];
			renderSimplePreview(plan, simplePreviewRows);
			renderSimpleSummary(plan, simplePreviewRows);

			const rows = (
				plan.operation === 'batch'
					? simplePreviewRows
					: Object.entries(fields)
						.map(([fieldName, nextValue]) => {
							const resolvedCurrent = resolveCurrentFieldValue(fieldName, currentValues);
							const previousValue = resolvedCurrent.found ? resolvedCurrent.value : null;
							const changed = resolvedCurrent.found
								? JSON.stringify(previousValue) !== JSON.stringify(nextValue)
								: true;
							const previousText = formatDiffValue(previousValue);
							const nextText = formatDiffValue(nextValue);
							const shouldInlineDiff = resolvedCurrent.found && changed && typeof previousValue === 'string' && typeof nextValue === 'string';
							const inline = shouldInlineDiff ? buildInlineWordDiffHtml(previousText, nextText) : null;
							return [{
								fieldName,
								displayFieldName: getPreviewDisplayFieldName(plan, {
									displayFieldName: resolvedCurrent.matchedField || fieldName,
									fieldName,
								}),
								targetContextLabel: getPlanTargetContextLabel(plan),
								beforeText: previousText,
								afterText: nextText,
								beforeHtml: inline ? inline.beforeHtml : '',
								afterHtml: inline ? inline.afterHtml : '',
								hasInlineDiff: !!inline,
								tone: getPreviewTone(previousValue, nextValue, resolvedCurrent.found),
								changed,
							}];
						})
						.flat()
			).map((item, index) => {
				const displayFieldLabel = humanizeFieldName(item.displayFieldName || item.fieldName);
				const targetContext = String(item.targetContextLabel || '').trim();
				const tone = String(item.tone || 'modify').trim().toLowerCase();
				return `
					<div class="sae-diff-item sae-diff-item--${escapeHtml(tone)} sae-progressive-card" style="--sae-row-index:${index};--sae-stagger-index:${index}">
						<div class="sae-diff-item__header">
							<p class="sae-diff-item__field">${escapeHtml(displayFieldLabel)}</p>
							${targetContext ? `<span class="sae-simple-preview-item__context">${escapeHtml(targetContext)}</span>` : ''}
						</div>
						<div class="sae-diff-values">
							<span class="sae-diff-before ${item.changed ? '' : 'is-same'}">${item.hasInlineDiff ? item.beforeHtml : escapeHtml(item.beforeText)}</span>
							<span class="sae-diff-arrow">→</span>
							<span class="sae-diff-after ${item.changed ? '' : 'is-same'}">${item.hasInlineDiff ? item.afterHtml : escapeHtml(item.afterText)}</span>
						</div>
					</div>
				`;
			});

			els.planDiffList.innerHTML = rows.length ? rows.join('') : '<p class="sae-diff-empty">No field-level changes detected.</p>';
		} catch (error) {
			if (state.planDiffRequestId !== currentRequestId || plan !== state.plan) {
				return;
			}
			state.planPreviewRows = [];
			const fallbackPreviewRows = plan.operation === 'batch'
				? buildBatchPreviewRows(plan)
				: buildSimplePreviewRows(plan);
			renderSimplePreview(plan, fallbackPreviewRows);
			renderSimpleSummary(plan, fallbackPreviewRows);
			els.planDiffList.innerHTML = `<p class="sae-diff-empty">Unable to load field diff: ${escapeHtml(error.message || 'unknown error')}</p>`;
		}
	}
	function renderPlan() {
		clearProgressiveRenderTimers();
		clearStreamedReviewPreview();
		const plan = state.plan;
		if (!plan) {
			const receiptVisible = !!(els.receipt && !els.receipt.hidden);
			if (els.reviewTitle) {
				els.reviewTitle.textContent = isSimpleMode() ? MODE_COPY.simple.reviewTitle : MODE_COPY.dev.reviewTitle;
			}
			if (els.reviewSubtitle) {
				els.reviewSubtitle.textContent = isSimpleMode()
					? MODE_COPY.simple.reviewSubtitle
					: MODE_COPY.dev.reviewSubtitle;
			}
			setFadeVisibility(els.planEmpty, !receiptVisible);
			setFadeVisibility(els.planContent, false);
			updateQuickstartVisibility();
			updateEmptyStateCopy();

			if (els.planRequest) {
				els.planRequest.hidden = true;
				els.planRequest.textContent = '';
			}
			if (els.simpleSummary) {
				els.simpleSummary.hidden = true;
			}
			if (els.simpleSummaryEyebrow) {
				els.simpleSummaryEyebrow.textContent = 'Change at a glance';
			}
			if (els.simpleSummaryStatus) {
				els.simpleSummaryStatus.hidden = true;
				els.simpleSummaryStatus.innerHTML = '';
			}
			if (els.simpleSummaryHeadline) {
				els.simpleSummaryHeadline.textContent = '';
			}
			if (els.simpleSummaryDetails) {
				els.simpleSummaryDetails.textContent = '';
			}
			if (els.simpleSummaryGlance) {
				els.simpleSummaryGlance.hidden = true;
				els.simpleSummaryGlance.innerHTML = '';
			}
			if (els.simpleSummarySignals) {
				els.simpleSummarySignals.hidden = true;
			}
			if (els.simpleSummaryConfidence) {
				els.simpleSummaryConfidence.className = 'sae-confidence-pill';
				els.simpleSummaryConfidence.textContent = '';
			}
			if (els.simpleSummaryScope) {
				els.simpleSummaryScope.hidden = true;
				els.simpleSummaryScope.textContent = '';
			}
			if (els.simpleSummarySafety) {
				els.simpleSummarySafety.hidden = true;
				els.simpleSummarySafety.textContent = '';
			}
			if (els.simplePreview) {
				els.simplePreview.hidden = true;
			}
			if (els.simplePreviewList) {
				els.simplePreviewList.innerHTML = '';
			}
			if (els.reviewPanel) {
				els.reviewPanel.dataset.planner = '';
				els.reviewPanel.dataset.hasPlan = '0';
			}
			if (els.askWrap) {
				els.askWrap.hidden = true;
			}
			if (els.askList) {
				els.askList.innerHTML = '';
			}
			if (els.planPayloadWrap) {
				els.planPayloadWrap.hidden = false;
			}
			if (els.planSummary) {
				els.planSummary.hidden = false;
			}
			els.dryRunWrap.hidden = false;
			if (els.planActions) {
				els.planActions.hidden = false;
			}
			els.planDiffWrap.hidden = true;
			els.planDiffList.innerHTML = '';
			hideBundleReview();
			if (els.vectorSourcesWrap) {
				els.vectorSourcesWrap.hidden = true;
			}
			if (els.vectorSourcesList) {
				els.vectorSourcesList.innerHTML = '';
			}
			if (els.tokenWrap) {
				els.tokenWrap.hidden = true;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = '';
			}
			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = '';
			}
			clearTokenCountdown();
			updateApplyButtonLabel(null);
			updateApplyButtonState();
			updateConsoleUiStage();
			setApplyStatus('');
			return;
		}

		if (els.reviewTitle) {
			els.reviewTitle.textContent = isSimpleMode() ? MODE_COPY.simple.reviewTitle : MODE_COPY.dev.reviewTitle;
		}
		if (els.reviewSubtitle) {
			els.reviewSubtitle.textContent = isSimpleMode() ? MODE_COPY.simple.reviewSubtitle : MODE_COPY.dev.reviewSubtitle;
		}
		setFadeVisibility(els.planEmpty, false);
		setFadeVisibility(els.planContent, true);
		updateQuickstartVisibility();
		setApplyStatus('');

		const isAskIntent = plan.intent === 'ask' && !!els.askWrap && !!els.askList;
		const isCreateIntent = isCreateOutlinePlan(plan) && !!els.askWrap && !!els.askList;
		const isAuditIntent = isAuditPlan(plan) && !!els.askWrap && !!els.askList;
		const simpleModeActive = isSimpleMode();
		if (isBundlePlan(plan)) {
			renderBundleReview(plan, simpleModeActive);
			return;
		}
		const post = plan.post || {};
		const operation = plan.operation || '';
		const risk = classifyRisk(operation);
		const planner = plan.planner || {};
		const dryRun = plan.dry_run || null;
		const confirmation = dryRun && dryRun.confirmation ? dryRun.confirmation : null;
		const destructive = requiresDestructiveConfirm(plan);

		if (els.planRequest) {
				let requestText = '';
				if (plan && plan.request) {
					requestText = plan.request === '[batch-composer]'
						? 'Batch operations (composer)'
						: String(plan.request);
				}
			els.planRequest.textContent = requestText;
			els.planRequest.hidden = !requestText;
		}

		if (simpleModeActive && els.reviewTitle && els.reviewSubtitle) {
			if (isAuditIntent) {
				els.reviewTitle.textContent = 'Review audit';
				els.reviewSubtitle.textContent = 'See the score, top issues, and the safest next fix.';
			} else if (isAskIntent) {
				els.reviewTitle.textContent = 'Review suggestions';
				els.reviewSubtitle.textContent = 'Pick the strongest direction, then turn it into a safe plan.';
			} else if (isCreateIntent) {
				const createPostType = String(plan && plan.post_type ? plan.post_type : '').trim().toLowerCase() === 'post' ? 'post' : 'page';
				els.reviewTitle.textContent = createPostType === 'post' ? 'Review blog draft plan' : 'Review page draft plan';
				els.reviewSubtitle.textContent = createPostType === 'post'
					? 'Check the structure before creating the blog draft.'
					: 'Check the structure before creating the draft.';
			} else {
				els.reviewTitle.textContent = 'Review change';
				els.reviewSubtitle.textContent = 'Nothing is live yet. Confirm the preview, then apply when ready.';
			}
		}

		if (isCreateIntent) {
			const outline = Array.isArray(plan.outline) ? plan.outline : [];
			const title = String(plan.title || '').trim() || 'Untitled draft';
			const createPostType = String(plan && plan.post_type ? plan.post_type : '').trim().toLowerCase() === 'post' ? 'post' : 'page';
			const createLabel = createPostType === 'post' ? 'blog post' : 'page';
			const templateMode = String(plan.template_mode || '').trim().toLowerCase() || 'custom';
			const templateLabel = String(plan.template_label || '').trim();
			const templateRationale = String(plan.template_rationale || '').trim();
			const templateKey = String(plan.template || '').trim();
			ensureCreateOutlineSelection(outline.length);
			const selectedSections = getSelectedOutlineSectionIndexes();
			const selectedCount = selectedSections.length;
			const hasSelection = selectedCount > 0;
			const allSelected = outline.length > 0 && selectedCount === outline.length;
			const toggleLabel = allSelected ? 'Deselect all' : 'Select all';
			const continueLabel = simpleModeActive
				? `Create ${createLabel}`
				: `Create ${createLabel} (${selectedCount} section${selectedCount === 1 ? '' : 's'})`;
			const subtitle = simpleModeActive
				? "Uncheck any sections you don't need."
				: 'Review the outline, choose sections, then continue to draft creation.';
			setAskPanelCopy(
				simpleModeActive ? "Here's a suggested outline" : `Outline — ${outline.length} section${outline.length === 1 ? '' : 's'}`,
				subtitle
			);

			if (els.askWrap) {
				els.askWrap.hidden = false;
			}
			if (els.askList) {
				const structureSummary = buildTemplateChoiceMarkup({
					mode: templateMode,
					label: templateLabel,
					rationale: templateRationale,
					templateKey,
					showKey: !simpleModeActive,
				});
				const cards = outline
					.map((item, index) => {
						const section = item && item.section ? String(item.section) : `Section ${index + 1}`;
						const purpose = item && item.purpose ? String(item.purpose) : 'Purpose not provided.';
						const blockName = item && item.block_name ? String(item.block_name) : '';
						const reasoning = item && item.reasoning ? String(item.reasoning) : '';
						const checked = !!state.createOutlineSelection[index];
						const devMeta = !simpleModeActive && blockName
							? `<p class="sae-create-outline-item__meta"><code>${escapeHtml(blockName)}</code></p>`
							: '';
						const devReasoning = !simpleModeActive && reasoning
							? `<p class="sae-create-outline-item__reasoning">${escapeHtml(reasoning)}</p>`
							: '';
						return `
							<label class="sae-create-outline-item sae-progressive-card ${checked ? 'is-checked' : 'is-unchecked'}">
								<span class="sae-create-outline-item__select">
									<input type="checkbox" data-outline-index="${index}" ${checked ? 'checked' : ''} />
								</span>
								<span class="sae-create-outline-item__body">
									<p class="sae-create-outline-item__index">Section ${index + 1}</p>
									<h4>${escapeHtml(section)}</h4>
									<p>${escapeHtml(purpose)}</p>
									${devMeta}
									${devReasoning}
								</span>
							</label>
						`;
					});
				els.askList.innerHTML = `
					${structureSummary}
					<div class="sae-create-outline-controls">
						<button type="button" class="button button-secondary" data-create-outline-action="toggle-all">${toggleLabel}</button>
						<p class="sae-help">${selectedCount} of ${outline.length} section${outline.length === 1 ? '' : 's'} selected</p>
					</div>
					<div class="sae-create-outline-list" data-progressive-list="outline"></div>
					<div class="sae-create-outline-actions">
						<button type="button" class="button button-primary" data-create-outline-action="continue" ${state.createOutlineActive || !hasSelection ? 'disabled' : ''}>
							${state.createOutlineActive ? 'Creating draft…' : continueLabel}
						</button>
						<p class="sae-help">${hasSelection ? 'Creates a draft using selected sections and keeps safety checks.' : 'Select at least one section to continue.'}</p>
					</div>
					<p class="sae-create-outline-title">Draft title: ${escapeHtml(title)}</p>
				`;
				const outlineList = els.askList.querySelector('[data-progressive-list="outline"]');
				progressivelyRenderHtmlCards(outlineList, cards, {
					intervalMs: 90,
					emptyHtml: '<p class="sae-empty">No outline sections returned. Try rephrasing your request.</p>',
				});
			}

			if (els.simpleSummary) {
				els.simpleSummary.hidden = true;
				if (els.simpleSummaryStatus) {
					els.simpleSummaryStatus.hidden = true;
					els.simpleSummaryStatus.innerHTML = '';
				}
				els.simpleSummaryHeadline.textContent = '';
				els.simpleSummaryDetails.textContent = '';
			}
			if (els.simplePreview) {
				els.simplePreview.hidden = true;
				els.simplePreviewList.innerHTML = '';
			}
			if (els.planSummary) {
				els.planSummary.hidden = true;
				els.planSummary.innerHTML = '';
			}
			if (els.planBadges) {
				els.planBadges.innerHTML = '';
			}
			if (els.planWarnings) {
				els.planWarnings.innerHTML = '';
			}
			if (els.planPayloadWrap) {
				els.planPayloadWrap.hidden = true;
			}
			if (els.devDetails) {
				els.devDetails.hidden = true;
				els.devDetails.removeAttribute('open');
			}
			els.dryRunWrap.hidden = true;
			els.planDryRun.textContent = '';
			els.planPayload.textContent = '';
			els.planDiffWrap.hidden = true;
			els.planDiffList.innerHTML = '';
			hideBundleReview();
			if (els.vectorSourcesWrap) {
				els.vectorSourcesWrap.hidden = true;
			}
			if (els.vectorSourcesList) {
				els.vectorSourcesList.innerHTML = '';
			}
			if (els.tokenWrap) {
				els.tokenWrap.hidden = true;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = '';
			}
			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = '';
			}
			if (els.planActions) {
				els.planActions.hidden = true;
			}
			els.removeConfirmWrap.hidden = true;
			clearTokenCountdown();
			updateApplyButtonLabel(null);
			updateApplyButtonState();
			updateConsoleUiStage('review');
			return;
		}

		if (els.planSummary) {
			els.planSummary.hidden = simpleModeActive;
		}
		const simplePreviewRows = !isAuditIntent
			? (operation === 'cross_field'
				? buildSimplePreviewRows(plan)
				: operation === 'batch'
					? buildBatchPreviewRows(plan)
					: [])
			: [];
		if (plan.operation === 'cross_field') {
			state.planPreviewRows = Array.isArray(simplePreviewRows) ? simplePreviewRows.map((item) => Object.assign({}, item)) : [];
		}
		const transparency = buildPlanTransparency(plan, simplePreviewRows);
		renderSimpleSummary(plan, simplePreviewRows);
		renderSimplePreview(plan, simplePreviewRows);

		if (els.reviewPanel) {
			els.reviewPanel.dataset.planner = planner.source || '';
			els.reviewPanel.dataset.hasPlan = '1';
		}

		const summaryRows = [
			['Intent', isAuditIntent ? 'audit' : (isAskIntent ? 'ask' : 'do')],
			['Operation', operation || (isAskIntent ? 'ask' : (isAuditIntent ? 'audit' : '--'))],
			['Planner', planner.source || '--'],
			['Resolved page', post.post_title || post.post_slug || post.post_id || '--'],
			['Post ID', post.post_id || '--'],
		];
		if (!isAskIntent && operation === 'batch') {
			const bundleName = plan.payload && plan.payload.bundle_name ? String(plan.payload.bundle_name).trim() : '';
			if (bundleName) {
				summaryRows.push(['Bundle', bundleName]);
			}
		}
		if (isAuditIntent) {
			summaryRows.push(['Score', plan.audit && plan.audit.score ? `${plan.audit.score}/10` : '--']);
			summaryRows.push(['Issues', getAuditIssues(plan).length || '--']);
		} else if (!isAskIntent) {
			summaryRows.push(['Endpoint', plan.endpoint || '--']);
			if (operation === 'cross_field') {
				const fieldLabel = humanizeFieldName(plan.payload && plan.payload.field ? plan.payload.field : '');
				summaryRows.push(['Field', fieldLabel || '--']);
			} else {
				summaryRows.push(['Blocks changed', dryRun && dryRun.change ? dryRun.change.delta : '--']);
			}
		}

		updateConsoleUiStage('review');

		const visibleSummaryRows = isAskIntent
			? summaryRows.filter(([, value]) => String(value || '').trim() !== '--')
			: summaryRows;

		els.planSummary.innerHTML = visibleSummaryRows
			.map(([label, value]) => `<dl class="sae-kv-item"><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></dl>`)
			.join('');

		const badges = [];
		if (isAuditIntent && plan.audit && plan.audit.score) {
			badges.push(
				`<span class="sae-badge sae-badge--audit-${escapeHtml(getAuditScoreTone(Number(plan.audit.score) || 0))}">score: ${escapeHtml(`${plan.audit.score}/10`)}</span>`
			);
			badges.push('<span class="sae-badge">mode: audit</span>');
		} else if (!isAskIntent) {
			badges.push(`<span class="sae-badge ${risk.className}">risk: ${escapeHtml(risk.label)}</span>`);
		}
		badges.push(`<span class="sae-badge">${escapeHtml(getPlannerBadgeLabel(planner))}</span>`);

		const rewriteMeta = planner && planner.rewrite && typeof planner.rewrite === 'object' ? planner.rewrite : null;
		if (rewriteMeta && rewriteMeta.tone_phrase) {
			badges.push(`<span class="sae-badge">tone: ${escapeHtml(rewriteMeta.tone_phrase)}</span>`);
		}
		if (rewriteMeta && (rewriteMeta.field_label || rewriteMeta.field_name)) {
			const fieldLabel = String(rewriteMeta.field_label || rewriteMeta.field_name || '')
				.trim()
				.replace(/_/g, ' ');
			const inferred = rewriteMeta.field_inferred ? ' (inferred)' : '';
			if (fieldLabel) {
				badges.push(`<span class="sae-badge">rewrite: ${escapeHtml(fieldLabel)}${escapeHtml(inferred)}</span>`);
			}
		}

		if (plan.agent_plan && plan.agent_plan.id) {
			badges.push('<span class="sae-badge">from: agent</span>');
		}

		if (isAskIntent) {
			badges.push('<span class="sae-badge">mode: ideation</span>');
		} else if (dryRun) {
			badges.push('<span class="sae-badge">mode: dry-run ready</span>');
		}
		badges.push(`<span class="sae-badge">confidence: ${escapeHtml(transparency.confidence.label || formatConfidenceLabel('medium')).toLowerCase()}</span>`);
		const vectorMeta = planner && planner.vector && typeof planner.vector === 'object' ? planner.vector : null;
		if (vectorMeta && vectorMeta.enriched) {
			const matchCount = Number(vectorMeta.match_count) || 0;
			badges.push(
				`<span class="sae-badge sae-badge--vector">context: ${matchCount} similar page${matchCount === 1 ? '' : 's'}</span>`
			);
		}

		els.planBadges.innerHTML = badges.join('');

		const warnings = normalizePlannerWarnings(planner.warnings, simpleModeActive, plan.intent);
		if (!isAskIntent && !isAuditIntent && simpleModeActive && destructive) {
			warnings.unshift('This action removes content. You will get a final confirmation before apply.');
		}
		els.planWarnings.innerHTML = warnings
			.map((warning) => `<div class="sae-warning">${escapeHtml(formatPlannerWarning(warning, simpleModeActive))}</div>`)
			.join('');
		if (els.vectorSourcesWrap && els.vectorSourcesList) {
			const vectorSources = Array.isArray(plan.vector_sources) ? plan.vector_sources : [];
			if (vectorSources.length) {
				els.vectorSourcesList.innerHTML = vectorSources
					.map((source) => {
						const title = source && source.title ? String(source.title) : '';
						if (!title) {
							return '';
						}
						const score = source && typeof source.score !== 'undefined' && source.score !== null
							? Number(source.score)
							: null;
						const scoreLabel = Number.isFinite(score) ? `${score.toFixed(2)}` : '';
						return `<li class="sae-vector-source"><span>${escapeHtml(title)}</span>${scoreLabel ? `<span class="sae-vector-source__score">${escapeHtml(scoreLabel)}</span>` : ''}</li>`;
					})
					.filter(Boolean)
					.join('');
				els.vectorSourcesWrap.hidden = !els.vectorSourcesList.innerHTML;
			} else {
				els.vectorSourcesWrap.hidden = true;
				els.vectorSourcesList.innerHTML = '';
			}
		}

		if (els.devDetails) {
			if (isAskIntent || simpleModeActive) {
				els.devDetails.hidden = true;
				els.devDetails.removeAttribute('open');
			} else {
				els.devDetails.hidden = false;
			}
		}

		if (isAskIntent) {
			setAskPanelCopy('Suggestions', 'Pick one suggestion to turn it into an editable request.');
			const suggestions = Array.isArray(plan.suggestions) ? plan.suggestions : [];
			els.askWrap.hidden = false;
			const suggestionCards = suggestions
				.map((item, index) => {
						const title = item && item.title ? item.title : `Suggestion ${index + 1}`;
						const rationale = item && item.rationale ? item.rationale : '';
						const command = item && item.command ? item.command : '';
						const candidateValue = getSuggestionCandidateValue(item);
						const canAutoApply = canAutoApplySuggestion(item);
						const applyLabel = simpleModeActive ? 'Apply' : 'Generate plan';
						const persuasionLabel = item && item.persuasion_label ? formatPersuasionLabel(item.persuasion_label) : '';
						const score = item && typeof item.score !== 'undefined' && item.score !== null ? Number(item.score) : null;
						const hasScore = Number.isFinite(score);
						const isRecommended = index === 0;
						const rationaleText = rationale || (persuasionLabel ? `${persuasionLabel} angle for this page section.` : '');
						const suggestionMeta = [];
						if (isRecommended) {
							suggestionMeta.push('<span class="sae-suggestion-badge sae-suggestion-badge--recommended">Recommended</span>');
						}
						if (persuasionLabel) {
							suggestionMeta.push(`<span class="sae-suggestion-badge">${escapeHtml(persuasionLabel)}</span>`);
						}
						if (hasScore) {
							suggestionMeta.push(`<span class="sae-suggestion-badge sae-suggestion-badge--score">score ${escapeHtml(score.toFixed(2))}</span>`);
						}
						const candidateMarkup = candidateValue
							? simpleModeActive
								? `<p class="sae-suggestion-proposal">"${escapeHtml(candidateValue)}"</p>`
								: `<pre class="sae-suggestion-candidate">${escapeHtml(candidateValue)}</pre>`
							: '';
						return `
							<article class="sae-suggestion-item${isRecommended ? ' is-recommended' : ''}" style="--sae-stagger-index:${index}">
								<h4>${escapeHtml(title)}</h4>
								${suggestionMeta.length ? `<div class="sae-suggestion-meta">${suggestionMeta.join('')}</div>` : ''}
								${rationaleText ? `<p>${escapeHtml(rationaleText)}</p>` : ''}
								${candidateMarkup}
								${simpleModeActive ? '' : `<code>${escapeHtml(command)}</code>`}
								<div class="sae-suggestion-actions">
									<button type="button" class="button button-primary" data-suggestion-index="${index}" data-suggestion-action="apply"${canAutoApply ? '' : ' disabled title="This suggestion needs a concrete replacement value before it can generate a plan. Use &quot;Use this&quot; to refine it manually."'}>${escapeHtml(applyLabel)}</button>
									<button type="button" class="button button-secondary" data-suggestion-index="${index}" data-suggestion-action="use">Use this</button>
								</div>
							</article>
						`;
					});
			els.askList.innerHTML = '<div class="sae-ask-list" data-progressive-list="suggestions"></div>';
			const suggestionList = els.askList.querySelector('[data-progressive-list="suggestions"]');
			progressivelyRenderHtmlCards(suggestionList, suggestionCards, {
				intervalMs: 120,
				emptyHtml: '<p class="sae-empty">No suggestions returned. Try rephrasing your request.</p>',
			});

			if (els.planPayloadWrap) {
				els.planPayloadWrap.hidden = true;
			}
			els.dryRunWrap.hidden = true;
			els.planDryRun.textContent = '';
			els.planPayload.textContent = '';
			els.planDiffWrap.hidden = true;
			els.planDiffList.innerHTML = '';
			hideBundleReview();
			if (els.tokenWrap) {
				els.tokenWrap.hidden = true;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = '';
			}
			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = '';
			}
			if (els.planActions) {
				els.planActions.hidden = true;
			}
			els.removeConfirmWrap.hidden = true;
			clearTokenCountdown();
			updateApplyButtonLabel(plan);
			updateApplyButtonState();
			return;
		}

		if (isAuditIntent) {
			renderAuditPlan(plan);
			if (els.planPayloadWrap) {
				els.planPayloadWrap.hidden = simpleModeActive;
			}
			if (els.planPayload) {
				els.planPayload.textContent = formatJson(plan);
			}
			els.dryRunWrap.hidden = true;
			els.planDryRun.textContent = '';
			els.planDiffWrap.hidden = true;
			els.planDiffList.innerHTML = '';
			hideBundleReview();
			if (els.tokenWrap) {
				els.tokenWrap.hidden = true;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = '';
			}
			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = '';
			}
			if (els.planActions) {
				els.planActions.hidden = true;
			}
			els.removeConfirmWrap.hidden = true;
			clearTokenCountdown();
			updateApplyButtonLabel(null);
			updateApplyButtonState();
			return;
		}

		if (els.askWrap) {
			els.askWrap.hidden = true;
		}
		if (els.askList) {
			els.askList.innerHTML = '';
		}
		if (els.planPayloadWrap) {
			els.planPayloadWrap.hidden = false;
		}
		if (els.planActions) {
			els.planActions.hidden = false;
		}

		els.planPayload.textContent = formatJson(plan.payload || {});
		els.dryRunWrap.hidden = !dryRun;
		els.planDryRun.textContent = dryRun ? formatJson(dryRun) : '';
		renderPlanDiff(plan);

		const showDestructivePhraseInput = destructive && !simpleModeActive;
		els.removeConfirmWrap.hidden = !showDestructivePhraseInput;
		if (!showDestructivePhraseInput) {
			els.removeConfirmInput.value = '';
		}

		if (hasDurableEnvelope(plan)) {
			if (els.tokenWrap) {
				els.tokenWrap.hidden = simpleModeActive;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = getPlanEnvelopeId(plan);
			}
			if (els.tokenExpiry) {
				els.tokenExpiry.textContent = `state: ${getDurablePlanState(plan)}`;
			}
			clearTokenCountdown();
		} else if (confirmation && confirmation.token) {
			if (els.tokenWrap) {
				els.tokenWrap.hidden = simpleModeActive;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = confirmation.token;
			}
			startTokenCountdown(confirmation.expires_at);
		} else {
			if (els.tokenWrap) {
				els.tokenWrap.hidden = true;
			}
			if (els.tokenValue) {
				els.tokenValue.textContent = '';
			}
			clearTokenCountdown();
		}

		updateApplyButtonLabel(plan);
		updateApplyButtonState();
	}


	function resetPlanState() {
		clearProgressiveRenderTimers();
		state.plan = null;
		state.planPreviewRows = [];
		state.createOutlineActive = false;
		state.createOutlineSelection = [];
		state.planDiffRequestId += 1;
		els.removeConfirmInput.value = '';
		hideBundleReview();
		setApplyStatus('');
		renderPlan();
	}

	function toAbsoluteEndpoint(endpoint) {
		if (!endpoint) {
			return '';
		}
		if (endpoint.startsWith('http://') || endpoint.startsWith('https://')) {
			return endpoint;
		}
		return `${window.location.origin}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
	}

	function buildPostBlocksEndpoint(postId) {
		const base = typeof rest.batch_base === 'string' && rest.batch_base ? rest.batch_base : `${window.location.origin}/wp-json/struo/v1/posts/`;
		const normalizedBase = base.endsWith('/') ? base : `${base}/`;
		return `${normalizedBase}${postId}/blocks`;
	}

	function buildBatchEndpoint(postId) {
		return `${buildPostBlocksEndpoint(postId)}/batch`;
	}

	function buildIdempotencyKey() {
		const timestamp = Date.now();
		const rand = Math.random().toString(36).slice(2, 10);
		return `console:${timestamp}:${rand}`;
	}

	function buildCreateDescriptionFromOutline(plan, postType = 'page') {
		const requestText = String(plan && plan.request ? plan.request : '').trim();
		const outline = Array.isArray(plan && plan.outline ? plan.outline : null) ? plan.outline : [];
		const outlineSummary = outline
			.slice(0, 3)
			.map((item) => (item && item.purpose ? String(item.purpose).trim() : ''))
			.filter(Boolean)
			.join(' ');
		if (requestText && outlineSummary) {
			return `${requestText} ${outlineSummary}`.trim();
		}
		const normalizedPostType = String(postType).trim().toLowerCase();
		const noun = normalizedPostType === 'post' ? 'post' : 'page';
		return requestText || outlineSummary || `Create a new draft ${noun} from this outline.`;
	}

	function ensureCreateOutlineSelection(outlineLength) {
		const count = Number.isInteger(outlineLength) && outlineLength > 0 ? outlineLength : 0;
		if (count <= 0) {
			state.createOutlineSelection = [];
			return;
		}
		if (!Array.isArray(state.createOutlineSelection) || state.createOutlineSelection.length !== count) {
			state.createOutlineSelection = Array.from({ length: count }, () => true);
		}
	}

	function getSelectedOutlineSectionIndexes() {
		if (!Array.isArray(state.createOutlineSelection) || !state.createOutlineSelection.length) {
			return [];
		}
		return state.createOutlineSelection
			.map((isChecked, index) => (isChecked ? index : -1))
			.filter((index) => index >= 0);
	}

	function setAllCreateOutlineSections(nextValue) {
		if (!Array.isArray(state.createOutlineSelection) || !state.createOutlineSelection.length) {
			return;
		}
		const checked = !!nextValue;
		state.createOutlineSelection = state.createOutlineSelection.map(() => checked);
	}

	function setCreateOutlineSection(index, nextValue) {
		if (!Array.isArray(state.createOutlineSelection)) {
			return;
		}
		if (!Number.isInteger(index) || index < 0 || index >= state.createOutlineSelection.length) {
			return;
		}
		state.createOutlineSelection[index] = !!nextValue;
	}

	function shouldFallbackPlanToAsk(plan) {
		if (!plan || plan.intent !== 'do') {
			return false;
		}
		const operation = String(plan.operation || '').trim().toLowerCase();
		const payload = plan.payload && typeof plan.payload === 'object' ? plan.payload : {};
		if (operation === 'batch') {
			const operations = Array.isArray(payload.operations) ? payload.operations : [];
			return operations.length === 0;
		}
		if (operation === 'update' || operation === 'insert') {
			const fields = payload.fields && typeof payload.fields === 'object' ? Object.keys(payload.fields) : [];
			return fields.length === 0;
		}
		return false;
	}

	function shouldRetryAsAsk(error, forcedCrossField) {
		if (forcedCrossField) {
			return false;
		}
		const code = String(error && error.code ? error.code : '').trim();
		if (!code) {
			return false;
		}
		return [
			'sae_planner_schema_invalid',
			'sae_plan_missing_target',
			'sae_target_ambiguous',
			'sae_plan_missing_fields',
			'sae_plan_missing_block_name',
			'sae_plan_missing_operation',
			'sae_invalid_target',
		].includes(code);
	}

	async function handleCreateOutlineContinue() {
		if (state.createOutlineActive || !isCreateOutlinePlan(state.plan)) {
			return;
		}
		if (!rest.plan_create || !rest.create_page) {
			setComposeStatus('Create page endpoints are unavailable in this environment.', 'error');
			return;
		}

		const plan = state.plan;
		const outline = Array.isArray(plan.outline) ? plan.outline : [];
		if (!outline.length) {
			setComposeStatus('No outline sections were returned. Try rephrasing your request.', 'warn');
			return;
		}
		ensureCreateOutlineSelection(outline.length);
		const selectedSections = getSelectedOutlineSectionIndexes();
		if (!selectedSections.length) {
			setComposeStatus('Select at least one section to continue.', 'warn');
			renderPlan();
			return;
		}

		const postType = String(plan && plan.post_type ? plan.post_type : '').trim().toLowerCase() === 'post' ? 'post' : 'page';
		const title = String(plan.title || '').trim() || (postType === 'post' ? 'New Draft Post' : 'New Draft Page');
		const plannedTemplateKey = String(plan && plan.template ? plan.template : '').trim();
		const template = plannedTemplateKey || (postType === 'post' ? '' : 'dynamic');
		const description = buildCreateDescriptionFromOutline(plan, postType);
		const createPlanBody = {
			title,
			post_type: postType,
			description,
			outline,
			selected_sections: selectedSections,
		};
		if (template) {
			createPlanBody.template = template;
		}

		state.createOutlineActive = true;
		renderPlan();
		clearReceipt();
		setApplyStatus('');
		setComposeStatus(isSimpleMode() ? 'Building your page plan…' : 'Compiling page_spec from outline…', 'loading');
		setReviewLoadingStage('ai', {
			requestText: plan.request || '',
			targetLabel: '',
			flow: 'create',
			createPhase: 'content',
		});

		try {
			const createPlanResponse = await requestJson(rest.plan_create, {
				method: 'POST',
				body: createPlanBody,
				timeoutMs: CREATE_PLAN_TIMEOUT_MS,
			});
			const durablePlanId = createPlanResponse && createPlanResponse.plan_id
				? String(createPlanResponse.plan_id)
				: '';
			const idempotencyKey = createPlanResponse && createPlanResponse.idempotency_key
				? String(createPlanResponse.idempotency_key)
				: '';
			if (!durablePlanId || !idempotencyKey) {
				throw new Error('Create plan response is missing durable plan data.');
			}

			const createdTemplateKey = createPlanResponse && createPlanResponse.template
				? String(createPlanResponse.template)
				: template;
			const createdTemplateMode = createPlanResponse && createPlanResponse.template_mode
				? String(createPlanResponse.template_mode).trim().toLowerCase()
				: (createdTemplateKey && createdTemplateKey.startsWith('dynamic-') ? 'custom' : 'known');
			const createdTemplateLabel = createPlanResponse && createPlanResponse.template_label
				? String(createPlanResponse.template_label).trim()
				: (String(plan.template_label || '').trim() || (createdTemplateMode === 'custom' ? 'AI-generated outline' : (createdTemplateKey || '--')));
			const createdTemplateRationale = createPlanResponse && createPlanResponse.template_rationale
				? String(createPlanResponse.template_rationale).trim()
				: String(plan.template_rationale || '').trim();
			const blocksCreated = createPlanResponse && typeof createPlanResponse.blocks_created !== 'undefined'
				? Number(createPlanResponse.blocks_created)
				: selectedSections.length;

			state.plan = {
				request: plan.request || description,
				intent: 'create',
				operation: 'create',
				payload_type: createPlanResponse.payload_type || 'page_spec_v1',
				plan_id: durablePlanId,
				plan_state: createPlanResponse.plan_state || 'planned',
				title,
				post_type: postType,
				template: createdTemplateKey,
				template_mode: createdTemplateMode,
				template_label: createdTemplateLabel,
				template_rationale: createdTemplateRationale,
				description,
				outline,
				operations: Array.isArray(createPlanResponse.operations) ? createPlanResponse.operations : [],
				endpoint: '/wp-json/struo/v1/pages/create',
				payload: {
					idempotency_key: idempotencyKey,
					payload_type: 'page_spec_v1',
					title,
					post_type: postType,
					template: createdTemplateKey,
					content_hash: createPlanResponse.content_hash || '',
					block_count: Number.isFinite(blocksCreated) ? blocksCreated : 0,
				},
				dry_run: {
					intent: 'create',
					title,
					post_type: postType,
					template: createdTemplateKey,
					blocks_created: Number.isFinite(blocksCreated) ? blocksCreated : 0,
					content_hash: createPlanResponse.content_hash || '',
					operations: Array.isArray(createPlanResponse.operations) ? createPlanResponse.operations : [],
					blocks_preview: Array.isArray(createPlanResponse.blocks_preview) ? createPlanResponse.blocks_preview : [],
					confirmation: {
						redeemable: false,
						origin: 'rest',
						message: 'Plan-only: approve and apply via the durable page_spec envelope.',
					},
				},
			};
			state.tokenExpiresAtMs = 0;
			updateApplyButtonLabel(state.plan);
			updateApplyButtonState();
			renderPlan();
			flashComposerStageChipDone('Done');
			setComposeStatus(
				isSimpleMode()
					? 'Page plan ready. Approve it before creating the draft.'
					: `page_spec_v1 planned (${durablePlanId}). Approve, then apply to create the draft.`,
				'info'
			);
		} catch (error) {
			setComposeStatus(
				getFriendlyErrorMessage(error, isSimpleMode() ? 'Could not build a page plan from this outline.' : 'Create plan failed.'),
				'error'
			);
		} finally {
			state.createOutlineActive = false;
			setReviewLoadingStage('');
			renderPlan();
		}
	}

	async function handleGeneratePlan() {
		if (state.planRequestActive && state.planRequestController) {
			state.planRequestController.abort();
			setComposeStatus(isSimpleMode() ? 'Request cancelled.' : 'Plan request cancelled.', 'warn');
			return;
		}

		if (state.batchMode) {
			await handleGenerateBatchPlan();
			return;
		}

		const requestText = (els.request.value || '').trim();
		const requestOrigin = String(state.lastRequestOrigin || 'manual').trim().toLowerCase();
		const recoveryContext = state.pendingPlanRecovery && typeof state.pendingPlanRecovery === 'object'
			? state.pendingPlanRecovery
			: null;
		if (!requestText) {
			setRequestValidationState('error');
			setComposeStatus(isSimpleMode() ? "What would you like to change?" : 'Request is required.', 'error');
			return;
		}
		setRequestValidationState('');
		const validation = validateRequestBeforePlan(requestText, { requestOrigin: state.lastRequestOrigin });
		if (!validation.ok) {
			setRequestValidationState(validation.kind || 'warn');
			setComposeStatus(validation.message, validation.kind || 'warn');
			return;
		}
		const createIntentRequest = shouldForceCreateIntent(requestText);
		const auditIntentRequest = !createIntentRequest && isLikelyAuditIntentRequest(requestText, requestOrigin);
		const directAskIntentRequest = !createIntentRequest
			&& !['suggestion', 'suggestion_apply'].includes(requestOrigin)
			&& isLikelyAskIntentRequest(requestText, requestOrigin);

		const hadExistingPlan = !!state.plan;
		clearReceipt();
		if (hadExistingPlan) {
			clearProgressiveRenderTimers();
			clearStreamedReviewPreview();
			state.createOutlineActive = false;
			state.planPreviewRows = [];
			state.planDiffRequestId += 1;
			prepareExistingPlanForRefresh();
		} else {
			resetPlanState();
		}
		setApplyStatus('');
		setRequestValidationState('');
		setComposerStageChip('', '');
		const selectedPostId = getSelectedPostId();
		const selectedPostLabel = selectedPostId > 0 && !createIntentRequest ? getPostLabelById(selectedPostId) : '';
		const plannerRequestText = buildPlannerRequestText(requestText, requestOrigin);
		const loadingContext = createIntentRequest
			? { flow: 'create', createPhase: 'outline' }
			: auditIntentRequest
				? { flow: 'audit' }
				: directAskIntentRequest
					? { flow: 'ask' }
					: {};
		if (createIntentRequest) {
			selectAutoDetectPostContext();
		}
		state.loadingRequestText = requestText;
		state.loadingTargetLabel = selectedPostLabel;
		if (auditIntentRequest) {
			setComposeStatus(isSimpleMode() ? 'Analyzing this page…' : 'Running page analysis…', 'loading');
		} else if (directAskIntentRequest) {
			setComposeStatus(isSimpleMode() ? 'Generating ideas…' : 'Generating suggestions…', 'loading');
		} else {
			setComposeStatus(isSimpleMode() ? 'Working on it…' : 'Parsing your request…', 'loading');
		}
		const requestController = new AbortController();
		state.planRequestController = requestController;
		const streamRequestId = `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
		state.planStreamRequestId = streamRequestId;
		setPlanRequestActive(true);
		const primedLoadingPreview = primeReviewLoadingPreview(
			requestText,
			requestOrigin,
			streamRequestId,
			{
				createIntent: createIntentRequest,
				createTitle: createIntentRequest ? 'Draft in progress' : '',
			}
		);
		if (hadExistingPlan || !primedLoadingPreview) {
			setReviewLoadingStage('parsing', {
				requestText,
				targetLabel: selectedPostLabel,
				...loadingContext,
			});
		}
		let forcedCrossField = '';

		try {
			const responseMode = els.responseMode.value === 'verbose';
			forcedCrossField = state.pendingCrossField;
			const forcedPostId = Number(state.pendingResolvedPostId || 0);
			clearPendingPlanContext();
			const planRequestOptions = createIntentRequest
				? {
					timeoutMs: CREATE_PLAN_TIMEOUT_MS,
					disableStreaming: false,
				}
				: auditIntentRequest
					? {
						timeoutMs: REQUEST_TIMEOUT_MS * 2,
						disableStreaming: true,
					}
					: directAskIntentRequest
						? {
							disableStreaming: true,
						}
					: {};
			const payload = {
				request: plannerRequestText,
				dry_run_preview: isSimpleMode() ? true : !!els.dryRunPreview.checked,
				prefer_ai: !!els.preferAi.checked,
				verbose: responseMode,
				compact: !responseMode,
			};
			if (createIntentRequest) {
				payload.intent = 'create';
				payload.post_id = 0;
				payload.post_type = getLikelyCreatePostType(requestText);
			} else if (auditIntentRequest) {
				payload.intent = 'audit';
				payload.dry_run_preview = false;
				payload.verbose = false;
				payload.compact = true;
			} else if (directAskIntentRequest) {
				payload.intent = 'ask';
				payload.dry_run_preview = false;
				payload.verbose = false;
				payload.compact = true;
			}
			if (!createIntentRequest && (selectedPostId > 0 || forcedPostId > 0)) {
				payload.plan = { post_id: selectedPostId > 0 ? selectedPostId : forcedPostId };
			}
			const bundlePostIds = getBundlePostIds(requestText);
			if (!createIntentRequest && bundlePostIds.length >= 2) {
				payload.post_ids = bundlePostIds;
				delete payload.plan;
			}
			if (forcedCrossField) {
				const resolvedPostId = selectedPostId > 0 ? selectedPostId : forcedPostId;
				if (resolvedPostId <= 0) {
					throw new Error('Select a page or post first for this quick action.');
				}
				payload.cross_field = forcedCrossField;
				payload.post_id = resolvedPostId;
			}

			let plan = await requestPlan(
				payload,
				{
					onEvent: (eventName, streamPayload) => {
						handlePlanStreamEvent(eventName, streamPayload, {
							requestId: streamRequestId,
							requestText,
							targetLabel: selectedPostLabel,
							loadingContext,
						});
					},
					onFallback: () => {
						if (isStalePlanRequest(streamRequestId)) {
							return;
						}
						clearStreamedReviewPreview();
						setComposeStatus(isSimpleMode() ? 'Still working on it…' : 'Streaming unavailable, continuing with standard planner…', 'loading');
						setReviewLoadingStage('ai', {
							requestText,
							targetLabel: selectedPostLabel,
							...loadingContext,
						});
					},
				},
				requestController.signal,
				planRequestOptions
			);
			if (isStalePlanRequest(streamRequestId)) {
				return;
			}
			if (shouldRetryPlanWithoutStreaming(plan, payload)) {
				setComposeStatus(isSimpleMode() ? 'Finishing your preview…' : 'Completing dry-run preview…', 'loading');
				plan = await requestPlan(
					payload,
					{},
					requestController.signal,
					Object.assign({}, planRequestOptions, { disableStreaming: true })
				);
				if (isStalePlanRequest(streamRequestId)) {
					return;
				}
			}
			if (
				payload.dry_run_preview
				&& plan
				&& typeof plan === 'object'
				&& plan.intent !== 'ask'
				&& !isAuditPlan(plan)
				&& !isCreateOutlinePlan(plan)
				&& !hasPlanToken(plan)
				&& !hasDurableEnvelope(plan)
				&& !hasPlanPreview(plan)
			) {
				throw { code: 'sae_preview_incomplete', message: 'Dry-run preview did not finish. Generate the plan again.' };
			}
			if (shouldFallbackPlanToAsk(plan) && !forcedCrossField) {
				setComposeStatus(
					isSimpleMode()
						? 'I can suggest edits for this request. Building options…'
						: 'No executable edit returned. Falling back to ask suggestions…',
					'loading'
				);
				setReviewLoadingStage('ai', {
					requestText,
					targetLabel: selectedPostLabel,
					...loadingContext,
				});
					const askPayload = {
						...payload,
						intent: 'ask',
					};
				plan = await requestPlan(
					askPayload,
					{
						onEvent: (eventName, streamPayload) => {
							handlePlanStreamEvent(eventName, streamPayload, {
								requestId: streamRequestId,
								requestText,
								targetLabel: selectedPostLabel,
								loadingContext,
							});
						},
						onFallback: () => {
							if (isStalePlanRequest(streamRequestId)) {
								return;
							}
							clearStreamedReviewPreview();
							setReviewLoadingStage('ai', {
								requestText,
								targetLabel: selectedPostLabel,
								...loadingContext,
							});
						},
						},
						requestController.signal,
						planRequestOptions
					);
				if (isStalePlanRequest(streamRequestId)) {
					return;
				}
			}
			if (isStalePlanRequest(streamRequestId)) {
				return;
			}
			plan = annotatePlanRequestOrigin(plan, requestOrigin);
			rememberRequest(requestText);
			if (shouldRejectAutoApplyInsert(plan, requestOrigin)) {
				if (recoveryContext && recoveryContext.plan) {
					if (isStalePlanRequest(streamRequestId)) {
						return;
					}
					state.plan = annotatePlanRequestOrigin(recoveryContext.plan, requestOrigin);
					clearPendingPlanRecovery();
					renderPlan();
				}
				if (isStalePlanRequest(streamRequestId)) {
					return;
				}
				setComposeStatus(getAutoApplyInsertMessage(requestOrigin), 'error');
				return;
			}
			if (isStalePlanRequest(streamRequestId)) {
				return;
			}
			state.plan = plan;
			notifyWorkQueueSelect(plan);
			clearPendingPlanRecovery();
			renderPlan();
			flashComposerStageChipDone('Done');
			if (els.planContent && !els.planContent.hidden) {
				els.planContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			if (isCreateOutlinePlan(plan)) {
				setComposeStatus(isSimpleMode() ? 'Outline ready. Continue to create a draft.' : 'Create outline ready. Continue to draft creation.', 'info');
			} else if (isAuditPlan(plan)) {
				const score = Number(plan.audit && plan.audit.score ? plan.audit.score : 0);
				const issues = getAuditIssues(plan).length;
				const summaryLabel = Number.isFinite(score) && score > 0 ? `${score}/10` : 'ready';
				setComposeStatus(
					isSimpleMode()
						? `Audit ready. Score ${summaryLabel}${issues ? ` with ${issues} issue${issues === 1 ? '' : 's'}` : ''}.`
						: `Audit ready. Review ${issues || 0} issue${issues === 1 ? '' : 's'} and turn any finding into a fix plan.`,
					'info'
				);
			} else if (plan && plan.intent === 'ask') {
				setComposeStatus(isSimpleMode() ? 'Here are some ideas:' : 'Suggestions ready. Pick one to create an edit request.', 'info');
			} else {
				const plannerSource = plan && plan.planner ? String(plan.planner.source || '') : '';
				const sourceLabel = ['ai_engine', 'ai_rewrite', 'client', 'openai'].includes(plannerSource) ? 'AI-assisted plan generated.' : 'Deterministic plan generated.';
				setComposeStatus(isSimpleMode() ? "Ready to apply. Here's what will change:" : `${sourceLabel} Review and approve to apply.`, 'info');
			}
		} catch (error) {
			if (isStalePlanRequest(streamRequestId)) {
				return;
			}
			if (error && error.code === 'request_aborted') {
				clearStreamedReviewPreview();
				setComposeStatus(isSimpleMode() ? 'Request cancelled.' : 'Plan request cancelled.', 'warn');
				clearPendingPlanRecovery();
				return;
			}
			if (recoveryContext && recoveryContext.plan && shouldRestoreRecoveredPlan(error, requestOrigin)) {
				if (isStalePlanRequest(streamRequestId)) {
					return;
				}
				state.plan = annotatePlanRequestOrigin(recoveryContext.plan, requestOrigin);
				clearPendingPlanRecovery();
				renderPlan();
				setComposeStatus(getRecoveredPlanErrorMessage(error, requestOrigin), 'error');
				return;
			}
			if (shouldRetryAsAsk(error, forcedCrossField)) {
				try {
					setComposeStatus(
						isSimpleMode()
							? 'I can still help with suggestions. Building options…'
							: 'Planner returned no executable edit. Falling back to ask suggestions…',
						'loading'
					);
					setReviewLoadingStage('ai', {
						requestText,
						targetLabel: selectedPostLabel,
						...loadingContext,
					});
					const askPayload = {
						request: plannerRequestText,
						dry_run_preview: false,
						prefer_ai: !!els.preferAi.checked,
						verbose: false,
						compact: true,
						intent: 'ask',
					};
					if (selectedPostId > 0) {
						askPayload.plan = { post_id: selectedPostId };
					}
					const askPlan = await requestPlan(
						askPayload,
						{
							onEvent: (eventName, streamPayload) => {
								handlePlanStreamEvent(eventName, streamPayload, {
									requestId: streamRequestId,
									requestText,
									targetLabel: selectedPostLabel,
									loadingContext,
								});
							},
							onFallback: () => {
								if (isStalePlanRequest(streamRequestId)) {
									return;
								}
								clearStreamedReviewPreview();
								setReviewLoadingStage('ai', {
									requestText,
									targetLabel: selectedPostLabel,
									...loadingContext,
								});
							},
						},
						requestController.signal
					);
					if (isStalePlanRequest(streamRequestId)) {
						return;
					}
					rememberRequest(requestText);
					state.plan = annotatePlanRequestOrigin(askPlan, requestOrigin);
					clearPendingPlanRecovery();
					renderPlan();
					flashComposerStageChipDone('Done');
					if (els.planContent && !els.planContent.hidden) {
						els.planContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
					setComposeStatus(
						isSimpleMode()
							? 'Here are suggested edits you can apply.'
							: 'Planner fallback applied. Pick a suggestion to continue.',
						'info'
					);
					return;
				} catch (fallbackError) {
					if (fallbackError && fallbackError.code === 'request_aborted') {
						clearStreamedReviewPreview();
						setComposeStatus(isSimpleMode() ? 'Request cancelled.' : 'Plan request cancelled.', 'warn');
						clearPendingPlanRecovery();
						return;
					}
				}
			}
			clearPendingPlanRecovery();
			clearStreamedReviewPreview();
			setComposeStatus(getFriendlyErrorMessage(error, config.strings?.network_error || 'Request failed.'), 'error');
		} finally {
			if (isStalePlanRequest(streamRequestId)) {
				return;
			}
			invalidatePlanRequest();
			state.planRequestController = null;
			setPlanRequestActive(false);
			setReviewLoadingStage('');
			state.loadingRequestText = '';
			state.loadingTargetLabel = '';
			clearPendingPlanContext();
		}
	}

	async function handleGenerateBatchPlan() {
		clearReceipt();
		resetPlanState();
		setApplyStatus('');
		setRequestValidationState('');
		const targetLabel = getPostLabelById(els.postId && els.postId.value ? Number(els.postId.value) : 0);
		const operationCount = Array.isArray(state.batchOperations) ? state.batchOperations.length : 0;
		state.loadingRequestText = `Batch preview (${operationCount} operation${operationCount === 1 ? '' : 's'})`;
		state.loadingTargetLabel = targetLabel;
		setComposeStatus(isSimpleMode() ? 'Building your batch preview…' : 'Generating batch dry-run…', 'loading');
		els.generatePlan.disabled = true;
		setReviewLoadingStage('parsing', {
			requestText: state.loadingRequestText,
			targetLabel,
		});

		try {
			const responseMode = els.responseMode.value === 'verbose';
			const batch = buildBatchPayload();
			const endpoint = buildBatchEndpoint(batch.postId);
			const response = await requestJson(endpoint, {
				method: 'POST',
				body: {
					operations: batch.operations,
					bundle_name: batch.bundleName,
					dry_run: true,
					verbose: responseMode,
					compact: !responseMode,
				},
			});

			const postContext = getSelectedPostContext();
			state.plan = {
				request: '[batch-composer]',
				planner: {
					source: 'batch_composer',
					ai_used: false,
					warnings: [],
				},
				intent: 'do',
				post: {
					post_id: batch.postId,
					post_title: postContext.label,
				},
				operation: 'batch',
				endpoint,
				payload: {
					bundle_name: batch.bundleName,
					operations: batch.operations,
				},
				dry_run: response,
				plan_id: response && response.plan_id ? String(response.plan_id) : '',
				plan_state: response && response.plan_state ? String(response.plan_state) : 'planned',
			};

			renderPlan();
			notifyWorkQueueSelect(state.plan);
			flashComposerStageChipDone('Done');
			if (els.planContent && !els.planContent.hidden) {
				els.planContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			const bundleLabel = batch.bundleName ? ` Bundle "${batch.bundleName}" is ready.` : '';
			setComposeStatus(isSimpleMode() ? 'Batch ready. Review your changes:' : `Batch plan generated.${bundleLabel} Review and approve to apply.`, 'info');
		} catch (error) {
			setComposeStatus(getFriendlyErrorMessage(error, config.strings?.network_error || 'Request failed.'), 'error');
		} finally {
			els.generatePlan.disabled = false;
			setReviewLoadingStage('');
			state.loadingRequestText = '';
			state.loadingTargetLabel = '';
		}
	}

	async function handleApply() {
		if (isAuditPlan(state.plan)) {
			setApplyStatus('Choose an audit issue first, then generate a fix plan before apply.', 'warn');
			return;
		}

		if (!state.plan || !state.plan.payload || !state.plan.endpoint) {
			if (state.plan && state.plan.intent === 'ask') {
				setApplyStatus('Select a suggestion, then generate a plan before apply.', 'warn');
				return;
			}
			setApplyStatus(config.strings?.plan_required || 'Generate a plan before apply.', 'error');
			return;
		}

		const durablePlanId = getPlanEnvelopeId(state.plan);
		if (!durablePlanId || !hasDurableEnvelope(state.plan)) {
			setApplyStatus('This plan is not ready to apply. Generate a fresh plan.', 'error');
			return;
		}
		const durableState = getDurablePlanState(state.plan);
		if ('planned' === durableState) {
			try {
				await approveDurablePlan(durablePlanId);
			} catch (error) {
				setApplyStatus(getFriendlyErrorMessage(error, 'Could not approve the plan.'), 'error');
			}
			return;
		}
		if ('approved' !== durableState) {
			setApplyStatus('This plan is not ready to apply.', 'error');
			return;
		}

		if (requiresDestructiveConfirm(state.plan)) {
			if (isSimpleMode()) {
				const pageLabel = state.plan && state.plan.post
					? state.plan.post.post_title || state.plan.post.post_slug || `post ${state.plan.post.post_id || ''}`
					: 'this page';
				const confirmed = await requestSimpleApplyConfirmation({
					title: 'Confirm removal',
					description: `You are about to remove content on ${pageLabel}. Continue?`,
					confirmLabel: 'Apply remove',
					cancelLabel: 'Cancel',
				});
				if (!confirmed) {
					setApplyStatus('Destructive change cancelled. Nothing was modified.', 'warn');
					return;
				}
			} else if (!isDestructivePhraseValid()) {
				setApplyStatus(`Type "${REMOVE_CONFIRM_PHRASE}" to allow this destructive apply.`, 'warn');
				updateApplyButtonState();
				return;
			}
		}

		clearReceipt();
		setApplyStatus('Validating changes…', 'loading');
		els.apply.disabled = true;
		const applyTimeout = window.setTimeout(() => {
			setApplyStatus('Writing to WordPress…', 'loading');
		}, 800);

		try {
			let response;
			let idempotencyKey;
			const envelopeResponse = await applyDurablePlan(durablePlanId);
			response = envelopeResponse && envelopeResponse.result ? envelopeResponse.result : envelopeResponse;
			idempotencyKey = durablePlanId;
			window.clearTimeout(applyTimeout);
			const replayed = !!(response && response.idempotency && response.idempotency.replayed);
			const appliedPlan = state.plan && typeof state.plan === 'object' ? cloneJsonLike(state.plan) : null;
			const resolvedPostId = appliedPlan && appliedPlan.post && appliedPlan.post.post_id ? String(appliedPlan.post.post_id) : '';
			const resolvedPostIdNumber = Number(resolvedPostId || 0);
			const postTitle = state.plan && state.plan.post ? state.plan.post.post_title || state.plan.post.post_slug || `Post ${resolvedPostId}` : '';
			const postUrl = state.plan && state.plan.post ? state.plan.post.post_url || '' : '';
			const safeUrl = postUrl && /^https?:\/\//i.test(postUrl) ? postUrl : '';
			const delta = response && response.change && typeof response.change.delta !== 'undefined' ? Number(response.change.delta) : null;
			const operationLabelMap = {
				update: 'Content updated',
				insert: 'Block inserted',
				remove: 'Block removed',
				batch: 'Batch changes applied',
				cross_field: 'Field updated',
			};
			const operationLabel = operationLabelMap[state.plan.operation] || 'Changes applied';
			let deltaLabel = '';
			if (Number.isFinite(delta)) {
				if (delta > 0) {
					deltaLabel = `+${delta} block${delta !== 1 ? 's' : ''}`;
				} else if (delta < 0) {
					deltaLabel = `${delta} block${Math.abs(delta) !== 1 ? 's' : ''}`;
				} else {
					deltaLabel = 'No block count change';
				}
			}
			const message = replayed
				? (isSimpleMode() ? 'No duplicate write was made.' : 'Apply completed via idempotency replay. No duplicate write executed.')
				: (isSimpleMode() ? 'Saved to WordPress.' : 'Apply completed successfully.');
			setApplyStatus(message, replayed ? 'warn' : 'info');
			notifyMutationApplied(resolvedPostIdNumber);
				const details = [
					{ label: 'Operation', value: state.plan.operation || '--' },
					{ label: 'Post ID', value: state.plan.post && state.plan.post.post_id ? state.plan.post.post_id : '--' },
					{ label: 'Endpoint', value: state.plan.endpoint || '--' },
					{ label: 'Idempotency key', value: idempotencyKey },
					{ label: 'Status', value: replayed ? 'replayed' : 'success' },
					{
						label: 'Page',
						value: safeUrl ? `${postTitle} ↗` : postTitle || '--',
					},
				];
				const simpleDetails = [
					{ label: 'Page', value: postTitle || '--' },
					{ label: 'Action', value: operationLabel },
				];
				if (state.plan && state.plan.operation === 'cross_field' && state.plan.payload && state.plan.payload.field) {
					const fieldLabel = humanizeFieldName(state.plan.payload.field);
					details.push({ label: 'Field', value: fieldLabel });
					simpleDetails.push({ label: 'Field', value: fieldLabel });
				}
				const batchBundleName = state.plan && state.plan.operation === 'batch' && state.plan.payload && state.plan.payload.bundle_name
					? String(state.plan.payload.bundle_name).trim()
					: '';
				if (batchBundleName) {
					details.push({ label: 'Bundle', value: batchBundleName });
					simpleDetails.push({ label: 'Bundle', value: batchBundleName });
				}
			if (deltaLabel) {
				details.push({ label: 'Change', value: deltaLabel });
				simpleDetails.push({ label: 'Change', value: deltaLabel });
			}
				const applyTransparency = buildPlanTransparency(state.plan, state.planPreviewRows);
				const undoContext = buildUndoContextFromPlan(state.plan, state.planPreviewRows);
				const premiumSummary = replayed
					? `No duplicate write was made on ${postTitle || 'this page'}.`
					: buildPlanCompletionSummary(state.plan, state.planPreviewRows, postTitle || 'this page');
				const shouldRunAuditFollowUp = !replayed
					&& resolvedPostIdNumber > 0
					&& appliedPlan
					&& String(appliedPlan.request_origin || '').trim().toLowerCase() === 'audit_apply';
				const previousScoutSnapshot = shouldRunAuditFollowUp ? readScoutAuditSnapshot(resolvedPostIdNumber) : null;
				const auditFollowUpToken = shouldRunAuditFollowUp
					? `audit_follow_up_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
					: '';
				const receiptPayload = {
					kind: replayed ? 'warning' : 'success',
					title: replayed ? 'Apply replayed' : 'Apply completed',
					message,
					friendly_title: replayed ? 'No duplicate write was made' : 'Saved to WordPress',
					friendly_message: replayed
						? `The latest version is already saved to WordPress on ${postTitle || 'this page'}.`
						: `Saved to WordPress on ${postTitle || 'this page'}.`,
					friendly_summary: premiumSummary,
					details,
					simple_details: simpleDetails,
					view_url: safeUrl,
					post_id: resolvedPostId,
					confidence: applyTransparency.confidence,
					scope_line: applyTransparency.scopeLine,
					undo_context: replayed ? null : undoContext,
					audit_follow_up_token: auditFollowUpToken,
					audit_follow_up: shouldRunAuditFollowUp ? buildAuditFollowUpLoadingState(previousScoutSnapshot) : null,
				};
				renderReceipt(receiptPayload);
				if (!replayed) {
					confettiBurst(els.receipt);
				}
			if (resolvedPostId && els.postId.querySelector(`option[value="${resolvedPostId}"]`)) {
				els.postId.value = resolvedPostId;
			}
			if (resolvedPostId) {
				deleteScoutAuditSnapshot(resolvedPostId);
				delete state.postBlocksCache[resolvedPostIdNumber];
				delete state.postBlocksRequests[resolvedPostIdNumber];
			}
			resetPlanState();
			if (receiptPayload && receiptPayload.audit_follow_up_token) {
				void runReceiptAuditFollowUp(receiptPayload, previousScoutSnapshot, {
					postId: resolvedPostIdNumber,
					token: receiptPayload.audit_follow_up_token,
				});
			}
			await Promise.all([loadAudit(), loadStatus()]);
		} catch (error) {
			window.clearTimeout(applyTimeout);
			const code = error && error.code ? error.code : 'unknown';
			const message = getFriendlyErrorMessage(error, isSimpleMode() ? 'Could not apply changes.' : 'Apply failed.');
			setApplyStatus(message, 'error');
			renderReceipt({
				kind: 'error',
				title: 'Apply failed',
				message,
				friendly_title: 'Could not apply changes',
				friendly_message: 'No content was changed. Review the plan and try again.',
				details: [
					{ label: 'Operation', value: state.plan.operation || '--' },
					{ label: 'Post ID', value: state.plan.post && state.plan.post.post_id ? state.plan.post.post_id : '--' },
					{ label: 'Endpoint', value: state.plan.endpoint || '--' },
					{ label: 'Idempotency key', value: idempotencyKey },
					{ label: 'Error code', value: code },
				],
				simple_details: [
					{ label: 'Page', value: state.plan.post && (state.plan.post.post_title || state.plan.post.post_slug || state.plan.post.post_id) ? state.plan.post.post_title || state.plan.post.post_slug || state.plan.post.post_id : '--' },
					{ label: 'Error code', value: code },
				],
			});
			updateApplyButtonState();
		}
	}

	function formatRelativeTime(value) {
		const timestamp = Date.parse(value || '');
		if (Number.isNaN(timestamp)) {
			return { short: '--', full: '--' };
		}

		const now = Date.now();
		const diffSec = Math.round((now - timestamp) / 1000);
		const abs = Math.abs(diffSec);
		let unit = 'sec';
		let amount = abs;

		if (abs >= 86400) {
			unit = 'day';
			amount = Math.floor(abs / 86400);
		} else if (abs >= 3600) {
			unit = 'hr';
			amount = Math.floor(abs / 3600);
		} else if (abs >= 60) {
			unit = 'min';
			amount = Math.floor(abs / 60);
		}

		const suffix = diffSec >= 0 ? 'ago' : 'from now';
		return {
			short: `${amount}${unit} ${suffix}`,
			full: new Date(timestamp).toLocaleString(),
		};
	}

	function renderHistoryRow(item) {
		const details = item && item.details ? item.details : {};
		const detailSummary = summarizeHistoryDetails(details);
		const createdValue = item && (item.created_at_iso || item.created_at) ? item.created_at_iso || item.created_at : '';
		const created = formatRelativeTime(createdValue);
		const historyPostLabel = Number(item && item.post_id ? item.post_id : 0)
			? getPostLabelById(item.post_id || '')
			: (String(details.post_title || details.title || details.template_label || '').trim() || '--');
		if (isSimpleMode()) {
			const actionLabel = getHistoryActionLabel(item.action || '');
			const metaLine = buildSimpleHistoryMeta(details, item);
			return `
				<tr class="sae-history-row-simple">
					<td colspan="5">
						<div class="sae-history-simple__time">${escapeHtml(created.short)}</div>
						<p class="sae-history-simple__title">${escapeHtml(`${actionLabel} on ${historyPostLabel}`)}</p>
						${metaLine ? `<p class="sae-history-simple__meta">${escapeHtml(metaLine)}</p>` : ''}
					</td>
				</tr>
			`;
		}

		return `
			<tr>
				<td><time datetime="${escapeHtml(createdValue || '')}" title="${escapeHtml(created.full)}">${escapeHtml(created.short)}</time></td>
				<td>${escapeHtml(item.action || '--')}</td>
				<td>${escapeHtml(historyPostLabel)}</td>
				<td>${escapeHtml(item.user_label || item.user_id || '--')}</td>
				<td>${escapeHtml(detailSummary)}</td>
			</tr>
		`;
	}

	function isReadOnlyAuditAction(action) {
		const normalized = String(action || '').trim().toLowerCase();
		if (!normalized) {
			return false;
		}
		return normalized === 'catalog_read' || normalized === 'post_blocks_read' || normalized.endsWith('_read');
	}

	function escapeCsvCell(value) {
		let text = String(value === undefined || value === null ? '' : value);
		if (/^\s*[=+\-@]/.test(text) || /^[\t\r]/.test(text)) {
			text = `'${text}`;
		}
		if (/[",\n]/.test(text)) {
			return `"${text.replace(/"/g, '""')}"`;
		}
		return text;
	}

	function buildAuditCsv(items) {
		const header = ['time', 'action', 'post', 'user', 'details'];
		const rows = (items || []).map((item) => {
			return [
				item && item.created_at ? item.created_at : '',
				item && item.action ? item.action : '',
				getPostLabelById(item && item.post_id ? item.post_id : ''),
				item && (item.user_label || item.user_id) ? item.user_label || item.user_id : '',
				summarizeHistoryDetails(item && item.details ? item.details : {}),
			].map(escapeCsvCell).join(',');
		});
		return [header.join(','), ...rows].join('\n');
	}

	function downloadTextFile(name, mimeType, content) {
		const blob = new Blob([content], { type: mimeType });
		const url = URL.createObjectURL(blob);
		const anchor = document.createElement('a');
		anchor.href = url;
		anchor.download = name;
		document.body.appendChild(anchor);
		anchor.click();
		anchor.remove();
		window.setTimeout(() => URL.revokeObjectURL(url), 0);
	}

	async function handleHistoryExport() {
		const format = els.historyExportFormat && els.historyExportFormat.value === 'json' ? 'json' : 'csv';
		const action = els.historyFilter ? String(els.historyFilter.value || '') : '';
		const hideReadOnly = !!(els.historyHideReads && els.historyHideReads.checked);
		const exportUrl = rest.audit_export
			? buildUrl(rest.audit_export, {
				action,
				exclude_actions: hideReadOnly ? 'catalog_read,post_blocks_read' : '',
				format,
			})
			: '';

		try {
			if (exportUrl) {
				const response = await requestJson(exportUrl);
				const count = Number(response && response.count ? response.count : 0);
				const filename = response && response.filename ? String(response.filename) : `sae-history.${format}`;
				if (format === 'json') {
					const items = Array.isArray(response.items) ? response.items : [];
					downloadTextFile(filename, 'application/json', JSON.stringify(items, null, 2));
				} else {
					const content = response && typeof response.content === 'string' ? response.content : '';
					downloadTextFile(filename, 'text/csv;charset=utf-8', content);
				}
				setComposeStatus(`History export ready (${count} row${count === 1 ? '' : 's'}).`, 'info');
				return;
			}
		} catch (error) {
			setComposeStatus(`Export endpoint failed: ${error.message || 'unknown error'}. Using visible rows.`, 'warn');
		}

		if (!state.historyLoaded && !state.historyLoading) {
			await loadAudit({ force: false });
		}

		const items = Array.isArray(state.historyItems) ? state.historyItems : [];
		if (!items.length) {
			setComposeStatus('No history rows to export for the current filters.', 'warn');
			return;
		}
		const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
		if (format === 'json') {
			downloadTextFile(`sae-history-${timestamp}.json`, 'application/json', JSON.stringify(items, null, 2));
		} else {
			downloadTextFile(`sae-history-${timestamp}.csv`, 'text/csv;charset=utf-8', buildAuditCsv(items));
		}
		setComposeStatus(`History export ready (${items.length} visible row${items.length === 1 ? '' : 's'}).`, 'info');
	}

	function renderHistoryDeferredState() {
		if (!els.historyBody) {
			return;
		}
		const message = isSimpleMode()
			? 'Recent activity loads when you scroll here.'
			: 'History loads on demand. Scroll to this panel or click Refresh History.';
		const rowClass = isSimpleMode() ? ' class="sae-history-row-simple"' : '';
		els.historyBody.innerHTML = `<tr${rowClass}><td colspan="5" class="sae-empty">${escapeHtml(message)}</td></tr>`;
	}

	async function loadAudit(options = {}) {
		if (!els.historyBody) {
			return;
		}
		const force = Object.prototype.hasOwnProperty.call(options, 'force') ? !!options.force : true;
		if (state.historyLoading) {
			return;
		}
		if (!force && state.historyLoaded) {
			return;
		}
		state.historyLoading = true;
		els.historyBody.innerHTML = '<tr><td colspan="5" class="sae-empty">Loading history…</td></tr>';
		try {
			const action = els.historyFilter.value || '';
			const hideReadOnly = !!(els.historyHideReads && els.historyHideReads.checked);
			if (!force) {
				const cachedItems = readAuditCache(action, hideReadOnly);
				if (cachedItems && cachedItems.length) {
					state.historyItems = cachedItems;
					state.historyLoaded = true;
					els.historyBody.innerHTML = cachedItems.map(renderHistoryRow).join('');
					return;
				}
			}
			const url = buildUrl(rest.audit, {
				page: 1,
				per_page: 25,
				action,
				exclude_actions: hideReadOnly ? 'catalog_read,post_blocks_read' : '',
				_ts: force ? Date.now() : '',
			});
			const response = await requestJson(url, { cache: 'no-store' });
			const items = Array.isArray(response.items) ? response.items : [];
			state.historyItems = items;
			state.historyLoaded = true;
			writeAuditCache(action, hideReadOnly, items);
			if (state.historyObserver) {
				state.historyObserver.disconnect();
				state.historyObserver = null;
			}
			if (!items.length) {
				if (isSimpleMode()) {
					els.historyBody.innerHTML = '<tr class="sae-history-row-simple"><td colspan="5" class="sae-empty">No recent activity yet for these filters.</td></tr>';
				} else {
					els.historyBody.innerHTML = hideReadOnly
						? '<tr><td colspan="5" class="sae-empty">No non-read-only entries for this filter.</td></tr>'
						: '<tr><td colspan="5" class="sae-empty">No audit entries for this filter.</td></tr>';
				}
				if (!state.plan) {
					renderPlan();
				}
				return;
			}
			els.historyBody.innerHTML = items.map(renderHistoryRow).join('');
		} catch (error) {
			state.historyItems = [];
			state.historyLoaded = false;
			els.historyBody.innerHTML = `<tr><td colspan="5" class="sae-empty">Failed to load audit history: ${escapeHtml(error.message || 'unknown error')}</td></tr>`;
		} finally {
			state.historyLoading = false;
			if (!state.plan) {
				renderPlan();
			}
		}
	}

	function renderHealthCards(status) {
		const health = status && status.health ? status.health : {};
		const cards = Object.keys(health).map((key) => {
			const item = health[key] || {};
			const toneClass = item.status === 'error' ? 'sae-health-dot--error' : item.status === 'warning' ? 'sae-health-dot--warning' : '';
			const responseMs = typeof item.duration_ms === 'number' ? ` (${item.duration_ms}ms)` : '';
			return `
				<div class="sae-health-card">
					<h4>${escapeHtml(HEALTH_LABELS[key] || key)}</h4>
					<p><span class="sae-health-dot ${toneClass}"></span>${escapeHtml((item.message || '--') + responseMs)}</p>
				</div>
			`;
		});
		if (els.healthCards) {
			els.healthCards.innerHTML = cards.join('');
		}
	}

	function getPostOptionsSignature(posts) {
		return (posts || [])
			.map((post) => `${post.post_id}:${post.post_title || ''}:${post.post_slug || ''}:${post.post_type || ''}:${post.post_status || ''}:${post.word_count || 0}`)
			.join('|');
	}

	function getSelectablePosts(posts) {
		return (posts || []).filter((post) => {
			if (!post || !Number(post.post_id || 0)) {
				return false;
			}
			const status = String(post.post_status || '').trim().toLowerCase();
			return !!status && !['auto-draft', 'trash', 'inherit'].includes(status);
		});
	}

	function isAutoDetectExcludedPost(post) {
		const title = String(post && post.post_title ? post.post_title : '').trim().toLowerCase();
		const slug = String(post && post.post_slug ? post.post_slug : '').trim().toLowerCase();
		return title.includes('[struo-eval-temp]') || slug.startsWith('struo-eval-');
	}

	function getAutoDetectCandidate(posts) {
		return (posts || [])
			.filter((post) => post && Number(post.post_id || 0))
			.filter((post) => String(post.post_status || '').trim().toLowerCase() === 'publish')
			.filter((post) => !isAutoDetectExcludedPost(post))
			.map((post) => ({
				post,
				modified: Date.parse(String(post.post_modified_gmt || '')) || 0,
			}))
			.sort((left, right) => right.modified - left.modified || Number(right.post.post_id) - Number(left.post.post_id))
			.map((entry) => entry.post)[0] || null;
	}

	function getSelectedPostDisplayLabel() {
		const selectedPost = getAllowlistedPostById(getSelectedPostId());
		return selectedPost ? getAllowlistedPostDisplayLabel(selectedPost) : '';
	}

	function getPostComboboxQuery() {
		if (!els.postFilter) {
			return '';
		}
		const rawValue = String(els.postFilter.value || '').trim();
		if (!rawValue) {
			return '';
		}
		const selectedLabel = getSelectedPostDisplayLabel();
		if (selectedLabel && rawValue.toLowerCase() === selectedLabel.toLowerCase()) {
			return '';
		}
		return rawValue.toLowerCase();
	}

	function getFilteredPostComboboxItems() {
		const posts = getSelectablePosts(state.status && state.status.allowlist ? state.status.allowlist.posts : []);
		const query = getPostComboboxQuery();
		const typeValue = (els.postTypeFilter && els.postTypeFilter.value ? els.postTypeFilter.value : '').trim().toLowerCase();
		return posts.filter((post) => {
			const postType = String(post && post.post_type ? post.post_type : '').trim().toLowerCase();
			const haystack = [
				post.post_title || '',
				post.post_slug || '',
				String(post.post_id || ''),
				postType === 'post' ? 'blog post article' : 'page landing',
			]
				.join(' ')
				.toLowerCase();
			const typeMatches = !typeValue || postType === typeValue;
			const queryMatches = !query || haystack.includes(query);
			return typeMatches && queryMatches;
		});
	}

	function setPostComboboxOpen(isOpen) {
		state.postComboboxOpen = !!isOpen;
		if (!state.postComboboxOpen) {
			state.postComboboxHighlight = -1;
		}
		if (els.postFilter) {
			els.postFilter.setAttribute('aria-expanded', state.postComboboxOpen ? 'true' : 'false');
			if (!state.postComboboxOpen) {
				els.postFilter.removeAttribute('aria-activedescendant');
			}
		}
		if (els.postComboboxList) {
			els.postComboboxList.hidden = !state.postComboboxOpen;
		}
	}

	function syncPostComboboxFromSelection(options = {}) {
		if (!els.postFilter) {
			return;
		}
		const preserveTypedValue = !!options.preserveTypedValue;
		const selectedPost = getAllowlistedPostById(getSelectedPostId());
		const selectedLabel = selectedPost ? getAllowlistedPostDisplayLabel(selectedPost) : '';
		const selectedMeta = selectedPost
			? `${formatSelectedPostTypeLabel(selectedPost.post_type)} · ${formatPostStatusLabel(selectedPost.post_status)} · ID ${selectedPost.post_id}`
			: 'Leave blank to auto-detect from your request.';
		const isAutoDetected = !!selectedPost
			&& state.autoDetectedPostId > 0
			&& Number(selectedPost.post_id) === Number(state.autoDetectedPostId);
		if (!preserveTypedValue) {
			els.postFilter.value = selectedLabel;
		}
		if (els.postComboboxMeta) {
			els.postComboboxMeta.textContent = isAutoDetected ? `Auto-detected · ${selectedMeta}` : selectedMeta;
			els.postComboboxMeta.classList.toggle('is-auto-detected', isAutoDetected);
		}
		if (els.postClear) {
			els.postClear.textContent = 'Auto-detect';
		}
		els.postFilter.setAttribute('aria-invalid', 'false');
	}

	function renderPostComboboxOptions(options = {}) {
		if (!els.postComboboxList) {
			return;
		}
		const forceOpen = !!options.forceOpen;
		const posts = getFilteredPostComboboxItems().slice(0, 12);
		const shouldOpen = forceOpen || state.postComboboxOpen;
		state.postComboboxResults = posts;
		if (!posts.length) {
			if (getPostComboboxQuery()) {
				setPostComboboxOpen(true);
				els.postFilter.setAttribute('aria-invalid', 'true');
				els.postComboboxList.innerHTML = '<div class="sae-post-combobox__empty">No matching allowlisted pages or posts.</div>';
				return;
			}
			els.postComboboxList.innerHTML = '';
			if (!shouldOpen) {
				setPostComboboxOpen(false);
			}
			return;
		}

		if (!shouldOpen) {
			els.postComboboxList.innerHTML = '';
			setPostComboboxOpen(false);
			els.postFilter.setAttribute('aria-invalid', 'false');
			return;
		}

		if (state.postComboboxHighlight < 0 || state.postComboboxHighlight >= posts.length) {
			const selectedId = getSelectedPostId();
		const selectedIndex = posts.findIndex((post) => Number(post.post_id || 0) === selectedId);
		state.postComboboxHighlight = selectedIndex >= 0 ? selectedIndex : 0;
		}

		els.postComboboxList.innerHTML = posts
			.slice(0, 12)
			.map((post, index) => {
				const postId = Number(post.post_id || 0);
				const selected = postId === getSelectedPostId();
				const highlighted = index === state.postComboboxHighlight;
				const resultId = `sae-post-combobox-option-${postId}`;
				return `
					<button
						type="button"
						id="${escapeHtml(resultId)}"
						class="sae-post-combobox__option${selected ? ' is-selected' : ''}${highlighted ? ' is-highlighted' : ''}"
						role="option"
						aria-selected="${selected ? 'true' : 'false'}"
						data-post-option-id="${escapeHtml(String(postId))}">
						<span class="sae-post-combobox__option-title">${escapeHtml(getAllowlistedPostDisplayLabel(post))}</span>
						<span class="sae-post-combobox__option-meta">${escapeHtml(`${formatSelectedPostTypeLabel(post.post_type)} · ${formatPostStatusLabel(post.post_status)} · ID ${postId}`)}</span>
					</button>
				`;
			})
			.join('');
		const activePost = posts[state.postComboboxHighlight];
		if (els.postFilter && activePost) {
			els.postFilter.setAttribute('aria-activedescendant', `sae-post-combobox-option-${Number(activePost.post_id || 0)}`);
			els.postFilter.setAttribute('aria-invalid', 'false');
		}
		setPostComboboxOpen(true);
	}

	function isInsideTargetPicker(node) {
		if (!(node instanceof HTMLElement)) {
			return false;
		}
		const pickerRoot = els.stepTarget || els.postCombobox;
		return !!(pickerRoot && pickerRoot.contains(node));
	}

	function closePostCombobox(options = {}) {
		setPostComboboxOpen(false);
		if (getSelectedPostId()) {
			state.stepTargetExpanded = false;
		}
		syncPostComboboxFromSelection({ preserveTypedValue: !!options.preserveTypedValue });
		updateMissionBriefFlow();
	}

	function movePostComboboxHighlight(direction) {
		const posts = state.postComboboxResults;
		if (!Array.isArray(posts) || !posts.length) {
			return;
		}
		const delta = direction === 'up' ? -1 : 1;
		const nextIndex = state.postComboboxHighlight < 0
			? 0
			: (state.postComboboxHighlight + delta + posts.length) % posts.length;
		state.postComboboxHighlight = nextIndex;
		renderPostComboboxOptions({ forceOpen: true });
	}

	function notifyBundleApplyResults(results) {
		const rows = Array.isArray(results) ? results : [];
		const children = state.plan && Array.isArray(state.plan.children) ? state.plan.children : [];
		rows.forEach((row) => {
			if (!row || !row.ok) {
				return;
			}
			const outcome = String(row.outcome || '');
			if (outcome && outcome !== 'newly_applied' && outcome !== 'already_applied' && outcome !== 'recovered_persisted') {
				return;
			}
			const child = children.find((item) => item && String(item.plan_id || '') === String(row.plan_id || ''));
			notifyMutationApplied(Number((child && child.post_id) || 0));
		});
	}

	function notifyMutationApplied(postId) {
		const id = Number(postId || 0);
		if (!Number.isInteger(id) || id <= 0) {
			return;
		}
		window.dispatchEvent(new CustomEvent('struo-mutation-applied', { detail: { postId: id } }));
	}

	function pinGutenbergTargetFromConfig() {
		if (state.gutenbergTargetPinned || !els.postId) {
			return;
		}
		if (String(config.host || '') !== 'gutenberg') {
			return;
		}
		const wanted = Number(config.current_post_id || 0);
		if (!Number.isInteger(wanted) || wanted <= 0) {
			return;
		}
		const option = els.postId.querySelector('option[value="' + String(wanted) + '"]');
		if (!option) {
			return;
		}
		const current = Number(els.postId.value || 0);
		const operatorChose = current > 0
			&& current !== wanted
			&& Number(state.autoDetectedPostId || 0) !== current;
		if (operatorChose) {
			state.gutenbergTargetPinned = true;
			return;
		}
		state.gutenbergTargetPinned = true;
		selectPostComboboxCandidate(wanted);
	}

	function selectPostComboboxCandidate(postId) {
		const normalizedPostId = Number(postId || 0);
		if (!els.postId) {
			return;
		}
		state.autoDetectedPostId = 0;
		els.postId.value = normalizedPostId > 0 ? String(normalizedPostId) : '';
		els.postId.dispatchEvent(new Event('change', { bubbles: true }));
		closePostCombobox();
	}

	function populatePostSelector(posts) {
		if (!els.postId) {
			return;
		}
		const selectablePosts = getSelectablePosts(posts);
		const signature = getPostOptionsSignature(selectablePosts);
		if (signature === state.postOptionsSignature) {
			syncPostComboboxFromSelection();
			return;
		}

		const currentValue = els.postId.value;
		const options = ['<option value="">Auto-detect from request</option>'];
		selectablePosts.forEach((post) => {
			const postType = String(post.post_type || '').trim().toLowerCase();
			const suffix = postType === 'post' ? ' [Blog]' : '';
			const label = `${post.post_id} — ${post.post_title || post.post_slug || 'Untitled'}${suffix}`;
			options.push(
				`<option value="${escapeHtml(post.post_id)}" data-post-type="${escapeHtml(postType)}" data-post-title="${escapeHtml(post.post_title || '')}" data-post-slug="${escapeHtml(post.post_slug || '')}" data-word-count="${escapeHtml(String(post.word_count || 0))}" data-post-status="${escapeHtml(post.post_status || '')}">${escapeHtml(label)}</option>`
			);
		});

		els.postId.innerHTML = options.join('');
		state.postOptionsSignature = signature;

		if (currentValue && selectablePosts.some((post) => String(post.post_id) === currentValue)) {
			els.postId.value = currentValue;
			applyPostFilter();
			return;
		}

		if (!currentValue && !state.preserveAutoDetectSelection) {
			const candidate = getAutoDetectCandidate(selectablePosts);
			if (candidate) {
				state.autoDetectedPostId = Number(candidate.post_id);
				els.postId.value = String(state.autoDetectedPostId);
				els.postId.dispatchEvent(new Event('change', { bubbles: true }));
				applyPostFilter();
				return;
			}
		}
		applyPostFilter();
	}

	function applyPostFilter() {
		if (!els.postFilter) {
			return;
		}
		renderPostComboboxOptions({ forceOpen: document.activeElement === els.postFilter });
	}

	function renderFindings(data) {
		if (!els.findingsList) {
			return;
		}
		const connected = !!(data && data.connected);
		const items = data && Array.isArray(data.items) ? data.items : [];
		if (!connected) {
			const settingsUrl = config.admin && config.admin.settings ? String(config.admin.settings) : '';
			els.findingsList.innerHTML = `
				<div class="sae-findings__empty">
					<p>${escapeHtml('Search Console and Analytics are not connected.')}</p>
					<p class="sae-help">${escapeHtml('Struo will not invent numbers. Connect them in Settings when you want Findings on this list.')}</p>
					${settingsUrl ? `<a class="button button-secondary" href="${escapeHtml(settingsUrl)}">${escapeHtml('Open Settings')}</a>` : ''}
				</div>
			`;
			return;
		}
		if (data && data.notice === 'fetch_failed') {
			els.findingsList.innerHTML = `<p class="sae-help">${escapeHtml('Search Console and Analytics could not be loaded. The list stays empty.')}</p>`;
			return;
		}
		if (!items.length) {
			els.findingsList.innerHTML = `<p class="sae-help">${escapeHtml('No findings in the last 28 days.')}</p>`;
			return;
		}
		els.findingsList.innerHTML = items.map((item) => {
			const id = String(item.id || '');
			const title = String(item.title || item.post_title || '').trim() || 'Finding';
			const source = String(item.source || '') === 'analytics' ? 'Analytics' : 'Search Console';
			const page = String(item.post_title || '').trim();
			const summary = String(item.summary || '').trim();
			const meta = [source, page, summary].filter(Boolean).join(' · ');
			const pill = String(item.source || '') === 'analytics' ? 'Analytics' : 'Search';
			return `
				<article class="sae-finding" data-finding-id="${escapeHtml(id)}">
					<div>
						<p class="sae-finding__title">${escapeHtml(title)}</p>
						<p class="sae-finding__meta">${escapeHtml(meta)}</p>
					</div>
					<div class="sae-finding__actions">
						<span class="sae-finding__pill">${escapeHtml(pill)}</span>
						<button type="button" class="button button-secondary" data-finding-action="open">${escapeHtml('Open')}</button>
					</div>
				</article>
			`;
		}).join('');
	}

	function findingSourceLabel(source) {
		return String(source || '') === 'analytics' ? 'Analytics' : 'Search Console';
	}

	function renderFindingDetail(item) {
		if (!els.findingsList || !item || typeof item !== 'object') {
			return;
		}
		const title = String(item.title || item.post_title || '').trim() || 'Finding';
		const source = findingSourceLabel(item.source);
		const page = String(item.post_title || '').trim();
		const windowLabel = String(item.window || 'last_28_days').replace(/_/g, ' ');
		const meta = [source, page, windowLabel, 'not a plan'].filter(Boolean).join(' · ');
		const metrics = item.metrics && typeof item.metrics === 'object' && !Array.isArray(item.metrics) ? item.metrics : {};
		const metricLines = Object.keys(metrics).map((key) => {
			const label = String(key).replace(/_/g, ' ');
			return `<p>${escapeHtml(label)} ${escapeHtml(String(metrics[key]))}</p>`;
		}).join('');
		const summary = String(item.summary || '').trim();
		const postUrl = String(item.post_url || '').trim();
		const view = /^https?:\/\//i.test(postUrl)
			? `<a class="button button-secondary" href="${escapeHtml(postUrl)}" target="_blank" rel="noopener">${escapeHtml('View public page')}</a>`
			: '';
		els.findingsList.innerHTML = `
			<article class="sae-finding-detail">
				<p class="sae-finding-detail__title">${escapeHtml(title)}</p>
				<p class="sae-finding-detail__meta">${escapeHtml(meta)}</p>
				${summary ? `<p class="sae-finding-detail__summary">${escapeHtml(summary)}</p>` : ''}
				<p class="sae-finding-detail__label">${escapeHtml('What Google reported')}</p>
				<div class="sae-finding-detail__metrics">${metricLines || `<p>${escapeHtml('No extra numbers.')}</p>`}</div>
				<div class="sae-finding-detail__actions">
					${view}
					<button type="button" class="button button-secondary" data-finding-action="back">${escapeHtml('Back to Findings')}</button>
				</div>
				<p class="sae-help">${escapeHtml('There is no Apply here. To change the page, describe it in Brief like any other Improve.')}</p>
			</article>
		`;
	}

	async function handleFindingsClick(event) {
		const target = event.target;
		if (!target || !els.findingsList || !els.findingsList.contains(target)) {
			return;
		}
		const button = target.closest('[data-finding-action]');
		if (!button) {
			return;
		}
		const action = String(button.getAttribute('data-finding-action') || '');
		if (action === 'back') {
			renderFindings(state.findings || { connected: true, items: [] });
			return;
		}
		if (action !== 'open') {
			return;
		}
		const row = button.closest('[data-finding-id]');
		const id = row ? String(row.getAttribute('data-finding-id') || '') : '';
		if (!/^[a-f0-9]{16}$/.test(id) || !rest.findings) {
			return;
		}
		try {
			const item = await requestJson(`${String(rest.findings).replace(/\/$/, '')}/${id}`);
			renderFindingDetail(item);
		} catch (error) {
			setComposeStatus(error && error.message ? error.message : 'Finding not found.', 'warn');
		}
	}

	async function loadFindings() {
		if (!els.findingsList || !rest.findings) {
			return;
		}
		try {
			const data = await requestJson(rest.findings);
			state.findings = data;
			renderFindings(data);
		} catch (error) {
			state.findings = { connected: true, items: [], notice: 'fetch_failed' };
			renderFindings(state.findings);
		}
	}

	async function loadStatus() {
		try {
			const status = await requestJson(rest.status);
			state.status = status;
			populatePostSelector(status.allowlist && status.allowlist.posts ? status.allowlist.posts : []);
			pinGutenbergTargetFromConfig();
			updateQuickstartVisibility();
			updateQuickActionAvailability();
			renderRateLimitHint(status);
			renderPlatformHint(status);
			if (state.batchMode) {
				renderBatchOperations();
			}
			renderHealthCards(status);
			window.dispatchEvent(new CustomEvent('struo-work-queue-refresh'));
			renderDiscoveryPlans(status);
			loadBlockBrowser();
			warmTemplateRegistryCache();
			warmPatternRegistryCache();

			const killEnabled = !!(status.safety && status.safety.kill_switch);
			const canManageSettings = !!(status.permissions && status.permissions.can_manage_settings);
			if (els.killSwitchLabel) {
				els.killSwitchLabel.textContent = `Kill switch: ${killEnabled ? 'ENABLED' : 'OFF'}`;
				els.killSwitchLabel.style.color = killEnabled ? '#ef4444' : BRAND_PRIMARY;
			}
			if (els.killSwitchToggle) {
				els.killSwitchToggle.textContent = killEnabled ? 'Disable' : 'Enable';
				els.killSwitchToggle.disabled = !canManageSettings;
				els.killSwitchToggle.title = canManageSettings ? '' : 'Manage options capability required.';
			}
			if (!state.plan) {
				renderPlan();
			}
		} catch (error) {
			renderRateLimitHint(null);
			renderPlatformHint(null);
			if (els.healthCards) {
				els.healthCards.innerHTML = `<div class="sae-empty">Failed to load status: ${escapeHtml(error.message || 'unknown error')}</div>`;
			}
			if (!state.plan) {
				renderPlan();
			}
		}
	}

	function startStatusAutoRefresh() {
		if (state.statusRefreshTimer) {
			window.clearInterval(state.statusRefreshTimer);
		}
		state.statusRefreshTimer = window.setInterval(() => {
			if (document.hidden) {
				return;
			}
			loadStatus();
		}, HEALTH_AUTO_REFRESH_MS);
	}

	function setupHistoryLazyLoad() {
		renderHistoryDeferredState();
		const historyPanel = els.historyBody ? els.historyBody.closest('.sae-panel') : null;
		const triggerLazyLoad = () => {
			loadAudit({ force: false });
		};
		if (historyPanel) {
			historyPanel.addEventListener('mouseenter', triggerLazyLoad, { passive: true });
			historyPanel.addEventListener('focusin', triggerLazyLoad);
			historyPanel.addEventListener('click', triggerLazyLoad);
		}
		if ('IntersectionObserver' in window && historyPanel) {
			state.historyObserver = new IntersectionObserver((entries) => {
				if (entries.some((entry) => entry.isIntersecting)) {
					triggerLazyLoad();
				}
			}, {
				root: null,
				rootMargin: '240px 0px',
				threshold: 0.01,
			});
			state.historyObserver.observe(historyPanel);
			return;
		}
		if (historyPanel) {
			window.setTimeout(triggerLazyLoad, 1200);
		}
	}

	function ensureSimpleConfirmModal() {
		if (state.simpleConfirmModal) {
			return state.simpleConfirmModal;
		}

		const overlay = document.createElement('div');
		overlay.className = 'sae-modal';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sae-simple-confirm-title">
				<h3 id="sae-simple-confirm-title"></h3>
				<p class="sae-modal__description"></p>
				<div class="sae-modal__actions">
					<button type="button" class="button button-secondary" data-modal-action="cancel">Cancel</button>
					<button type="button" class="button button-primary" data-modal-action="confirm">Continue</button>
				</div>
			</div>
		`;
		document.body.appendChild(overlay);

		const title = overlay.querySelector('#sae-simple-confirm-title');
		const description = overlay.querySelector('.sae-modal__description');
		const confirmButton = overlay.querySelector('[data-modal-action="confirm"]');
		const cancelButton = overlay.querySelector('[data-modal-action="cancel"]');

		const close = (approved) => {
			if (!state.simpleConfirmModal || typeof state.simpleConfirmModal.resolve !== 'function') {
				return;
			}
			const resolver = state.simpleConfirmModal.resolve;
			state.simpleConfirmModal.resolve = null;
			hideModal(overlay);
			resolver(approved);
		};

		confirmButton.addEventListener('click', () => close(true));
		cancelButton.addEventListener('click', () => close(false));
		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close(false);
			}
		});
		overlay.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				close(false);
				return;
			}
			if (event.key === 'Enter') {
				event.preventDefault();
				close(true);
			}
		});

		state.simpleConfirmModal = {
			overlay,
			title,
			description,
			confirmButton,
			cancelButton,
			resolve: null,
		};

		return state.simpleConfirmModal;
	}

	function requestSimpleApplyConfirmation(options = {}) {
		const modal = ensureSimpleConfirmModal();
		const title = String(options.title || 'Confirm change').trim();
		const description = String(options.description || '').trim();
		const confirmLabel = String(options.confirmLabel || 'Continue').trim();
		const cancelLabel = String(options.cancelLabel || 'Cancel').trim();

		modal.title.textContent = title;
		modal.description.textContent = description;
		modal.confirmButton.textContent = confirmLabel || 'Continue';
		modal.cancelButton.textContent = cancelLabel || 'Cancel';
		showModal(modal.overlay, modal.confirmButton);

		return new Promise((resolve) => {
			modal.resolve = resolve;
		});
	}

	function ensureTextEntryModal() {
		if (state.textEntryModal) {
			return state.textEntryModal;
		}

		const overlay = document.createElement('div');
		overlay.className = 'sae-modal';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sae-text-entry-title">
				<h3 id="sae-text-entry-title"></h3>
				<p class="sae-modal__description"></p>
				<input type="text" class="sae-modal__input" autocomplete="off" />
				<p class="sae-modal__error" hidden></p>
				<div class="sae-modal__actions">
					<button type="button" class="button button-secondary" data-modal-action="cancel">Cancel</button>
					<button type="button" class="button button-primary" data-modal-action="confirm">Save</button>
				</div>
			</div>
		`;
		document.body.appendChild(overlay);

		const title = overlay.querySelector('#sae-text-entry-title');
		const description = overlay.querySelector('.sae-modal__description');
		const input = overlay.querySelector('.sae-modal__input');
		const error = overlay.querySelector('.sae-modal__error');
		const confirmButton = overlay.querySelector('[data-modal-action="confirm"]');
		const cancelButton = overlay.querySelector('[data-modal-action="cancel"]');

		const close = (value) => dismissTextEntryModal(value);

		const submit = () => {
			const value = String(input.value || '').trim();
			if (!value) {
				error.textContent = 'A name is required.';
				error.hidden = false;
				return;
			}
			close(value);
		};

		confirmButton.addEventListener('click', submit);
		cancelButton.addEventListener('click', () => close(null));
		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close(null);
			}
		});
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				close(null);
				return;
			}
			if (event.key === 'Enter') {
				event.preventDefault();
				submit();
			}
		});

		state.textEntryModal = {
			overlay,
			title,
			description,
			input,
			error,
			confirmButton,
			cancelButton,
			resolve: null,
		};

		return state.textEntryModal;
	}

	function dismissTextEntryModal(value = null) {
		if (!state.textEntryModal || typeof state.textEntryModal.resolve !== 'function') {
			return false;
		}
		const resolver = state.textEntryModal.resolve;
		state.textEntryModal.resolve = null;
		if (state.textEntryModal.overlay) {
			hideModal(state.textEntryModal.overlay);
		}
		if (state.textEntryModal.error) {
			state.textEntryModal.error.hidden = true;
			state.textEntryModal.error.textContent = '';
		}
		if (state.textEntryModal.input) {
			state.textEntryModal.input.value = '';
		}
		resolver(value);
		return true;
	}

	function requestTextEntry(options = {}) {
		const modal = ensureTextEntryModal();
		modal.title.textContent = String(options.title || 'Enter a name').trim();
		modal.description.textContent = String(options.description || '').trim();
		modal.input.value = String(options.defaultValue || '').trim();
		modal.input.placeholder = String(options.placeholder || '').trim();
		modal.confirmButton.textContent = String(options.confirmLabel || 'Save').trim() || 'Save';
		modal.cancelButton.textContent = String(options.cancelLabel || 'Cancel').trim() || 'Cancel';
		modal.error.hidden = true;
		modal.error.textContent = '';
		showModal(modal.overlay, modal.input);
		window.requestAnimationFrame(() => {
			if (modal.input && typeof modal.input.select === 'function') {
				modal.input.select();
			}
		});

		return new Promise((resolve) => {
			modal.resolve = resolve;
		});
	}

	function ensureKillSwitchModal() {
		if (state.killSwitchModal) {
			return state.killSwitchModal;
		}

		const overlay = document.createElement('div');
		overlay.className = 'sae-modal';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sae-kill-switch-modal-title">
				<h3 id="sae-kill-switch-modal-title">Confirm kill switch change</h3>
				<p class="sae-modal__description"></p>
				<label class="sae-field" for="sae-kill-switch-confirm-input">Type confirmation phrase</label>
				<input id="sae-kill-switch-confirm-input" class="sae-modal__input" type="text" autocomplete="off" />
				<p class="sae-modal__error" hidden></p>
				<div class="sae-modal__actions">
					<button type="button" class="button button-secondary" data-modal-action="cancel">Cancel</button>
					<button type="button" class="button button-primary" data-modal-action="confirm">Confirm</button>
				</div>
			</div>
		`;
		document.body.appendChild(overlay);

		const description = overlay.querySelector('.sae-modal__description');
		const input = overlay.querySelector('.sae-modal__input');
		const error = overlay.querySelector('.sae-modal__error');
		const confirmButton = overlay.querySelector('[data-modal-action="confirm"]');
		const cancelButton = overlay.querySelector('[data-modal-action="cancel"]');

		const close = (approved) => {
			if (!state.killSwitchModal || typeof state.killSwitchModal.resolve !== 'function') {
				return;
			}
			const resolver = state.killSwitchModal.resolve;
			state.killSwitchModal.resolve = null;
			hideModal(overlay);
			resolver(approved);
		};

		confirmButton.addEventListener('click', () => {
			const expected = state.killSwitchModal ? state.killSwitchModal.expected : '';
			const typed = String(input.value || '').trim().toUpperCase();
			if (!expected || typed !== expected) {
				error.textContent = `Type ${expected || 'the expected phrase'} exactly to continue.`;
				error.hidden = false;
				return;
			}
			close(true);
		});
		cancelButton.addEventListener('click', () => close(false));
		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close(false);
			}
		});
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				close(false);
			}
			if (event.key === 'Enter') {
				event.preventDefault();
				confirmButton.click();
			}
		});

		state.killSwitchModal = {
			overlay,
			description,
			input,
			error,
			resolve: null,
			expected: '',
		};
		return state.killSwitchModal;
	}

	function requestKillSwitchConfirmation(expected, nextEnabled) {
		const modal = ensureKillSwitchModal();
		modal.expected = String(expected || '').trim().toUpperCase();
		modal.description.textContent = `Type ${modal.expected} to ${nextEnabled ? 'enable' : 'disable'} the kill switch.`;
		modal.error.hidden = true;
		modal.error.textContent = '';
		modal.input.value = '';
		showModal(modal.overlay, modal.input);

		return new Promise((resolve) => {
			modal.resolve = resolve;
		});
	}

	function getCommandPaletteItems() {
		const context = getSelectedPostContext();
		const pageLabel = context.title || (context.postType === 'post' ? 'current blog post' : 'current page');
		return [
			{
				id: 'ask',
				title: context.postId ? `Ask about ${pageLabel}` : 'Ask a question',
				description: 'Jump to the composer and start typing.',
				keywords: 'ask question composer prompt',
				enabled: true,
				run: () => {
					if (els.request && typeof els.request.focus === 'function') {
						els.request.focus();
					}
					setComposeStatus('Composer ready.', 'info');
				},
			},
			{
				id: 'analyze',
				title: context.postId ? `Analyze ${pageLabel}` : 'Analyze selected page',
				description: context.postId ? 'Run Scout analysis for the selected page.' : 'Select a page first.',
				keywords: 'audit analyze scout page review',
				enabled: !!context.postId,
				run: () => runScoutAuditAnalysis(),
			},
			{
				id: 'rewrite',
				title: context.postId ? `Rewrite ${pageLabel}` : 'Rewrite selected page',
				description: 'Seed the rewrite quick action.',
				keywords: 'rewrite improve copy quick action',
				enabled: !!context.postId,
				run: () => queueQuickAction('rewrite'),
			},
			{
				id: 'shorten',
				title: context.postId ? `Shorten ${pageLabel}` : 'Shorten selected page',
				description: 'Seed the shorten quick action.',
				keywords: 'shorten simplify concise quick action',
				enabled: !!context.postId,
				run: () => queueQuickAction('shorten'),
			},
			{
				id: 'seo-meta',
				title: context.postId ? `Generate SEO ideas for ${pageLabel}` : 'Generate SEO ideas',
				description: 'Seed SEO title + meta description suggestions.',
				keywords: 'seo meta description search preview',
				enabled: !!context.postId,
				run: () => queueQuickAction('seo_meta'),
			},
			{
				id: 'promote-template',
				title: context.postId ? `Promote ${pageLabel} to template` : 'Promote selected page to template',
				description: context.postId ? 'Save the selected page structure as a reusable template.' : 'Select a page first.',
				keywords: 'template promote page reusable structure registry',
				enabled: !!context.postId,
				run: () => promoteSelectedPageToTemplate(),
			},
			{
				id: 'promote-pattern',
				title: context.postId ? `Promote a section from ${pageLabel}` : 'Promote selected section to pattern',
				description: context.postId ? 'Choose a top-level section and save it as a reusable pattern.' : 'Select a page first.',
				keywords: 'pattern promote section reusable block module registry',
				enabled: !!context.postId,
				run: () => promoteSelectedSectionToPattern(),
			},
			{
				id: 'manage-patterns',
				title: 'Open pattern library',
				description: 'Review saved patterns and update their status.',
				keywords: 'pattern library manage status registry',
				enabled: !!rest.patterns,
				run: () => openPatternLibrary(),
			},
			{
				id: 'create-page',
				title: 'Create a new page',
				description: 'Seed the create-page flow.',
				keywords: 'create new page architect',
				enabled: true,
				run: () => seedScoutCreateRequest('page'),
			},
			{
				id: 'create-blog',
				title: 'Create a new blog post',
				description: 'Seed the create-blog flow.',
				keywords: 'create new blog post article',
				enabled: true,
				run: () => seedScoutCreateRequest('blog'),
			},
		];
	}

	function getCommandPaletteFilteredItems(filterText) {
		const normalized = String(filterText || '').trim().toLowerCase();
		const items = getCommandPaletteItems();
		if (!normalized) {
			return items;
		}
		const tokens = normalized.split(/\s+/).filter(Boolean);
		return items.filter((item) => {
			const haystack = `${item.title} ${item.description} ${item.keywords}`.toLowerCase();
			return tokens.every((token) => haystack.includes(token));
		});
	}

	function ensureCommandPalette() {
		if (state.commandPalette) {
			return state.commandPalette;
		}
		const overlay = document.createElement('div');
		overlay.className = 'sae-modal sae-command-palette';
		overlay.hidden = true;
		overlay.innerHTML = `
			<div class="sae-modal__dialog sae-command-palette__dialog" role="dialog" aria-modal="true" aria-labelledby="sae-command-palette-title">
				<div class="sae-command-palette__header">
					<p class="sae-command-palette__eyebrow">Command palette</p>
					<h3 id="sae-command-palette-title">Jump to anything</h3>
				</div>
				<input class="sae-modal__input sae-command-palette__input" type="text" autocomplete="off" placeholder="Type a command…" />
				<div class="sae-command-palette__results" role="listbox" aria-label="Command palette results"></div>
				<p class="sae-command-palette__hint">Use ↑ ↓ to move, Enter to run, Esc to close.</p>
			</div>
		`;
		document.body.appendChild(overlay);

		const input = overlay.querySelector('.sae-command-palette__input');
		const results = overlay.querySelector('.sae-command-palette__results');
		const stateful = {
			overlay,
			input,
			results,
			items: [],
			selectedIndex: -1,
		};

		const renderItems = () => {
			const visibleItems = getCommandPaletteFilteredItems(input.value || '');
			stateful.items = visibleItems;
			const firstEnabledIndex = visibleItems.findIndex((item) => item.enabled);
			if (stateful.selectedIndex < 0 || stateful.selectedIndex >= visibleItems.length || !(visibleItems[stateful.selectedIndex] && visibleItems[stateful.selectedIndex].enabled)) {
				stateful.selectedIndex = firstEnabledIndex;
			}
			if (!visibleItems.length) {
				results.innerHTML = '<p class="sae-command-palette__empty">No commands match that search yet.</p>';
				return;
			}
			results.innerHTML = visibleItems.map((item, index) => {
				const selected = index === stateful.selectedIndex;
				return `
					<button type="button" class="sae-command-palette__item${selected ? ' is-selected' : ''}" data-command-index="${index}" ${item.enabled ? '' : 'disabled'}>
						<span class="sae-command-palette__item-copy">
							<span class="sae-command-palette__item-title">${escapeHtml(item.title)}</span>
							<span class="sae-command-palette__item-description">${escapeHtml(item.description)}</span>
						</span>
						<span class="sae-command-palette__item-state">${item.enabled ? '↵' : 'Select a page'}</span>
					</button>
				`;
			}).join('');
		};

		const close = () => {
			hideModal(overlay);
			input.value = '';
			stateful.items = [];
			stateful.selectedIndex = -1;
		};

		const executeSelected = () => {
			const item = stateful.items[stateful.selectedIndex] || null;
			if (!item || !item.enabled || typeof item.run !== 'function') {
				return;
			}
			close();
			item.run();
		};

		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				close();
			}
		});
		overlay.addEventListener('keydown', (event) => {
			if (event.key !== 'Escape' || overlay.hidden) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			close();
		});
		results.addEventListener('click', (event) => {
			const target = event.target instanceof HTMLElement ? event.target.closest('[data-command-index]') : null;
			if (!target) {
				return;
			}
			const index = Number(target.getAttribute('data-command-index') || -1);
			if (!Number.isInteger(index) || index < 0) {
				return;
			}
			stateful.selectedIndex = index;
			executeSelected();
		});
		input.addEventListener('input', renderItems);
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				event.stopPropagation();
				close();
				return;
			}
			if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault();
				const direction = event.key === 'ArrowDown' ? 1 : -1;
				const enabledIndexes = stateful.items
					.map((item, index) => ({ item, index }))
					.filter(({ item }) => item.enabled)
					.map(({ index }) => index);
				if (!enabledIndexes.length) {
					return;
				}
				const currentPos = enabledIndexes.indexOf(stateful.selectedIndex);
				const nextPos = currentPos < 0
					? 0
					: (currentPos + direction + enabledIndexes.length) % enabledIndexes.length;
				stateful.selectedIndex = enabledIndexes[nextPos];
				renderItems();
				const active = results.querySelector(`[data-command-index="${stateful.selectedIndex}"]`);
				if (active && typeof active.scrollIntoView === 'function') {
					active.scrollIntoView({ block: 'nearest' });
				}
				return;
			}
			if (event.key === 'Enter') {
				event.preventDefault();
				event.stopPropagation();
				executeSelected();
			}
		});

		state.commandPalette = {
			open() {
				renderItems();
				showModal(overlay, input);
			},
			close,
			isOpen() {
				return !overlay.hidden;
			},
		};
		return state.commandPalette;
	}

	async function toggleKillSwitch() {
		if (!state.status || !state.status.safety) {
			return;
		}

		const current = !!state.status.safety.kill_switch;
		const next = !current;
		const expected = next ? 'ENABLE' : 'DISABLE';
		const confirmed = await requestKillSwitchConfirmation(expected, next);
		if (!confirmed) {
			setComposeStatus('Kill switch change cancelled.', 'warn');
			return;
		}

		try {
			await requestJson(rest.kill_switch, {
				method: 'POST',
				body: { enabled: next },
			});
			await Promise.all([loadStatus(), loadAudit()]);
			setComposeStatus(`Kill switch ${next ? 'enabled' : 'disabled'}.`, 'info');
		} catch (error) {
			setComposeStatus(`Kill switch update failed: ${error.message || 'unknown error'}`, 'error');
		}
	}

	function onScoutActionClick(event) {
		const target = event.target;
		if (!target || !(target instanceof HTMLElement)) {
			return;
		}
		const button = target.closest('button[data-scout-action]');
		if (!button || !(button instanceof HTMLElement)) {
			return;
		}
		const action = String(button.getAttribute('data-scout-action') || '').trim().toLowerCase();
		if (!action) {
			return;
		}
		if (action === 'analyze-page' || action === 'refresh-analysis') {
			runScoutAuditAnalysis();
			return;
		}
		if (action === 'review-issue') {
			runScoutIssueAction(Number(button.getAttribute('data-scout-issue-index')), true);
			return;
		}
		if (action === 'seed-edit') {
			seedSelectedPageEditRequest();
			return;
		}
		if (action === 'seed-create-page') {
			seedScoutCreateRequest('page');
			return;
		}
		if (action === 'seed-create-blog') {
			seedScoutCreateRequest('blog');
			return;
		}
		if (action === 'promote-template') {
			promoteSelectedPageToTemplate();
			return;
		}
		if (action === 'promote-pattern') {
			void promoteSelectedSectionToPattern();
			return;
		}
		if (action === 'manage-patterns') {
			void openPatternLibrary();
			return;
		}
		if (action === 'focus-composer' && els.request) {
			els.request.focus();
			els.request.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

		function bindEvents() {
			els.generatePlan.addEventListener('click', handleGeneratePlan);
			els.apply.addEventListener('click', handleApply);
			if (els.bundleApprove) {
				els.bundleApprove.addEventListener('click', () => {
					void handleBundleApprove();
				});
			}
			if (els.bundleApplyRemaining) {
				els.bundleApplyRemaining.addEventListener('click', () => {
					void handleBundleApplyRemaining();
				});
			}
			if (els.bundleDismiss) {
				els.bundleDismiss.addEventListener('click', () => {
					void handleBundleDismiss();
				});
			}
			if (els.bundleRows) {
				els.bundleRows.addEventListener('change', (event) => {
					void handleBundleSelectChange(event);
				});
				els.bundleRows.addEventListener('click', (event) => {
					const button = event.target && event.target.closest ? event.target.closest('[data-bundle-apply]') : null;
					if (!button) {
						return;
					}
					const planId = String(button.getAttribute('data-bundle-apply') || '');
					if (/^[a-f0-9]{16}$/.test(planId)) {
						void handleBundleApplyThis(planId);
					}
				});
			}
			updateQuickstartVisibility();
			els.batchMode.addEventListener('change', () => {
				state.batchMode = !!els.batchMode.checked;
				renderComposeMode();
				updateQuickstartVisibility();
				setComposeStatus(state.batchMode ? 'Batch mode enabled. Build operations and preview.' : '');
			});
		els.batchAddUpdate.addEventListener('click', () => {
			state.batchOperations = state.batchOperations.concat([createBatchOperation('update')]);
			renderBatchOperations();
		});
		els.batchAddInsert.addEventListener('click', () => {
			state.batchOperations = state.batchOperations.concat([createBatchOperation('insert')]);
			renderBatchOperations();
		});
		els.batchAddRemove.addEventListener('click', () => {
			state.batchOperations = state.batchOperations.concat([createBatchOperation('remove')]);
			renderBatchOperations();
		});
		els.batchClear.addEventListener('click', () => {
			state.batchOperations = [];
			renderBatchOperations();
			setComposeStatus('Batch operations cleared.', 'warn');
		});
		els.batchList.addEventListener('click', (event) => {
			const target = event.target;
			if (!target || !(target instanceof HTMLElement)) {
				return;
			}
			const card = target.closest('.sae-batch-op');
			if (!card) {
				return;
			}
			const opId = card.getAttribute('data-batch-id');
			if (!opId) {
				return;
			}
			const action = target.getAttribute('data-batch-action');
			if (action === 'delete') {
				state.batchOperations = state.batchOperations.filter((operation) => operation.id !== opId);
				renderBatchOperations();
				return;
			}
			if (action === 'move-up') {
				moveBatchOperation(opId, 'up');
				return;
			}
			if (action === 'move-down') {
				moveBatchOperation(opId, 'down');
			}
		});
		els.batchList.addEventListener('input', (event) => {
			const target = event.target;
			if (!target || !(target instanceof HTMLElement)) {
				return;
			}
			const fieldName = target.getAttribute('data-batch-field');
			if (!fieldName) {
				return;
			}
			const card = target.closest('.sae-batch-op');
			if (!card) {
				return;
			}
			const opId = card.getAttribute('data-batch-id');
			const operationIndex = state.batchOperations.findIndex((operation) => operation.id === opId);
			if (operationIndex < 0) {
				return;
			}

			const nextOperations = state.batchOperations.slice();
			const nextOperation = Object.assign({}, nextOperations[operationIndex]);
			const value = target instanceof HTMLInputElement && target.type === 'checkbox'
				? target.checked
				: target.value;
			nextOperation[fieldName] = value;
			nextOperations[operationIndex] = normalizeBatchOperation(nextOperation);
			state.batchOperations = nextOperations;

			if (fieldName === 'action' || fieldName === 'remove_all' || fieldName === 'position_type' || fieldName === 'target_type' || fieldName === 'position_target_type') {
				renderBatchOperations();
			}
		});
		els.cancelPlan.addEventListener('click', () => {
			discardActivePlanRequest();
			clearPendingPlanContext();
			resetPlanState();
			setIntentRailMode('');
			setApplyStatus(isSimpleMode() ? 'Cancelled. Nothing was changed.' : 'Plan cancelled.', 'warn');
			clearReceipt();
		});
		els.clear.addEventListener('click', () => {
			discardActivePlanRequest();
			clearPendingPlanContext();
			els.request.value = '';
			clearRequestHistory();
			if (els.batchBundleName) {
				els.batchBundleName.value = '';
			}
			state.batchOperations = [];
			resetPlanState();
			setIntentRailMode('');
			setComposeStatus('');
			clearReceipt();
			renderBatchOperations();
			updateQuickstartVisibility();
			updateActionButtonHints();
		});
		if (els.refreshStatus) {
			els.refreshStatus.addEventListener('click', () => {
				void Promise.all([loadStatus(), loadFindings()]);
			});
		}
		if (els.agentPlansList) {
			els.agentPlansList.addEventListener('click', handleAgentPlansClick);
		}
		if (els.findingsList) {
			els.findingsList.addEventListener('click', handleFindingsClick);
		}
		if (els.refreshHistory) {
			els.refreshHistory.addEventListener('click', () => loadAudit({ force: true }));
		}
		if (els.appModeToggle) {
			els.appModeToggle.addEventListener('click', () => {
				setAppMode(!state.appMode);
				setComposeStatus(state.appMode ? 'App mode enabled. Use “Back to WP Admin” anytime.' : 'App mode disabled.', 'info');
			});
		}
		if (els.modeToggle) {
			els.modeToggle.addEventListener('click', () => {
				const nextMode = state.consoleMode === 'dev' ? 'simple' : 'dev';
				setConsoleMode(nextMode);
				setComposeStatus(nextMode === 'dev' ? 'Developer mode enabled.' : 'Simple mode enabled.', 'info');
			});
		}
		if (els.historyApplyFilter) {
			els.historyApplyFilter.addEventListener('click', () => loadAudit({ force: true }));
		}
		if (els.historyHideReads) {
			els.historyHideReads.addEventListener('change', () => loadAudit({ force: true }));
		}
		if (els.historyExport) {
			els.historyExport.addEventListener('click', handleHistoryExport);
		}
		if (els.killSwitchToggle) {
			els.killSwitchToggle.addEventListener('click', toggleKillSwitch);
		}
		els.postId.addEventListener('change', () => {
			state.stepTargetExpanded = false;
			state.preserveAutoDetectSelection = !els.postId.value;
			clearReviewStateForPostSwitch(els.postId.value);
			syncPostComboboxFromSelection();
			setPostComboboxOpen(false);
			updateQuickstartVisibility();
			updateQuickActionAvailability();
			updateIntentRailAvailability();
			updateActionButtonHints();
			warmSelectedPostBlocks(getSelectedPostId());
			loadBlockBrowser();
			renderPlan();
		});
		if (els.intentRail) {
			els.intentRail.addEventListener('click', (event) => {
				const target = event.target instanceof HTMLElement ? event.target.closest('[data-intent-rail]') : null;
				if (!target || !(target instanceof HTMLButtonElement) || target.disabled) {
					return;
				}
				handleIntentRailAction(target.getAttribute('data-intent-rail'));
			});
		}
		if (els.stepTargetChange) {
			els.stepTargetChange.addEventListener('click', () => {
				state.stepTargetExpanded = !state.stepTargetExpanded;
				els.stepTargetChange.setAttribute('aria-expanded', String(state.stepTargetExpanded));
				updateMissionBriefFlow();
				if (state.stepTargetExpanded) {
					if (els.postFilter) {
						els.postFilter.focus();
						renderPostComboboxOptions({ forceOpen: true });
					}
					return;
				}
				closePostCombobox();
			});
		}
		if (els.postFilter) {
			els.postFilter.addEventListener('focus', () => {
				state.postComboboxHighlight = -1;
				renderPostComboboxOptions({ forceOpen: true });
			});
			els.postFilter.addEventListener('input', () => {
				state.postComboboxHighlight = -1;
				applyPostFilter();
			});
			els.postFilter.addEventListener('keydown', (event) => {
				if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
					event.preventDefault();
					if (!state.postComboboxOpen) {
						renderPostComboboxOptions({ forceOpen: true });
					}
					movePostComboboxHighlight(event.key === 'ArrowUp' ? 'up' : 'down');
					return;
				}
				if (event.key === 'Enter') {
					if (state.postComboboxOpen && state.postComboboxResults[state.postComboboxHighlight]) {
						event.preventDefault();
						selectPostComboboxCandidate(state.postComboboxResults[state.postComboboxHighlight].post_id);
					}
					return;
				}
				if (event.key === 'Escape') {
					event.preventDefault();
					closePostCombobox();
				}
			});
		}
		if (els.postCombobox) {
			els.postCombobox.addEventListener('focusout', (event) => {
				if (!state.postComboboxOpen) {
					return;
				}
				const nextFocused = event.relatedTarget instanceof HTMLElement ? event.relatedTarget : null;
				if (nextFocused && els.postCombobox.contains(nextFocused)) {
					return;
				}
				window.requestAnimationFrame(() => {
					const activeElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
					if (activeElement && els.postCombobox.contains(activeElement)) {
						return;
					}
					closePostCombobox();
				});
			});
		}
		if (els.postClear) {
			els.postClear.addEventListener('click', () => {
				selectAutoDetectPostContext();
				closePostCombobox();
			});
		}
		if (els.postTypeFilter) {
			els.postTypeFilter.addEventListener('change', () => {
				state.postComboboxHighlight = -1;
				applyPostFilter();
			});
		}
		if (els.postComboboxList) {
			els.postComboboxList.addEventListener('click', (event) => {
				const target = event.target instanceof HTMLElement ? event.target.closest('[data-post-option-id]') : null;
				if (!target) {
					return;
				}
				const postId = Number(target.getAttribute('data-post-option-id') || 0);
				if (!postId) {
					return;
				}
				selectPostComboboxCandidate(postId);
			});
		}
		if (els.blockBrowserRefresh) {
			els.blockBrowserRefresh.addEventListener('click', () => {
				loadBlockBrowser(true);
			});
		}
		els.removeConfirmInput.addEventListener('input', updateApplyButtonState);
		els.request.addEventListener('input', () => {
			if (!state.isAutofill) {
				state.lastRequestOrigin = 'manual';
			}
			if (!state.isCyclingRequestHistory) {
				resetRequestHistoryCursor();
			}
			setRequestValidationState('');
			updateQuickstartVisibility();
			updateActionButtonHints();
		});
		els.request.addEventListener('keydown', (event) => {
			if (!state.batchMode && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
				const requestText = (els.request.value || '').trim();
				if (!requestText || state.requestHistoryCursor >= 0) {
					const direction = event.key === 'ArrowUp' ? -1 : 1;
					if (cycleRequestHistory(direction)) {
						event.preventDefault();
						return;
					}
				}
			}
			if ((event.metaKey || event.ctrlKey) && event.key === 'Enter' && !els.generatePlan.disabled) {
				event.preventDefault();
				handleGeneratePlan();
			}
		});
		document.addEventListener('keydown', (event) => {
			if ((event.metaKey || event.ctrlKey) && String(event.key || '').toLowerCase() === 'k') {
				event.preventDefault();
				const palette = ensureCommandPalette();
				if (palette.isOpen()) {
					palette.close();
				} else {
					palette.open();
				}
				return;
			}
			if (event.key === 'Escape' && state.plan) {
				event.preventDefault();
				resetPlanState();
				clearReceipt();
				setApplyStatus(isSimpleMode() ? 'Cancelled. Nothing was changed.' : 'Plan cancelled.', 'warn');
				return;
			}
			if ((event.metaKey || event.ctrlKey) && event.shiftKey && event.key === 'Enter' && !els.apply.disabled) {
				if (!isSimpleMode() && !isDestructivePhraseValid()) {
					event.preventDefault();
					setApplyStatus(`Type "${REMOVE_CONFIRM_PHRASE}" to allow this destructive apply.`, 'warn');
					updateApplyButtonState();
					return;
				}
				event.preventDefault();
				handleApply();
			}
		});
		if (els.quickstart) {
			els.quickstart.addEventListener('click', (event) => {
				const target = event.target;
				if (!target || !(target instanceof HTMLElement)) {
					return;
				}
				const button = target.closest('button[data-chip]');
				if (!button || !(button instanceof HTMLElement)) {
					return;
				}
				const chip = button.getAttribute('data-chip');
				if (!chip) {
					return;
				}
				clearPendingPlanContext();
				state.lastRequestOrigin = 'quickstart';
				smoothFillComposer(chip);
				updateQuickstartVisibility();
			});
		}
		if (els.quickActions) {
			els.quickActions.addEventListener('click', (event) => {
				const target = event.target;
				if (!target || !(target instanceof HTMLElement)) {
					return;
				}
				const button = target.closest('button[data-quick-action]');
				if (!button || !(button instanceof HTMLElement)) {
					return;
				}
				const action = button.getAttribute('data-quick-action');
				queueQuickAction(action);
			});
		}
		if (els.planEmpty) {
			els.planEmpty.addEventListener('click', onScoutActionClick);
		}
		if (els.scoutLive) {
			els.scoutLive.addEventListener('click', onScoutActionClick);
		}
		if (els.advanced) {
			els.advanced.addEventListener('click', onScoutActionClick);
			els.advanced.addEventListener('toggle', () => {
				const summary = els.advanced.querySelector('summary');
				if (summary) {
					summary.setAttribute('aria-expanded', els.advanced.open ? 'true' : 'false');
				}
			});
		}
		if (els.scoutLiveToggle) {
			els.scoutLiveToggle.addEventListener('click', () => {
				const next = els.scoutLiveToggle.getAttribute('aria-expanded') !== 'true';
				els.scoutLiveToggle.setAttribute('aria-expanded', next ? 'true' : 'false');
				if (els.scoutLivePanel) {
					els.scoutLivePanel.hidden = !next;
				}
			});
		}
		document.addEventListener('click', (event) => {
			if (!els.postCombobox || !state.postComboboxOpen) {
				return;
			}
			const target = event.target instanceof HTMLElement ? event.target : null;
			if (isInsideTargetPicker(target)) {
				return;
			}
			closePostCombobox();
		});
		els.receipt.addEventListener('click', (event) => {
			const target = event.target;
			if (!target || !(target instanceof HTMLElement)) {
				return;
			}
			const receiptAction = target.dataset.receiptAction;
			if (receiptAction === 'edit-created') {
				const postId = Number(target.dataset.receiptPostId || 0);
				if (postId > 0) {
					selectAllowlistedPost({
						post_id: postId,
						post_title: String(target.dataset.receiptPostTitle || '').trim(),
						post_type: String(target.dataset.receiptPostType || '').trim().toLowerCase() || 'page',
						post_status: String(target.dataset.receiptPostStatus || '').trim().toLowerCase() || 'draft',
					});
				}
				clearReceipt();
				resetPlanState();
				setApplyStatus('');
				setComposeStatus(isSimpleMode() ? 'Ready to edit this draft.' : 'Draft selected. Continue editing.', 'info');
				els.request.focus();
				els.request.scrollIntoView({ behavior: 'smooth', block: 'center' });
				return;
			}
			if (receiptAction === 'undo') {
				handleReceiptUndo();
				return;
			}
			if (receiptAction === 'save-template') {
				handleReceiptSaveTemplate();
				return;
			}
			if (receiptAction === 'follow-up-review') {
				runScoutIssueAction(Number(target.dataset.receiptFollowIndex || 0), true);
				return;
			}
			if (receiptAction === 'follow-up-refresh') {
				runScoutAuditAnalysis();
				return;
			}
			if (receiptAction !== 'run-another') {
				return;
			}
			clearReceipt();
			resetPlanState();
			setIntentRailMode('');
			setApplyStatus('');
			if (state.batchMode) {
				const batchFocusTarget = els.batchList ? els.batchList.querySelector('input, select, textarea, button') : null;
				if (batchFocusTarget instanceof HTMLElement) {
					batchFocusTarget.focus();
				}
				if (els.batchModeWrap) {
					els.batchModeWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			} else {
				els.request.focus();
				els.request.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
			setComposeStatus('Ready for another request.', 'info');
		});
		els.planContent.addEventListener('click', (event) => {
			const target = event.target;
			if (!target || !(target instanceof HTMLElement)) {
				return;
			}
			const createOutlineButton = target.closest('button[data-create-outline-action]');
			if (createOutlineButton && createOutlineButton instanceof HTMLElement) {
				const action = createOutlineButton.getAttribute('data-create-outline-action');
				if (action === 'continue') {
					handleCreateOutlineContinue();
				} else if (action === 'toggle-all') {
					const hasUnchecked = Array.isArray(state.createOutlineSelection)
						&& state.createOutlineSelection.some((isChecked) => !isChecked);
					setAllCreateOutlineSections(hasUnchecked);
					renderPlan();
					const selectedCount = getSelectedOutlineSectionIndexes().length;
					setComposeStatus(
						selectedCount > 0
							? `${selectedCount} section${selectedCount === 1 ? '' : 's'} selected.`
							: 'Select at least one section to continue.',
						selectedCount > 0 ? 'info' : 'warn'
					);
				}
				return;
			}
			const auditButton = target.closest('button[data-audit-action]');
			if (auditButton && auditButton instanceof HTMLElement) {
				const auditAction = String(auditButton.getAttribute('data-audit-action') || '').trim().toLowerCase();
				const plan = isAuditPlan(state.plan) ? state.plan : null;
				if (!plan) {
					return;
				}
				if (auditAction === 'compose-all') {
					const fixAllCommand = buildAuditFixAllCommand(plan);
					if (!fixAllCommand) {
						return;
					}
					fillComposerFromAuditCommand(fixAllCommand, {
						planFromFinding: false,
						customStatus: isSimpleMode()
							? 'Audit checklist added. Apply these fixes one at a time.'
							: 'Audit checklist added to composer. Use each fix one at a time, not as a bulk plan.',
					});
					return;
				}
					const index = Number(auditButton.getAttribute('data-audit-index'));
					if (!Number.isInteger(index) || index < 0) {
						return;
					}
					const issue = getAuditIssues(plan)[index] || null;
					const fixCommand = buildPlannerSafeAuditCommand(issue, plan && plan.post ? plan.post : null);
					if (!fixCommand) {
						return;
					}
				fillComposerFromAuditCommand(fixCommand, { planFromFinding: auditAction === 'apply' });
				return;
			}
			const button = target.closest('button[data-suggestion-index]');
			if (!button || !(button instanceof HTMLElement)) {
				return;
			}
			const action = String(button.getAttribute('data-suggestion-action') || 'use').trim().toLowerCase();
			const index = Number(button.getAttribute('data-suggestion-index'));
			if (!Number.isInteger(index) || index < 0) {
				return;
			}
			const plan = state.plan;
			const suggestions = plan && Array.isArray(plan.suggestions) ? plan.suggestions : [];
			const suggestion = suggestions[index] || null;
			if (!suggestion || (!suggestion.command && !suggestion.candidate_value)) {
				return;
			}
			if (action === 'apply' && !canAutoApplySuggestion(suggestion)) {
				setComposeStatus(
					isSimpleMode()
						? 'This suggestion needs a concrete replacement value first. Use "Use this" to refine it manually.'
						: 'This suggestion is missing a concrete replacement value. Use "Use this" to refine it manually before generating a plan.',
					'warn'
				);
				return;
			}
			const nextCommand = buildPlannerSafeSuggestionCommand(suggestion, plan && plan.post ? plan.post : null);
			clearPendingPlanContext();
			state.pendingPlanRecovery = action === 'apply' && plan ? { plan, origin: 'suggestion_apply' } : null;
			state.lastRequestOrigin = action === 'apply' ? 'suggestion_apply' : 'suggestion';
			resetPlanState();
			clearReceipt();
			smoothFillComposer(nextCommand);
			if (action === 'apply') {
				setComposeStatus(isSimpleMode() ? 'Generating a plan from this suggestion…' : 'Generating plan from suggestion…', 'loading');
				window.setTimeout(() => handleGeneratePlan(), 0);
			} else {
				setComposeStatus(
					isSimpleMode() ? 'Suggestion added. Press Plan changes when ready.' : 'Suggestion added to composer. Generate plan to continue.',
					'info'
				);
			}
		});
		els.planContent.addEventListener('change', (event) => {
			const target = event.target;
			if (!target || !(target instanceof HTMLInputElement)) {
				return;
			}
			if (target.type !== 'checkbox') {
				return;
			}
			const indexValue = target.getAttribute('data-outline-index');
			if (indexValue === null) {
				return;
			}
			const index = Number(indexValue);
			if (!Number.isInteger(index) || index < 0) {
				return;
			}
			setCreateOutlineSection(index, target.checked);
			renderPlan();
		});
		document.addEventListener('visibilitychange', () => {
			if (!document.hidden && state.tokenExpiresAtMs > 0) {
				renderTokenCountdown();
			}
		});
		window.addEventListener('beforeunload', () => {
			if (state.statusRefreshTimer) {
				window.clearInterval(state.statusRefreshTimer);
				state.statusRefreshTimer = null;
			}
			if (state.planRequestController) {
				state.planRequestController.abort();
				state.planRequestController = null;
			}
			if (state.historyObserver) {
				state.historyObserver.disconnect();
				state.historyObserver = null;
			}
		});
	}

	async function boot() {
		setAppMode(isAppModeEnabled(), false);
		setConsoleMode(getStoredConsoleMode(), false);
		relocateModeTogglesIntoAdvanced();
		state.requestHistory = loadRequestHistory();
		resetRequestHistoryCursor();
		const platformSource = (navigator.userAgentData && navigator.userAgentData.platform)
			|| navigator.platform
			|| navigator.userAgent
			|| '';
		const isApple = /Mac|iPhone|iPad|iPod/i.test(platformSource);
		if (els.request && isSimpleMode()) {
			els.request.setAttribute('placeholder', MODE_COPY.simple.requestPlaceholder);
		}
		if (els.generatePlan) {
			els.generatePlan.dataset.shortcut = isApple ? '⌘↵' : 'Ctrl+Enter';
		}
		if (els.apply) {
			els.apply.dataset.shortcut = isApple ? '⌘⇧↵' : 'Ctrl+Shift+Enter';
		}
		updateGenerateButtonLabel();
		updateEmptyStateCopy();
		initializeFadeTargets();
		bindEvents();
		setupHistoryLazyLoad();
		startStatusAutoRefresh();
		state.batchMode = false;
		state.batchOperations = [];
		renderComposeMode();
		resetPlanState();
		clearReceipt();
		updateQuickActionAvailability();
		updateIntentRailAvailability();
		updateActionButtonHints();
		await Promise.all([loadStatus(), loadFindings()]);
		updateIntentRailAvailability();
	}

	window.addEventListener('struo-gutenberg-post-id', (event) => {
		const id = Number(event && event.detail && event.detail.postId || 0);
		if (Number.isInteger(id) && id > 0) {
			config.current_post_id = id;
		}
		pinGutenbergTargetFromConfig();
	});

	boot();
