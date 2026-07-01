</div>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= base_path() ?>/sw.js', { scope: '<?= base_path() ?>/' });
}
</script>
</body>
</html>
