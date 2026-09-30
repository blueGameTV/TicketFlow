(() => {
  if ((document.documentElement.lang || '').toLowerCase() !== 'en') return;

  const map = new Map(Object.entries({
    'Sécurité du compte':'Account security',
    'Nouveau mot de passe requis':'New password required',
    'Un Administrateur a demandé le renouvellement de votre mot de passe. Vous devez le modifier avant de pouvoir continuer dans TicketFlow.':'An Administrator required you to renew your password. You must change it before continuing in TicketFlow.',
    'Votre ancien mot de passe n’est pas demandé pour ce changement imposé.':'Your current password is not required for this enforced change.',
    'Nouveau mot de passe':'New password',
    'Confirmation':'Confirmation',
    '12 caractères minimum':'12 characters minimum',
    'Confirmez le nouveau mot de passe':'Confirm the new password',
    'Afficher le mot de passe':'Show password',
    'Masquer le mot de passe':'Hide password',
    'Afficher la confirmation':'Show confirmation',
    'Critères du mot de passe':'Password requirements',
    'Une majuscule':'One uppercase letter',
    'Une minuscule':'One lowercase letter',
    'Un chiffre':'One number',
    'Les mots de passe correspondent':'Passwords match',
    'Enregistrer et accéder à TicketFlow':'Save and access TicketFlow',
    'Se déconnecter':'Sign out',
    'Interne Support IT':'Internal IT Support',
    'Aucun message pour le moment.':'No messages yet.',
    'Aucun résultat':'No results',
    'Essayez un numéro de ticket, un nom ou un groupe.':'Try a ticket number, a name or a group.',
    'Recherche…':'Searching…',
    'Recherche indisponible':'Search unavailable',
    'Réessayez dans quelques instants.':'Try again in a few moments.',
    'Masquer les filtres':'Hide filters',
    'Filtres avancés':'Advanced filters',
    'Maintenance planifiée':'Scheduled maintenance',
    'Choisir des fichiers':'Choose files',
    'Choisir un fichier':'Choose file',
    'Aucun fichier sélectionné':'No file selected',
    'Aucun fichier choisi':'No file selected',
    'Effacer':'Clear',
    'Aujourd’hui':'Today',
    'Appliquer':'Apply',
    'Mois précédent':'Previous month',
    'Mois suivant':'Next month',
    'Sécurité':'Security',
    'Changer mon mot de passe':'Change my password',
    'Mot de passe actuel':'Current password',
    'Modifier le mot de passe':'Change password',
    'Retour':'Back',
    'Pièces jointes':'Attachments',
    'Aucune pièce jointe pour ce ticket.':'No attachments for this ticket.',
    'Ajouter des fichiers':'Add files',
    'Ajouter les pièces jointes':'Upload attachments',
    'Historique':'History',
    'Actions Support IT':'IT Support actions',
    'Proposer la résolution':'Propose resolution',
    'Proposer au demandeur':'Send proposal to requester',
    'Modifier un groupe':'Edit group',
    'Informations du groupe':'Group information',
    'Modifier un utilisateur':'Edit user',
    'Alertes de service globales':'Global service alerts',
    'Créer une alerte':'Create an alert',
    'Alertes actives':'Active alerts',
    'Aucune alerte active.':'No active alerts.',
    'Centre de notifications':'Notification center',
    'Toutes':'All',
    'Non lues':'Unread',
    'Aucune notification dans cette vue.':'No notifications in this view.'
  }));

  const months = {
    janvier:'January', février:'February', mars:'March', avril:'April', mai:'May', juin:'June',
    juillet:'July', août:'August', septembre:'September', octobre:'October', novembre:'November', décembre:'December'
  };

  const translate = (text) => {
    if (!text || !text.trim()) return text;
    let out = text;
    for (const [fr,en] of map) out = out.split(fr).join(en);
    for (const [fr,en] of Object.entries(months)) out = out.replace(new RegExp(`\\b${fr}\\b`, 'gi'), en);
    out = out
      .replace(/(\d+) sélectionné(?:s)?/g, '$1 selected')
      .replace(/Appliquer « ([^»]+) » à (\d+) ticket(?:s)? \?/g, 'Apply “$1” to $2 ticket(s)?')
      .replace(/(\d+) message(?:s)?/g, '$1 message(s)')
      .replace(/(\d+) fichier(?:s)?/g, '$1 file(s)')
      .replace(/(\d+) alerte(?:s)?/g, '$1 alert(s)')
      .replace(/(\d+) membre(?:s)?/g, '$1 member(s)')
      .replace(/Créé le\s+/g, 'Created on ')
      .replace(/Créée le\s+/g, 'Created on ')
      .replace(/Résolue le\s+/g, 'Resolved on ')
      .replace(/Échéance\s*:\s*/g, 'Due: ')
      .replace(/Statut\s*:\s*/g, 'Status: ');
    return out;
  };

  const skip = (el) => !!el.closest('script,style,code,pre,.chat-message-text-v0136,.description-text,[data-no-translate]');

  const translateNode = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      const parent = node.parentElement;
      if (!parent || skip(parent)) return;
      const next = translate(node.nodeValue || '');
      if (next !== node.nodeValue) node.nodeValue = next;
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return;
    const el = node;
    if (skip(el)) return;
    for (const attr of ['placeholder','aria-label','title']) {
      if (el.hasAttribute(attr)) {
        const current = el.getAttribute(attr) || '';
        const next = translate(current);
        if (next !== current) el.setAttribute(attr, next);
      }
    }
    el.childNodes.forEach(translateNode);
  };

  const localizeFileInputs = () => {
    document.querySelectorAll('input[type="file"]:not([data-tf-file-localized])').forEach(input => {
      input.dataset.tfFileLocalized = '1';
      const wrapper = document.createElement('div');
      wrapper.className = 'tf-localized-file';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);
      input.classList.add('tf-localized-file-native');

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'tf-localized-file-button';
      button.innerHTML = '<i class="fa-solid fa-folder-open"></i><span>Choose file</span>';
      const name = document.createElement('span');
      name.className = 'tf-localized-file-name';
      name.textContent = 'No file selected';
      wrapper.append(button, name);

      button.addEventListener('click', () => input.click());
      input.addEventListener('change', () => {
        const files = Array.from(input.files || []);
        button.querySelector('span').textContent = input.multiple ? 'Choose files' : 'Choose file';
        name.textContent = files.length === 0 ? 'No file selected' : files.length === 1 ? files[0].name : `${files.length} files selected`;
      });
    });
  };

  const run = () => {
    translateNode(document.body);
    localizeFileInputs();
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, {once:true}); else run();

  const observer = new MutationObserver(mutations => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach(node => translateNode(node));
    }
    localizeFileInputs();
  });
  observer.observe(document.documentElement, {subtree:true, childList:true});
})();
