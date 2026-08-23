( function () {
	'use strict';

	const bodyClasses = window.ASLC && Array.isArray( window.ASLC.editorBodyClasses )
		? window.ASLC.editorBodyClasses
		: [];

	if ( bodyClasses.length === 0 ) {
		return;
	}

	const observedFrames = new WeakSet();
	let updateScheduled = false;

	function addBodyClasses( element ) {
		if ( ! element ) {
			return;
		}

		element.classList.add( ...bodyClasses );
	}

	function updateEditorCanvas() {
		updateScheduled = false;

		document.querySelectorAll( 'iframe[name="editor-canvas"], iframe.editor-canvas__iframe' ).forEach( ( frame ) => {
			if ( ! observedFrames.has( frame ) ) {
				frame.addEventListener( 'load', () => addBodyClasses( frame.contentDocument && frame.contentDocument.body ) );
				observedFrames.add( frame );
			}

			addBodyClasses( frame.contentDocument && frame.contentDocument.body );
		} );

		document.querySelectorAll( '.editor-styles-wrapper' ).forEach( addBodyClasses );
	}

	function scheduleUpdate() {
		if ( updateScheduled ) {
			return;
		}

		updateScheduled = true;
		window.requestAnimationFrame( updateEditorCanvas );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', scheduleUpdate );
	} else {
		scheduleUpdate();
	}

	new MutationObserver( scheduleUpdate ).observe( document.documentElement, {
		childList: true,
		subtree: true,
	} );
}() );
