<section class="error-page">
  <p class="error-code" aria-hidden="true">404</p>
  <h1>Stránka nenalezena</h1>
  <p class="page-sub">Odkaz je nejspíš zastaralý, nebo článek už neexistuje. Zkuste hledání:</p>
  <?= view('search-form', ['q' => '', 'message' => null]) ?>
  <p><a class="readmore" href="/">← Zpět na úvodní stránku</a></p>
</section>
