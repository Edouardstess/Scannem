/* Site Immersif — hands the page content to the engine.
   The server renders the whole page from the database; app.js still reads
   two things from window.SITE_CONTENT (the motto hints and the number of
   steps). They travel in a JSON data block, because the site's
   Content-Security-Policy refuses inline scripts. Load BEFORE app.js. */
(function () {
  'use strict';
  var node = document.getElementById('site-content');
  try {
    window.SITE_CONTENT = node ? JSON.parse(node.textContent) : {};
  } catch (e) {
    window.SITE_CONTENT = {};
  }
}());
