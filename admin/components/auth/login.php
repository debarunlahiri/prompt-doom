<div id="login" class="login-page">
  <section class="login-visual">
    <div class="brand-icon"><i data-lucide="sparkles"></i></div>
    <div>
      <span class="eyebrow">PROMPT DOOM</span>
      <h1>Control the prompt universe.</h1>
      <p>Manage creative assets, moderate the community, and understand what inspires your audience.</p>
    </div>
    <div class="secure-note"><i data-lucide="shield-check"></i> Role-protected administrator access</div>
  </section>
  <section class="login-panel">
    <form id="login-form" class="login-card">
      <span class="eyebrow">ADMIN CONSOLE</span>
      <h2>Welcome back</h2>
      <p>Sign in with your administrator credentials.</p>
      <?php if (($_GET["setup"] ?? "") === "complete"): ?>
        <div class="success-message">Administrator created successfully. You can sign in now.</div>
      <?php endif; ?>
      <div id="login-error" class="alert hidden"></div>
      <label>Email address<input name="email" type="email" placeholder="Enter your email address" required autocomplete="email"></label>
      <label>Password<input name="password" type="password" placeholder="Enter your password" required autocomplete="current-password"></label>
      <button class="primary wide" type="submit"><i data-lucide="lock-keyhole"></i><span>Sign in securely</span></button>
      <div class="setup-action">
        <span>Setting up a new installation?</span>
        <a class="secondary-link" href="setup.php"><i data-lucide="user-plus"></i><span>Create the first administrator</span></a>
      </div>
    </form>
  </section>
</div>
