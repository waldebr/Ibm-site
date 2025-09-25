        const partnersContainer = document.getElementById('partnersContainer');
        const dots = document.querySelectorAll('.partners-dots .dot');
        let currentSlide = 0;
    
        function goToSlide(slideIndex) {
            partnersContainer.style.transform = `translateX(-${slideIndex * 50}%)`;
            dots.forEach(dot => dot.classList.remove('active'));
            dots[slideIndex].classList.add('active');
            currentSlide = slideIndex;
        }
    
        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const slideIndex = parseInt(dot.getAttribute('data-slide'));
                goToSlide(slideIndex);
            });
        });
    
        function autoSlide() {
            currentSlide = (currentSlide + 1) % dots.length;
            goToSlide(currentSlide);
        }
    
        setInterval(autoSlide, 5000); // Muda de slide a cada 5 segundos
    

