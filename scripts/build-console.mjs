#!/usr/bin/env node
/**
 * Concatenate authored console source into the shipped assets.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const jsDir = path.join( root, 'assets/src/js' );
const cssDir = path.join( root, 'assets/src/css' );

const jsParts = [ 'state.js', 'api.js', 'queue.js', 'plans.js', 'audit.js', 'modals.js', 'app.js' ];
const cssParts = [ 'tokens.css', 'layout.css', 'components.css' ];

const readPart = ( dir, name ) => {
	const file = path.join( dir, name );
	if ( ! fs.existsSync( file ) ) {
		throw new Error( 'missing ' + file );
	}
	return fs.readFileSync( file, 'utf8' ).replace( /\s+$/, '' );
};

const jsBody = jsParts.map( ( name ) => readPart( jsDir, name ) ).join( '\n\n' );
const cssBody = cssParts.map( ( name ) => readPart( cssDir, name ) ).join( '\n\n' );

fs.writeFileSync( path.join( root, 'assets/console.js' ), '(function () {\n' + jsBody + '\n})();\n' );
fs.writeFileSync( path.join( root, 'assets/console.css' ), cssBody + '\n' );
console.log( 'built assets/console.js and assets/console.css' );
