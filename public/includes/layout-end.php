<?php

declare(strict_types=1);

use App\Helpers\View;
?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <p>Files are processed securely and removed after download. Max upload <?= View::e((string) round((int) ($config['max_upload_bytes'] ?? 0) / 1048576, 1)) ?> MB.</p>
        </div>
    </footer>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
