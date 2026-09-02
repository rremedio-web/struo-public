'use strict';

const REQUEST_TIMEOUT_MS = 30000;
const CREATE_PLAN_TIMEOUT_MS = 90000;
const CREATE_APPLY_TIMEOUT_MS = 180000;
const REMOVE_CONFIRM_PHRASE = 'APPLY REMOVE';
const APP_MODE_STORAGE_KEY = 'sae_app_mode';
const CONSOLE_MODE_STORAGE_KEY = 'sae_console_mode_v1';
const REQUEST_HISTORY_STORAGE_KEY = 'sae_console_request_history_v1';
const AUDIT_CACHE_STORAGE_KEY = 'sae_console_audit_cache_v1';
const SCOUT_AUDIT_STORAGE_KEY = 'sae_console_scout_audit_v1';
const TEMPLATE_REGISTRY_STORAGE_KEY = 'sae_console_template_registry_v1';
const PATTERN_REGISTRY_STORAGE_KEY = 'sae_console_pattern_registry_v1';
const REQUEST_HISTORY_LIMIT = 10;
const PANEL_FADE_DURATION_MS = 180;
const MODE_TRANSITION_DURATION_MS = 220;
const COMPOSER_STAGE_DONE_DURATION_MS = 1800;
const HEALTH_AUTO_REFRESH_MS = 60000;
const BLOCK_BROWSER_MAX_ITEMS = 30;
const AUDIT_CACHE_TTL_MS = 180000;
const SCOUT_AUDIT_TTL_MS = 900000;
const TEMPLATE_REGISTRY_TTL_MS = 300000;
const PATTERN_REGISTRY_TTL_MS = 300000;
const POST_BLOCKS_CACHE_TTL_MS = 180000;
const config = window.saeConsoleConfig || {};
const rest = config.rest || {};
const adminConfig = config.admin || {};
const features = config.features || {};
const branding = config.branding || {};
const brandingColors = branding.colors || {};
const BRAND_PRIMARY = brandingColors.primary || '#3858e9';
const BRAND_ACCENT = brandingColors.accent || '#ffb300';

const state = {
	status: null,
	plan: null,
	tokenInterval: null,
	tokenExpiresAtMs: 0,
	applyLabelBase: '',
	postOptionsSignature: '',
	autoDetectedPostId: 0,
	planDiffRequestId: 0,
	batchMode: false,
	batchOperations: [],
	appMode: false,
	consoleMode: 'simple',
	requestHistory: [],
	requestHistoryCursor: -1,
	isCyclingRequestHistory: false,
	killSwitchModal: null,
	simpleConfirmModal: null,
	textEntryModal: null,
	statusRefreshTimer: null,
	blockBrowserRequestId: 0,
	blockBrowserLoadedPostId: 0,
	postBlocksCache: {},
	postBlocksRequests: {},
	historyItems: [],
	historyLoaded: false,
	historyLoading: false,
	historyObserver: null,
	planRequestController: null,
	planRequestActive: false,
	agentPlanBusy: false,
	bundleSelectBusy: false,
	bundleSelectQueued: null,
	bundleSelectFlushing: false,
	createOutlineActive: false,
	createOutlineSelection: [],
	pendingCrossField: '',
	pendingResolvedPostId: 0,
	pendingPlanRecovery: null,
	loadingRequestText: '',
	loadingTargetLabel: '',
	lastRequestOrigin: 'manual',
	isAutofill: false,
	composeStageChipState: '',
	composeStageChipTimer: null,
	preserveAutoDetectSelection: false,
	gutenbergTargetPinned: false,
	modeTransitionTimer: null,
	planPreviewRows: [],
	receiptPayload: null,
	progressiveRenderTimers: [],
	commandPalette: null,
	streamPreview: null,
	planStreamRequestId: '',
	templateRegistryItems: [],
	templateRegistryLoaded: false,
	templateRegistryLoading: false,
	patternRegistryItems: [],
	patternRegistryLoaded: false,
	patternRegistryLoading: false,
	postComboboxOpen: false,
	postComboboxHighlight: -1,
	postComboboxResults: [],
	patternStudioModal: null,
	patternLibraryModal: null,
	intentRailMode: '',
	findings: null,
	stepTargetExpanded: false,
	scoutTypeTimer: 0,
	scoutLineText: '',
};

