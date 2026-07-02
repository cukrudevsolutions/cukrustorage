        </div>
    </div>
</div>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('../sw.js');
}
</script>
<?php if (!empty($autoRefresh)): ?>
<script>
(function () {
    var REFRESH_MS = 45000;
    function scheduleRefresh() {
        setTimeout(function () {
            var active = document.activeElement;
            var isTyping = active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
            if (isTyping || document.hidden) {
                scheduleRefresh();
                return;
            }
            location.reload();
        }, REFRESH_MS);
    }
    scheduleRefresh();
})();
</script>
<?php endif; ?>
</body>
</html>
