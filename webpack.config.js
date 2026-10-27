const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'work-queue': path.resolve( process.cwd(), 'src/work-queue/index.js' ),
		'gutenberg-host': path.resolve( process.cwd(), 'src/gutenberg-host/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'assets' ),
		filename: '[name].js',
		clean: false,
	},
};
