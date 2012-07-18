jQuery(document).ready(function() {
	var cmCSS = CodeMirror.fromTextArea(document.getElementById("custom_css"), { mode : 'css', lineNumbers : true });
	var cmJavaScript = CodeMirror.fromTextArea(document.getElementById("custom_js"), { mode : 'javascript', lineNumbers : true });
});
