import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
let failed = 0;
const check = ( label, cond ) => {
	if ( cond ) {
		console.log( 'PASS: ' + label );
		return;
	}
	failed += 1;
	console.log( 'FAIL: ' + label );
};

const js = [ 'state.js', 'api.js', 'queue.js', 'plans.js', 'audit.js', 'modals.js', 'app.js' ];
const css = [ 'tokens.css', 'layout.css', 'components.css' ];
for ( const name of js ) {
	check( 'source ' + name, fs.existsSync( path.join( root, 'assets/src/js', name ) ) );
}
for ( const name of css ) {
	check( 'source ' + name, fs.existsSync( path.join( root, 'assets/src/css', name ) ) );
}

const builtJs = fs.readFileSync( path.join( root, 'assets/console.js' ), 'utf8' );
const builtCss = fs.readFileSync( path.join( root, 'assets/console.css' ), 'utf8' );
check( 'built console.js is an IIFE', builtJs.startsWith( '(function () {' ) && builtJs.trimEnd().endsWith( '})();' ) );
check( 'built console.js contains requestJson', builtJs.includes( 'async function requestJson' ) );
check( 'built console.js contains boot', builtJs.includes( 'boot();' ) );
check( 'built console.js uses generic brand fallback', builtJs.includes( "'#3858e9'" ) && ! builtJs.includes( "'#2db9c0'" ) );
check( 'built console.css has tokens', builtCss.includes( '--sae-ground' ) );
check( 'built console.css keeps the console page hook', builtCss.includes( 'toplevel_page_struo-console' ) );

console.log( failed ? `CONSOLE BUILD CASES RESULT: ${failed} CHECK(S) FAILED` : 'CONSOLE BUILD CASES RESULT: ALL CHECKS PASSED' );
process.exit( failed ? 1 : 0 );
