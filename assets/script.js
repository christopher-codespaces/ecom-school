function addToCart(productId) {
    let formData = new FormData();
    formData.append('product_id', productId);

    fetch('add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update Cart Count Badges across all navigation instances
            const cartBadges = document.querySelectorAll('#cart-count');
            cartBadges.forEach(badge => {
                badge.innerText = data.total_items;
            });

            // Show dynamic non-blocking toast
            const toast = document.getElementById('toast');
            if (toast) {
                toast.className = 'toast show';
                setTimeout(() => {
                    toast.className = toast.className.replace('show', '');
                }, 2500);
            }
        }
    })
    .catch(err => console.error('Error adding to cart:', err));
}