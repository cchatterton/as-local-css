(function () {
	'use strict';

	function formatCount(count) {
		return count.toLocaleString() + (count === 1 ? ' character' : ' characters');
	}

	function updateCount(textarea, value) {
		var output = document.querySelector('[data-count-for="' + textarea.id + '"]');
		if (output) output.textContent = formatCount(value.length);
	}

	function editorFor(textarea) {
		var sibling = textarea.previousSibling;
		while (sibling) {
			if (sibling.CodeMirror) return sibling.CodeMirror;
			sibling = sibling.previousSibling;
		}
		sibling = textarea.nextSibling;
		while (sibling) {
			if (sibling.CodeMirror) return sibling.CodeMirror;
			sibling = sibling.nextSibling;
		}
		return null;
	}

	function connectCount(textarea, editor) {
		if (textarea.dataset.aslcCountConnected) return;
		textarea.dataset.aslcCountConnected = '1';
		updateCount(textarea, editor ? editor.getValue() : textarea.value);
		if (editor) {
			editor.on('change', function (instance) {
				updateCount(textarea, instance.getValue());
			});
		} else {
			textarea.addEventListener('input', function () { updateCount(textarea, textarea.value); });
		}
	}

	function createEditor(textarea) {
		if (textarea.dataset.aslcEditorReady) return editorFor(textarea);
		var settings = Object.assign({}, window.CCJ && CCJ.codemirror ? CCJ.codemirror : {});
		settings.mode = 'text/css';
		settings.lineNumbers = true;
		settings.lineWrapping = true;
		settings.foldGutter = true;
		settings.gutters = ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'];
		settings.extraKeys = Object.assign({}, settings.extraKeys || {});
		delete settings.extraKeys.F11;
		delete settings.extraKeys.Esc;
		settings.extraKeys['Ctrl-S'] = settings.extraKeys['Cmd-S'] = function (cm) {
			cm.save();
			document.getElementById('post').requestSubmit();
		};

		var editor = CodeMirror.fromTextArea(textarea, settings);
		textarea.dataset.aslcEditorReady = '1';
		editor.setSize('100%', 360);
		connectCount(textarea, editor);
		window.setTimeout(function () { editor.refresh(); }, 0);
		return editor;
	}

	function openSection(details) {
		var textarea = details.querySelector('.aslc-breakpoint-textarea');
		if (textarea) createEditor(textarea);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var container = document.querySelector('.aslc-css-sections');
		if (!container || typeof CodeMirror === 'undefined') return;

		var base = document.getElementById('ccj_content');
		if (base) {
			window.setTimeout(function () { connectCount(base, editorFor(base)); }, 0);
		}

		container.querySelectorAll('.aslc-breakpoint-textarea').forEach(function (textarea) {
			updateCount(textarea, textarea.value);
		});

		container.querySelectorAll('details.aslc-css-section').forEach(function (details) {
			details.addEventListener('toggle', function () {
				if (details.open) openSection(details);
			});
			if (details.open) openSection(details);
		});

		if (window.location.hash) {
			var target = document.querySelector(window.location.hash);
			if (target && target.matches('details.aslc-css-section')) {
				target.open = true;
				target.scrollIntoView({ block: 'start' });
			}
		}
	});
}());
