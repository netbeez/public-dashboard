<?php
?>
<footer id="footer" class="site-footer">
    <div class="footer-wrap">
        <span class="footer-statement">Powered by NetBeez<sup>&reg;</sup></span>
        <span class="footer-statement">Public dashboard experience refreshed for clearer operational review.</span>
        <span class="footer-statement">&copy; <?php echo date('Y'); ?></span>
    </div>
</footer>

<?php show_selected_time_window_interval(); ?>
<?php get_graphs($view); ?>

</body>
</html>
