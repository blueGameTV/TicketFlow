        </main>
    </div>
</div>
<script>window.TICKETFLOW_CSRF=<?= json_encode($csrf->token()) ?>;</script>
<script src="assets/js/ui.js?v=1.2.0" defer></script>
<script src="assets/js/live-global.js?v=1.2.0" defer></script>
<script src="assets/js/global-search.js?v=1.2.0" defer></script>
<script src="assets/js/custom-selects.js?v=1.2.0" defer></script>
<script src="assets/js/theme-preferences.js?v=1.2.0" defer></script>
<script src="assets/js/live-system-state.js?v=1.2.0" defer></script>
<script src="assets/js/datetime-picker.js?v=1.2.0" defer></script>
<script src="assets/js/i18n-runtime.js?v=1.2.0" defer></script>

<?php if (!empty($user['must_change_password'])): ?>
<div class="tf-forced-password-layer" data-forced-password-layer role="presentation">
    <div class="tf-forced-password-backdrop" aria-hidden="true"></div>
    <section class="tf-forced-password-modal" role="dialog" aria-modal="true" aria-labelledby="tf-forced-password-title" aria-describedby="tf-forced-password-description">
        <div class="tf-forced-password-icon"><i class="fa-solid fa-key"></i></div>
        <div class="tf-forced-password-heading">
            <span class="tf-forced-password-kicker">Sécurité du compte</span>
            <h2 id="tf-forced-password-title">Nouveau mot de passe requis</h2>
            <p id="tf-forced-password-description">Un Administrateur a demandé le renouvellement de votre mot de passe. Vous devez le modifier avant de pouvoir continuer dans TicketFlow.</p>
        </div>

        <div class="tf-forced-password-notice"><i class="fa-solid fa-shield-halved"></i><span>Votre ancien mot de passe n’est pas demandé pour ce changement imposé.</span></div>

        <form class="tf-forced-password-form" data-forced-password-form autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field">
                <label for="forced-new-password">Nouveau mot de passe</label>
                <div class="tf-password-input">
                    <input id="forced-new-password" name="new_password" type="password" minlength="12" required autocomplete="new-password" placeholder="12 caractères minimum">
                    <button type="button" class="tf-password-eye" data-force-password-toggle="forced-new-password" aria-label="Afficher le mot de passe"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
            <div class="field">
                <label for="forced-confirm-password">Confirmation</label>
                <div class="tf-password-input">
                    <input id="forced-confirm-password" name="confirm_password" type="password" minlength="12" required autocomplete="new-password" placeholder="Confirmez le nouveau mot de passe">
                    <button type="button" class="tf-password-eye" data-force-password-toggle="forced-confirm-password" aria-label="Afficher la confirmation"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>

            <div class="tf-password-rules" aria-label="Critères du mot de passe">
                <span data-password-rule="length"><i class="fa-regular fa-circle"></i> 12 caractères minimum</span>
                <span data-password-rule="upper"><i class="fa-regular fa-circle"></i> Une majuscule</span>
                <span data-password-rule="lower"><i class="fa-regular fa-circle"></i> Une minuscule</span>
                <span data-password-rule="digit"><i class="fa-regular fa-circle"></i> Un chiffre</span>
                <span data-password-rule="match"><i class="fa-regular fa-circle"></i> Les mots de passe correspondent</span>
            </div>

            <div class="tf-forced-password-feedback" data-forced-password-feedback hidden></div>
            <button class="btn primary full tf-forced-password-submit" type="submit"><i class="fa-solid fa-lock"></i> Enregistrer et accéder à TicketFlow</button>
        </form>

        <form method="post" action="logout.php" class="tf-forced-password-logout">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Se déconnecter</button>
        </form>
    </section>
</div>
<script>
document.querySelectorAll('[data-force-password-toggle]').forEach(function(button){
    button.addEventListener('click', function(){
        var input = document.getElementById(button.getAttribute('data-force-password-toggle'));
        if (!input) return;
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        var icon = button.querySelector('i');
        if (icon) icon.className = visible ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
    });
});
</script>
<script src="assets/js/forced-password.js?v=1.2.0" defer></script>
<?php endif; ?>

</body>
</html>
