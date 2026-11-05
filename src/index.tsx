import { createRoot } from '@wordpress/element';
import '@xyflow/react/dist/style.css'; // Here, not in the lazy chunk: PHP only enqueues style-index.css.
import './style.css';
import { App } from './app';

const root = document.getElementById( 'twinspot-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
