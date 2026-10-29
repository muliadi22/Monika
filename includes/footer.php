<?php
// includes/footer.php
?>
   <style>
/* Efek cahaya menyapu untuk nama pembuat */
@keyframes shimmerText {
    0% { background-position: -200% center; }
    100% { background-position: 200% center; }
}
.shimmer-muliadi {
    font-size: 0.8rem;
    font-weight: 700;
    /* Kombinasi warna redup ke terang (warna tema) lalu ke redup lagi */
    background: linear-gradient(90deg, var(--muted) 0%, #6366f1 40%, #06b6d4 50%, #6366f1 60%, var(--muted) 100%);
    background-size: 200% auto;
    color: transparent;
    -webkit-background-clip: text;
    background-clip: text;
    animation: shimmerText 3.5s linear infinite;
}
</style>

<div class="text-center py-4" style="color: var(--muted); font-size: 0.9rem; line-height: 1.6;">
    &copy; 2026 <strong>MONIKA e-ICV</strong><br>
    Balai Kekarantinaan Kesehatan Kelas I Banjarmasin<br>
    <span class="shimmer-muliadi">Created by Muliadi</span>
</div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>