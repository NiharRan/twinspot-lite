import { createRoot } from '@wordpress/element';
import './style.css';
import { App } from './app';

const root = document.getElementById( 'twinspot-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
