'use strict';

module.exports = {
	root: true,
	env: {
		browser: true,
		es2022: true,
	},
	parserOptions: {
		ecmaVersion: 2022,
		sourceType: 'script',
	},
	ignorePatterns: [ 'js/app.js' ],
	rules: {
		// Extracted files share one IIFE after concat; names resolve at build time.
		'no-undef': 'off',
		'no-unused-vars': 'off',
		'no-eval': 'error',
		'no-implied-eval': 'error',
		'no-new-func': 'error',
		eqeqeq: [ 'error', 'always', { null: 'ignore' } ],
	},
};
