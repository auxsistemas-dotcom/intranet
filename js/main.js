// ============================================
// ARMOTOR DIGITAL - PLATAFORMA CORPORATIVA
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ INTRANET iniciado');
    initSlider();
    initAppCards();
    initDocCards();
    initSearch();
});

// ========== SLIDER ==========
function initSlider() {
    const slides = document.querySelectorAll('.slide');
    const prevBtn = document.getElementById('slider-prev');
    const nextBtn = document.getElementById('slider-next');
    const dotsContainer = document.getElementById('slider-dots');
    let currentSlide = 0;
    
    if (!slides.length) return;
    
    dotsContainer.innerHTML = '';
    slides.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.classList.add('dot');
        if (i === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(i));
        dotsContainer.appendChild(dot);
    });
    
    function updateSlider() {
        const wrapper = document.getElementById('slider-wrapper');
        wrapper.style.transform = `translateX(-${currentSlide * 100}%)`;
        
        document.querySelectorAll('.dot').forEach((dot, i) => {
            dot.classList.toggle('active', i === currentSlide);
        });
    }
    
    function goToSlide(index) {
        currentSlide = index;
        updateSlider();
    }
    
    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        updateSlider();
    }
    
    function prevSlide() {
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
        updateSlider();
    }
    
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);
    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    
    setInterval(nextSlide, 5000);
}

// ========== APLICACIONES ==========
function initAppCards() {
    const cards = document.querySelectorAll('.app-card');
    cards.forEach(card => {
        card.addEventListener('click', () => {
            const url = card.dataset.url;
            const name = card.dataset.name;
            const requiereLogin = card.dataset.requiereLogin === 'true';
            
            if (url) {
                if (requiereLogin) {
                    // Verificar si ya hay sesión activa
                    verificarSesionYAbrir(url, name);
                } else {
                    if (url.startsWith('http://') || url.startsWith('https://')) {
                        window.open(url, '_blank');
                    } else {
                        window.location.href = url;
                    }
                    showToast(`Abriendo ${name}...`);
                }
            }
        });
    });
}

// ========== VERIFICAR SESIÓN ANTES DE ABRIR ==========
function verificarSesionYAbrir(url, name) {
    fetch('includes/check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.logueado) {
                window.open(url, '_blank');
                showToast(`Abriendo ${name}...`);
            } else {
                // Guardar la URL a la que quería acceder
                sessionStorage.setItem('url_redirect', url);
                showToast(`🔐 Inicia sesión para acceder a ${name}`);
                window.location.href = 'login.php';
            }
        })
        .catch(() => {
            // Si hay error, asumimos que no está logueado
            sessionStorage.setItem('url_redirect', url);
            window.location.href = 'login.php';
        });
}

// ========== MODAL ==========
function showConfirmModal(url, name) {
    const modal = document.createElement('div');
    modal.className = 'modal-confirm';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-external-link-alt"></i>
            </div>
            <h3>Abrir ${name}</h3>
            <p>Serás redirigido a la plataforma ${name}</p>
            <div class="modal-buttons">
                <button class="modal-btn confirm">Continuar</button>
                <button class="modal-btn cancel">Cancelar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    modal.querySelector('.confirm').addEventListener('click', () => {
        window.open(url, '_blank');
        modal.remove();
        showToast(`Abriendo ${name}...`);
    });
    
    modal.querySelector('.cancel').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

// ========== DOCUMENTACIÓN ==========
function initDocCards() {
    const cards = document.querySelectorAll('.doc-card');
    cards.forEach(card => {
        card.addEventListener('click', () => {
            const nombre = card.dataset.nombre || card.querySelector('h3').textContent;
            const url = card.dataset.url;
            const requiereLogin = card.dataset.requiereLogin === 'true';
            
            if (requiereLogin) {
                // Verificar sesión antes de abrir
                verificarSesionParaDocumento(url, nombre);
            } else if (url) {
                // Documento público
                showToast(`📄 Abriendo documentación de ${nombre}`);
                window.location.href = url;
            }
        });
    });
}

// ========== VERIFICAR SESIÓN PARA DOCUMENTO ==========
function verificarSesionParaDocumento(url, nombre) {
    const destino = url || 'documentacion/index.php';
    fetch('includes/check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.logueado) {
                // Si ya está logueado, va directo a la URL de documentación seleccionada
                window.location.href = destino;
            } else {
                // No logueado - redirigir al login con la URL de destino codificada
                showToast(`🔐 Inicia sesión para acceder a ${nombre}`);
                window.location.href = 'login.php?redirect=' + encodeURIComponent(destino);
            }
        })
        .catch(() => {
            window.location.href = 'login.php?redirect=' + encodeURIComponent(destino);
        });
}

// ========== BUSCADOR ==========
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.app-card');
        
        cards.forEach(card => {
            const name = card.querySelector('.app-name').textContent.toLowerCase();
            const desc = card.querySelector('.app-description').textContent.toLowerCase();
            
            if (name.includes(term) || desc.includes(term)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

// ========== TOAST ==========
function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}