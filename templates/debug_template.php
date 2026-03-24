<?php include "templates/header.php"; ?>

<main class="main detail-page">
    <section class="hero-panel compact">
        <div class="hero-copy">
            <p class="eyebrow">Debug</p>
            <h1>Request timing profile</h1>
            <p class="hero-description">Use this page to inspect the queries executed for the current request.</p>
        </div>
    </section>

    <?php render_debug_panel(false); ?>
</main>

<?php include "templates/footer.php"; ?>
