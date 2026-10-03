/* Chargé en <head>, sans defer : marque le document AVANT le premier rendu,
   pour que les éléments à révéler partent cachés au lieu de clignoter.
   « Réduire les animations » : rien n'est caché. Si pages.js ne démarre
   pas (réseau coupé, erreur), tout réapparaît au bout de 3 secondes. */
(function () {
  var root = document.documentElement;

  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  root.classList.add('js');
  window.setTimeout(function () {
    if (!window.__pagesReady) {
      root.classList.remove('js');
    }
  }, 3000);
}());
