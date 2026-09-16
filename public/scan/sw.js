/*
 * Service worker du scanner.
 *
 * Son seul role : garantir que l'application demarre meme sans reseau. Le vigile
 * doit pouvoir ouvrir le scanner dans un sous-sol sans barres.
 *
 * Ce qu'il ne fait PAS : mettre en cache les reponses de l'API. Une reponse
 * "entree autorisee" servie depuis un cache serait un trou de securite beant.
 * Toutes les requetes /api/ passent directement au reseau.
 */

var CACHE = 'scannem-scan-v1';

var COQUILLE = [
  '/scan/',
  '/scan/index.html',
  '/scan/style.css',
  '/scan/app.js',
  '/scan/vendor/jsQR.js',
  '/scan/manifest.json'
];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE)
      .then(function (c) { return c.addAll(COQUILLE); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (cles) {
      return Promise.all(cles.map(function (k) {
        return k === CACHE ? null : caches.delete(k);
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var url = new URL(e.request.url);

  // L'API n'est jamais mise en cache, ni servie depuis le cache.
  if (url.pathname.indexOf('/api/') === 0) {
    return;
  }

  if (e.request.method !== 'GET' || url.origin !== location.origin) {
    return;
  }

  // Reseau d'abord pour recuperer les mises a jour, cache en secours.
  e.respondWith(
    fetch(e.request).then(function (reponse) {
      if (reponse && reponse.status === 200 && reponse.type === 'basic') {
        var copie = reponse.clone();
        caches.open(CACHE).then(function (c) { c.put(e.request, copie); });
      }
      return reponse;
    }).catch(function () {
      return caches.match(e.request).then(function (mise) {
        return mise || caches.match('/scan/index.html');
      });
    })
  );
});
