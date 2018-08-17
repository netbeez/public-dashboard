<?php
/**
 * The footer template
 */
?>

<footer id="footer" class="site-footer">
    <span class="footer-statement">Powered by NetBeez<sup>&reg;</sup></span>
    <span class="footer-statement">&copy; <?php echo date('Y'); ?> </span>
</footer>

<?php show_selected_time_window_interval(); ?>
<?php get_graphs($view); ?>

</body>
</html>
