( function () {
	'use strict';

	const bodyClasses = window.ASLC && Array.isArray( window.ASLC.editorBodyClasses )
		? window.ASLC.editorBodyClasses
		: [];

	const observedFrames = new WeakSet();
	const observedDocuments = new WeakSet();
	let updateScheduled = false;

	function addBodyClasses( element ) {
		if ( ! element || bodyClasses.length === 0 ) {
			return;
		}

		element.classList.add( ...bodyClasses );
	}

	function promoteLocalStyles( editorDocument ) {
		if ( ! editorDocument || ! editorDocument.head ) {
			return;
		}

		const localStyles = Array.from( editorDocument.head.querySelectorAll(
			'link[id^="aslc-editor-css-"], style[id^="aslc-editor-css-"]'
		) );
		if ( localStyles.length === 0 ) {
			return;
		}

		const headTail = Array.from( editorDocument.head.children ).slice( -localStyles.length );
		if ( localStyles.every( ( styleNode, index ) => styleNode === headTail[ index ] ) ) {
			return;
		}

		localStyles.forEach( ( styleNode ) => editorDocument.head.appendChild( styleNode ) );
	}

	function observeEditorDocument( editorDocument ) {
		if ( ! editorDocument || ! editorDocument.head || observedDocuments.has( editorDocument ) ) {
			return;
		}

		promoteLocalStyles( editorDocument );
		new MutationObserver( () => window.requestAnimationFrame( () => promoteLocalStyles( editorDocument ) ) ).observe(
			editorDocument.head,
			{ childList: true }
		);
		observedDocuments.add( editorDocument );
	}

	function updateFrame( frame ) {
		const editorDocument = frame.contentDocument;
		addBodyClasses( editorDocument && editorDocument.body );
		observeEditorDocument( editorDocument );
	}

	function updateEditorCanvas() {
		updateScheduled = false;

		document.querySelectorAll( 'iframe[name="editor-canvas"], iframe.editor-canvas__iframe' ).forEach( ( frame ) => {
			if ( ! observedFrames.has( frame ) ) {
				frame.addEventListener( 'load', () => updateFrame( frame ) );
				observedFrames.add( frame );
			}

			updateFrame( frame );
		} );

		const fallbackWrappers = document.querySelectorAll( '.editor-styles-wrapper' );
		fallbackWrappers.forEach( addBodyClasses );
		if ( fallbackWrappers.length > 0 ) {
			observeEditorDocument( document );
		}
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
