/*
 * One delegated click handler. Nothing is added to the global scope: the plugin
 * this was written against shipped an inline script defining a function called
 * print(), which shadows window.print and breaks printing everywhere else on
 * the page.
 */
document.addEventListener('click', function (event) {
	var button = event.target.closest('.folio-toolbar__print');

	if (button) {
		window.print();
	}
});