const HEALTH_LABELS = {
	catalog: 'Block Catalog',
	post_probe: 'Page Access',
	audit: 'Audit Log',
};

const MODE_COPY = {
	simple: {
		headerSubtitle:
			'Name the page. Describe the change. Plan first — nothing goes live until you apply.',
		composeTitle: 'What do you want to change?',
		composeSubtitle: '',
		requestLabel: 'Describe your change',
		requestPlaceholder: 'Shorten the pricing hero to one line…',
		postHelp: 'Search for the page you want to change, or leave it on auto-detect.',
		reviewTitle: 'Review Changes',
		reviewSubtitle: 'Check what will change before applying.',
		emptyTitle: 'Nothing to review yet.',
		cancelLabel: 'Cancel',
	},
	dev: {
		headerSubtitle:
			'Plan → preview → approve → apply. All actions remain allowlisted and audit logged.',
		composeTitle: 'Request Composer',
		composeSubtitle:
			'Use single intent for natural-language plans, or switch to batch mode for multiple explicit operations.',
		requestLabel: 'Request',
		requestPlaceholder: "Update the hero headline on the product page to 'Ship with confidence'.",
		postHelp: 'Search the target page, or use filters to narrow to pages or blogs.',
		reviewTitle: 'Plan Review',
		reviewSubtitle: 'Validate target, payload, and risk before apply.',
		emptyTitle: 'No plan loaded.',
		cancelLabel: 'Cancel Plan',
	},
};

const ERROR_MESSAGES = {
	sae_post_not_allowed: {
		message: "This page isn't enabled for AI editing.",
		action:
			'Pick a different page from the dropdown, or ask an admin to add this page in Settings.',
	},
	sae_plan_post_not_allowed: {
		message: "This page isn't enabled for AI editing.",
		action:
			'Pick a different page from the dropdown, or ask an admin to add this page in Settings.',
	},
	sae_kill_switch: {
		message: 'Editing is paused right now.',
		action: 'An admin turned on the kill switch. Contact your admin to re-enable editing.',
	},
	sae_write_disabled: {
		message: 'Editing is paused right now.',
		action: 'An admin has temporarily disabled writes. Contact your admin to re-enable editing.',
	},
	sae_invalid_fields: {
		message: "I couldn't match that request to an editable part of the selected content.",
		action:
			'Try naming what to change (for example, headline, paragraph, or button text) and include the new text in quotes.',
	},
	sae_field_locked: {
		message: "One or more fields are protected and can't be changed.",
		action: 'Try updating a different field, or ask an admin to unlock this field.',
	},
	sae_plan_missing_target: {
		message: "I couldn't figure out which block to edit.",
		action:
			'Try being more specific, for example: "update the hero headline" or "change paragraph at block 3".',
	},
	sae_target_ambiguous: {
		message: 'I found more than one block that could match this request.',
		action:
			'Name the section more explicitly, for example: "update the hero headline" or "change the pricing CTA".',
	},
	sae_plan_missing_fields: {
		message: "I couldn't determine what to change.",
		action: 'Include both field and value, for example: "set headline to New Title".',
	},
	sae_planner_schema_invalid: {
		message: 'The AI planner returned an unexpected response.',
		action: 'Try rephrasing your request, or disable "Always use AI planner" and try again.',
	},
	sae_target_not_found: {
		message: "The block you referenced doesn't exist on this page.",
		action: 'Double-check the block index or anchor, then regenerate the plan.',
	},
	sae_rate_limit: {
		message: "You've hit the request limit.",
		action: 'Wait a few minutes and try again.',
	},
	sae_plan_missing_operation: {
		message: "I couldn't understand what action you want.",
		action: 'Start with a verb like "update", "add", or "remove".',
	},
	sae_plan_post_unresolved: {
		message: "I couldn't match your request to a specific page.",
		action: 'Select a page from the dropdown, or mention the exact page name in your request.',
	},
	sae_no_featured_image: {
		message: 'This page does not have a featured image.',
		action: 'Set a featured image first, then generate alt text.',
	},
	request_aborted: {
		message: 'Request cancelled.',
		action: 'No changes were made. Update your request and try again.',
	},
};
