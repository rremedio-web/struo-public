#!/usr/bin/env node
// Auto-detect candidate unit cases. Extracts the pure functions from
// assets/console.js and asserts selection behavior. Run: node tests/autodetect-cases.mjs
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const src = await readFile(join(root, 'assets', 'console.js'), 'utf8');

function grabFunction(name) {
	const start = src.indexOf(`function ${name}(`);
	if (start < 0) {
		throw new Error(`${name} not found in console.js`);
	}
	let depth = 0;
	let opened = false;
	for (let i = src.indexOf('{', start); i < src.length; i += 1) {
		if (src[i] === '{') {
			depth += 1;
			opened = true;
		}
		if (src[i] === '}') {
			depth -= 1;
			if (opened && depth === 0) {
				return src.slice(start, i + 1);
			}
		}
	}
	throw new Error(`${name} body not terminated`);
}

const { getAutoDetectCandidate } = new Function(
	`${grabFunction('isAutoDetectExcludedPost')}\n${grabFunction('getAutoDetectCandidate')}\nreturn { getAutoDetectCandidate };`
)();

let failures = 0;
function check(condition, message) {
	if (condition) {
		console.log(`PASS: ${message}`);
	} else {
		console.error(`FAIL: ${message}`);
		failures += 1;
	}
}

const draft = { post_id: 52, post_title: '[struo-eval-temp] privacy probe', post_slug: 'privacy-probe', post_status: 'draft', post_modified_gmt: '2026-08-21 10:00:00' };
const markerSlug = { post_id: 53, post_title: 'Probe', post_slug: 'struo-eval-temp-privacy', post_status: 'publish', post_modified_gmt: '2026-08-21 09:30:00' };
const staging = { post_id: 71, post_title: 'Staging Environments', post_slug: 'staging-environments', post_status: 'publish', post_modified_gmt: '2026-08-19 12:00:00' };
const older = { post_id: 70, post_title: 'Homepage', post_slug: 'homepage', post_status: 'publish', post_modified_gmt: '2026-08-01 09:00:00' };
const untitled = { post_id: 80, post_title: '', post_slug: '', post_status: 'publish', post_modified_gmt: '2026-08-20 08:00:00' };

check(getAutoDetectCandidate([draft]) === null, 'draft is never selected');
check(getAutoDetectCandidate([{ ...draft, post_status: 'publish' }]) === null, '[struo-eval-temp] title is never selected');
check(getAutoDetectCandidate([markerSlug]) === null, 'struo-eval- slug prefix is never selected');
check(getAutoDetectCandidate([staging, older])?.post_id === 71, '"Staging Environments" published page is included');
check(getAutoDetectCandidate([untitled, older])?.post_id === 80, 'published page with empty title+slug falls back to inclusion');
check(
	getAutoDetectCandidate([older, staging, untitled, draft, markerSlug])?.post_id === 80,
	'most recently edited eligible published page wins'
);
check(getAutoDetectCandidate([]) === null, 'no eligible posts -> null');
check(getAutoDetectCandidate([draft, markerSlug]) === null, 'only ineligible posts -> null');

if (failures > 0) {
	console.error(`RESULT: ${failures} case(s) failed`);
	process.exit(1);
}
console.log('RESULT: all auto-detect cases passed');
