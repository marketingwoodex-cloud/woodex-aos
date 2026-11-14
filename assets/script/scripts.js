document.addEventListener('DOMContentLoaded', function () {
    const track = document.querySelector('.carousel-track');
    const cards = Array.from(track.children);
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    const cardWidth = cards[0].offsetWidth + 20; // Card width + margin (10px on each side)
    const visibleCards = 4;
    let currentIndex = 0;

    // Function to move to the next set of cards
    function moveToNextSlide() {
        currentIndex += visibleCards;
        if (currentIndex >= cards.length) {
            currentIndex = 0; // Wrap back to the start
        }
        const amountToMove = -currentIndex * cardWidth;
        track.style.transform = `translateX(${amountToMove}px)`;
    }

    // Function to move to the previous set of cards
    function moveToPrevSlide() {
        currentIndex -= visibleCards;
        if (currentIndex < 0) {
            currentIndex = Math.max(0, cards.length - visibleCards); // Wrap to last set if at the beginning
        }
        const amountToMove = -currentIndex * cardWidth;
        track.style.transform = `translateX(${amountToMove}px)`;
    }

    // Attach click event listeners to buttons
    nextButton.addEventListener('click', moveToNextSlide);
    prevButton.addEventListener('click', moveToPrevSlide);
});
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const faqItem = question.parentElement;
        faqItem.classList.toggle('active');
        
        // Toggle the icon between + and -
        const icon = question.querySelector('.faq-icon');
        icon.textContent = faqItem.classList.contains('active') ? '-' : '+';
    });
});
document.addEventListener("DOMContentLoaded", () => {
    const applyCouponBtn = document.querySelector(".apply-btn");
    const quantityInputs = document.querySelectorAll(".quantity-input");
    const subtotalDisplay = document.querySelector(".total-amount");
    const discountDisplay = document.querySelector(".discount-amount");
    const grandTotalDisplay = document.querySelector(".grand-total");

    let subtotal = 50.00;
    let discount = 0.00;

    // Function to update totals
    function updateTotals() {
        subtotalDisplay.textContent = `₨ ${subtotal.toFixed(2)}`;
        discountDisplay.textContent = `₨ ${discount.toFixed(2)}`;
        grandTotalDisplay.textContent = `₨ ${(subtotal - discount).toFixed(2)}`;
    }

    // Apply coupon
    applyCouponBtn.addEventListener("click", () => {
        const couponCode = document.getElementById("coupon-code").value.trim();
        if (couponCode === "DISCOUNT10") {
            discount = subtotal * 0.10;
            alert("Coupon Applied: 10% off!");
        } else {
            discount = 0.00;
            alert("Invalid Coupon Code");
        }
        updateTotals();
    });

    // Quantity change
    quantityInputs.forEach(input => {
        input.addEventListener("change", (e) => {
            const quantity = parseInt(e.target.value) || 1;
            e.target.value = quantity < 1 ? 1 : quantity;
            subtotal = 50.00 * quantity;
            updateTotals();
        });
    });

    // Initial totals update
    updateTotals();
});


 // JavaScript for Carousel
const track = document.querySelector('.carousel-track-v1');
const prevButton = document.getElementById('prevButton');
const nextButton = document.getElementById('nextButton');

let currentIndex = 0;
const itemsToShow = 4; // Number of items to show at once
const cardWidth = track.children[0].getBoundingClientRect().width;
const trackWidth = cardWidth * itemsToShow;

function updateCarousel() {
    const offset = currentIndex * trackWidth;
    track.style.transform = `translateX(-${offset}px)`;
}

nextButton.addEventListener('click', () => {
    const totalItems = track.children.length;
    const totalSlides = Math.ceil(totalItems / itemsToShow);

    if (currentIndex < totalSlides - 1) {
        currentIndex++;
    } else {
        currentIndex = 0; // Loop back to the start
    }
    updateCarousel();
});

prevButton.addEventListener('click', () => {
    const totalItems = track.children.length;
    const totalSlides = Math.ceil(totalItems / itemsToShow);

    if (currentIndex > 0) {
        currentIndex--;
    } else {
        currentIndex = totalSlides - 1; // Loop back to the last slide
    }
    updateCarousel();
});
   
// Cart
    

    


