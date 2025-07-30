/**
 * Simple Gallery Carousel for elements under .media-gallery
 * Requires basic CSS for .gallery-carousel, .gallery-slide, .gallery-nav
 */

document.addEventListener('DOMContentLoaded', function () {
    const gallery = document.querySelector('.media-gallery');
    if (!gallery) return;

    // Collect images
    const images = Array.from(gallery.querySelectorAll('img'));
    if (images.length === 0) return;

    // Create carousel container
    const carousel = document.createElement('div');
    carousel.className = 'gallery-carousel';

    // Create slides
    images.forEach((img, idx) => {
        const slide = document.createElement('div');
        slide.className = 'gallery-slide';
        if (idx !== 0) slide.style.display = 'none';
        slide.appendChild(img.cloneNode(true));
        carousel.appendChild(slide);
    });

    // Create navigation
    const prevBtn = document.createElement('button');
    prevBtn.className = 'gallery-nav gallery-prev';
    prevBtn.textContent = '‹';

    const nextBtn = document.createElement('button');
    nextBtn.className = 'gallery-nav gallery-next';
    nextBtn.textContent = '›';

    carousel.appendChild(prevBtn);
    carousel.appendChild(nextBtn);

    // Replace gallery content
    gallery.innerHTML = '';
    gallery.appendChild(carousel);

    let current = 0;
    const slides = carousel.querySelectorAll('.gallery-slide');
    let visibleSlides = window.innerWidth < 768 ? 1 : (window.innerWidth < 992 ? 2 : 3);
    window.addEventListener('resize', () => {
        const newVisibleSlides = window.innerWidth < 768 ? 1 : (window.innerWidth < 992 ? 2 : 3);
        if (newVisibleSlides !== visibleSlides) {
            visibleSlides = newVisibleSlides;
            showSlides(current);
        }
    });

    function showSlides(startIdx) {
        slides.forEach((slide, i) => {
            // Show slides in the window [startIdx, startIdx + visibleSlides)
            if (
                (i >= startIdx && i < startIdx + visibleSlides) ||
                // Wrap around for slides at the end
                (startIdx + visibleSlides > slides.length && i < (startIdx + visibleSlides) % slides.length)
            ) {
                slide.style.display = 'block';
            } else {
                slide.style.display = 'none';
            }
        });
    }

    prevBtn.addEventListener('click', () => {
        current = (current - 1 + slides.length) % slides.length;
        showSlides(current);
    });

    nextBtn.addEventListener('click', () => {
        current = (current + 1) % slides.length;
        showSlides(current);
    });

    showSlides(current);
});