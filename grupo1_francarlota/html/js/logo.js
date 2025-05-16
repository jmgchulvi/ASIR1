window.onload = function () {
  // Verificamos si el logo de carga ya se ha mostrado antes
  if (!localStorage.getItem('logoLoaded')) {
    // Si no se ha mostrado, lo mostramos y luego lo ocultamos con fade out
    setTimeout(function () {
      const loading = document.getElementById('loading');

      // Aplica la transición de opacidad para hacer el fade out
      loading.style.transition = 'opacity 1.2s ease';
      loading.style.opacity = '0';  // Comienza el fade out

      // Después de la transición (700ms), ocultamos el logo y mostramos el contenido
      setTimeout(function () {
        loading.style.display = 'none';
        document.getElementById('content').style.display = 'block';

        // Guardamos que el logo ya se mostró en el localStorage
        localStorage.setItem('logoLoaded', 'true');
      }, 1200); // 1200ms coincide con la duración de la transición
    }, 2500); // 2500ms de espera antes de iniciar el fade out
  } else {
    // Si ya se mostró antes, ocultamos directamente el loader y mostramos el contenido
    const loading = document.getElementById('loading');
    loading.style.display = 'none';
    document.getElementById('content').style.display = 'block';
  }
};