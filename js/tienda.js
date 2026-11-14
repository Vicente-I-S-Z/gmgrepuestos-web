document.addEventListener('DOMContentLoaded', () => {

    // =========================================================
    // Carrusel del banner principal (hero)
    // =========================================================
    (function heroCarousel() {
        const heroCarousel = document.getElementById('heroCarousel');
        if (!heroCarousel) return;

        const slides = heroCarousel.querySelectorAll('.hero-slide');
        const dots = heroCarousel.querySelectorAll('.hero-dot');
        if (slides.length <= 1) return;

        let indiceActual = 0;
        let intervalo = null;

        function mostrarSlide(indice) {
            slides.forEach(s => s.classList.remove('is-active'));
            dots.forEach(d => d.classList.remove('is-active'));
            slides[indice].classList.add('is-active');
            if (dots[indice]) dots[indice].classList.add('is-active');
            indiceActual = indice;
        }

        function siguienteSlide() {
            mostrarSlide((indiceActual + 1) % slides.length);
        }

        function iniciarAutoplay() {
            intervalo = setInterval(siguienteSlide, 5500);
        }

        function detenerAutoplay() {
            clearInterval(intervalo);
        }

        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                mostrarSlide(parseInt(dot.dataset.heroIndex, 10));
                detenerAutoplay();
                iniciarAutoplay();
            });
        });

        heroCarousel.addEventListener('mouseenter', detenerAutoplay);
        heroCarousel.addEventListener('mouseleave', iniciarAutoplay);

        iniciarAutoplay();
    })();

    // =========================================================
    // Autocompletado de búsqueda
    // =========================================================
    (function buscadorAutocompletado() {
        const input = document.getElementById('searchInput');
        const contenedor = document.getElementById('searchSugerencias');
        if (!input || !contenedor) return;

        let temporizador = null;
        let indiceResaltado = -1;

        input.addEventListener('input', () => {
            const q = input.value.trim();
            clearTimeout(temporizador);

            if (q.length < 2) {
                ocultarSugerencias();
                return;
            }

            temporizador = setTimeout(() => buscarSugerencias(q), 250);
        });

        input.addEventListener('keydown', (e) => {
            const items = contenedor.querySelectorAll('.search-sugerencia-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                indiceResaltado = Math.min(indiceResaltado + 1, items.length - 1);
                resaltarItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                indiceResaltado = Math.max(indiceResaltado - 1, 0);
                resaltarItem(items);
            } else if (e.key === 'Enter' && indiceResaltado >= 0) {
                e.preventDefault();
                items[indiceResaltado].click();
            } else if (e.key === 'Escape') {
                ocultarSugerencias();
            }
        });

        document.addEventListener('click', (e) => {
            if (!contenedor.contains(e.target) && e.target !== input) {
                ocultarSugerencias();
            }
        });

        // Estados de la búsqueda (E9-H7): cargando, sin resultados y error.
        let numeroBusqueda = 0;

        function mostrarEstado(texto, esError) {
            indiceResaltado = -1;
            const aviso = document.createElement('div');
            aviso.className = 'search-sugerencias-estado' + (esError ? ' error' : '');
            aviso.setAttribute('role', 'status');
            aviso.textContent = texto;
            contenedor.innerHTML = '';
            contenedor.appendChild(aviso);
            contenedor.classList.add('open');
        }

        async function buscarSugerencias(q) {
            const miBusqueda = ++numeroBusqueda;
            mostrarEstado('Buscando...');
            try {
                const resp = await fetch(`backend/buscar_sugerencias.php?q=${encodeURIComponent(q)}`);
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                if (miBusqueda !== numeroBusqueda) return; // llegó una búsqueda más nueva
                renderSugerencias(data.sugerencias || []);
            } catch (err) {
                if (miBusqueda !== numeroBusqueda) return;
                mostrarEstado('No pudimos buscar en este momento. Inténtalo de nuevo.', true);
            }
        }

        function renderSugerencias(sugerencias) {
            indiceResaltado = -1;
            if (sugerencias.length === 0) {
                mostrarEstado('No encontramos sugerencias. Presiona "Buscar" para ver todos los resultados.');
                return;
            }

            let html = '';
            sugerencias.forEach(s => {
                html += `
                    <a href="index.php?q=${encodeURIComponent(s.sku)}" class="search-sugerencia-item">
                        <img src="${s.imagen}" alt="" onerror="this.onerror=null;this.src='img/placeholder-repuesto.svg';">
                        <div class="search-sugerencia-info">
                            <div class="search-sugerencia-nombre">${s.nombre}</div>
                            <div class="search-sugerencia-sku">SKU: ${s.sku} · ${s.modelo}</div>
                        </div>
                        <div class="search-sugerencia-precio">$${s.precio.toLocaleString('es-CL')}</div>
                    </a>
                `;
            });
            contenedor.innerHTML = html;
            contenedor.classList.add('open');
        }

        function resaltarItem(items) {
            items.forEach(i => i.classList.remove('is-resaltado'));
            if (items[indiceResaltado]) {
                items[indiceResaltado].classList.add('is-resaltado');
                items[indiceResaltado].scrollIntoView({ block: 'nearest' });
            }
        }

        function ocultarSugerencias() {
            numeroBusqueda++; // descarta cualquier búsqueda en curso
            contenedor.classList.remove('open');
            contenedor.innerHTML = '';
            indiceResaltado = -1;
        }
    })();

    // =========================================================
    // Mini carrusel de imágenes en cada tarjeta de producto
    // (solo aparece si el producto tiene más de una foto cargada)
    // =========================================================
    (function carruselesProducto() {
        document.querySelectorAll('.producto-img-wrap').forEach(wrap => {
            const imagenes = wrap.querySelectorAll('.producto-img');
            const dots = wrap.querySelectorAll('.producto-img-dot');
            const btnPrev = wrap.querySelector('.producto-img-prev');
            const btnNext = wrap.querySelector('.producto-img-next');
            if (imagenes.length <= 1) return;

            let indice = 0;

            function mostrar(nuevoIndice) {
                indice = (nuevoIndice + imagenes.length) % imagenes.length;
                imagenes.forEach(img => img.classList.remove('producto-img-activa'));
                dots.forEach(d => d.classList.remove('is-active'));
                imagenes[indice].classList.add('producto-img-activa');
                if (dots[indice]) dots[indice].classList.add('is-active');
            }

            if (btnPrev) {
                btnPrev.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    mostrar(indice - 1);
                });
            }
            if (btnNext) {
                btnNext.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    mostrar(indice + 1);
                });
            }
            dots.forEach((dot, i) => {
                dot.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    mostrar(i);
                });
            });
        });
    })();

});
